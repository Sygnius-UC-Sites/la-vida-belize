#!/usr/bin/env bash
# CloudPanel staging: convert static vhost to PHP 8.2, ensure MySQL, write Laravel .env.
# Passwords are written to files with mode 600 and never printed.
set -euo pipefail
set +x

SITE_USER="${DEPLOY_USER:?}"
APP_PATH="${DEPLOY_APP_PATH:?}"
DOMAIN_RAW="${DEPLOY_DOMAIN:?}"
DOMAIN="${DOMAIN_RAW#https://}"
DOMAIN="${DOMAIN#http://}"
DOMAIN="${DOMAIN%/}"
PUBLIC_URL="https://${DOMAIN}"
ENV_FILE="${APP_PATH}/projects/.env"
SECRET_DIR="/home/${SITE_USER}/.laravel-deploy"
DB_SECRET="${SECRET_DIR}/db.env"

log() { printf '%s\n' "$*"; }

fail_blocker() {
  printf 'CLOUDPANEL_BLOCKER: %s\n' "$*" >&2
  exit 2
}

find_vhost() {
  local found=""
  found="$(grep -RIl --include='*.conf' -e "server_name.*${DOMAIN}" /etc/nginx 2>/dev/null | head -n 1 || true)"
  if [ -z "$found" ]; then
    for candidate in \
      "/etc/nginx/sites-enabled/${DOMAIN}.conf" \
      "/etc/nginx/sites-enabled/${DOMAIN}" \
      "/etc/nginx/conf.d/${DOMAIN}.conf"; do
      if [ -f "$candidate" ]; then
        found="$candidate"
        break
      fi
    done
  fi
  printf '%s' "$found"
}

php_version() {
  if [ -x /usr/sbin/php-fpm8.2 ] || [ -d /etc/php/8.2/fpm ]; then
    printf '8.2'
    return
  fi
  if [ -x /usr/sbin/php-fpm8.3 ] || [ -d /etc/php/8.3/fpm ]; then
    printf '8.3'
    return
  fi
  if [ -x /usr/sbin/php-fpm8.4 ] || [ -d /etc/php/8.4/fpm ]; then
    printf '8.4'
    return
  fi
  fail_blocker "PHP-FPM 8.2+ is not installed on this CloudPanel host"
}

ensure_fpm_pool() {
  local ver="$1"
  local pool="/etc/php/${ver}/fpm/pool.d/${DOMAIN}.conf"
  local listen=""
  if [ -f "$pool" ]; then
    listen="$(awk '/^listen[[:space:]]*=/ {print $3; exit}' "$pool")"
    printf '%s' "$listen"
    return
  fi

  local port=8030
  local used
  used="$(grep -hE '^listen[[:space:]]*=[[:space:]]*127\.0\.0\.1:' /etc/php/*/fpm/pool.d/*.conf 2>/dev/null | awk -F: '{print $NF}' | tr -d '[:space:]' || true)"
  while printf '%s\n' "$used" | grep -qx "$port"; do
    port=$((port + 1))
  done
  listen="127.0.0.1:${port}"

  cat >"$pool" <<POOL
[${DOMAIN}]
user = ${SITE_USER}
group = ${SITE_USER}
listen = ${listen}
listen.owner = ${SITE_USER}
listen.group = ${SITE_USER}
pm = ondemand
pm.max_children = 50
pm.process_idle_timeout = 10s
pm.max_requests = 500
chdir = /
POOL

  systemctl reload "php${ver}-fpm" || service "php${ver}-fpm" reload || fail_blocker "Could not reload php${ver}-fpm"
  printf '%s' "$listen"
}

patch_vhost_file() {
  local vhost="$1"
  local listen="$2"
  python3 - "$vhost" "$APP_PATH" "$listen" <<'PY'
import re, sys
from pathlib import Path
path, root, listen = sys.argv[1], sys.argv[2], sys.argv[3]
text = Path(path).read_text(encoding="utf-8")
text = re.sub(r"index\s+index\.html\s*;", "index index.php index.html;", text)
if "index index.php" not in text:
    text = re.sub(r"index\s+[^;]+;", "index index.php index.html;", text, count=1)
text = re.sub(
    r"try_files\s+\$uri\s+\$uri/\s+=\s*404\s*;",
    "try_files $uri $uri/ /index.php?$query_string;",
    text,
)
text = re.sub(
    r"try_files\s+\$uri\s+\$uri/\s+/index\.html(?:\?\$[a-z_]+)?\s*;",
    "try_files $uri $uri/ /index.php?$query_string;",
    text,
)
text = re.sub(
    r"location\s+/\s*\{[^{}]*\}",
    "location / {\n    try_files $uri $uri/ /index.php?$query_string;\n  }",
    text,
    count=1,
)
if "try_files $uri $uri/ /index.php" not in text:
    text = re.sub(r"\n\}\s*$", "\n  location / {\n    try_files $uri $uri/ /index.php?$query_string;\n  }\n}\n", text, count=1)
text = re.sub(r"^(\s*)root\s+.+;$", rf"\1root {root};", text, count=1, flags=re.M)
if "fastcgi_pass" not in text:
    pass_target = listen if listen.startswith("127.") else ("unix:" + listen)
    php_block = """
  location ~ \\.php$ {
    include fastcgi_params;
    fastcgi_intercept_errors on;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    try_files $uri =404;
    fastcgi_read_timeout 3600;
    fastcgi_send_timeout 3600;
    fastcgi_param HTTPS $fastcgi_https;
    fastcgi_pass %s;
  }
""" % pass_target
    text = re.sub(r"\n\}\s*$", php_block + "\n}\n", text, count=1)
Path(path).write_text(text, encoding="utf-8")
print(path)
for i, line in enumerate(text.splitlines(), 1):
    if re.search(r"try_files|location |index |fastcgi_pass|^\s*root ", line):
        print(f"{i}:{line.strip()}")
PY
}

ensure_php_vhost() {
  local listen="$1"
  local files=""
  files="$(grep -RIl --include='*.conf' -e "server_name.*${DOMAIN}" /etc/nginx 2>/dev/null || true)"
  if [ -z "$files" ]; then
    files="$(find_vhost)"
  fi
  [ -n "$files" ] || fail_blocker "Could not find nginx vhost for ${DOMAIN}"

  local vhost
  while IFS= read -r vhost; do
    [ -n "$vhost" ] || continue
    [ -f "$vhost" ] || continue
    cp -a "$vhost" "${vhost}.pre-laravel.bak"
    log "Patching vhost ${vhost}"
    patch_vhost_file "$vhost" "$listen"
  done <<< "$files"

  if ! nginx -t; then
    while IFS= read -r vhost; do
      [ -f "${vhost}.pre-laravel.bak" ] && mv "${vhost}.pre-laravel.bak" "$vhost"
    done <<< "$files"
    fail_blocker "nginx rejected the PHP vhost; restored the previous config"
  fi
  systemctl reload nginx || service nginx reload || fail_blocker "Could not reload nginx"
  log "Converted nginx vhost to PHP with root ${APP_PATH}"
}

ensure_database() {
  install -d -m 700 -o "$SITE_USER" -g "$SITE_USER" "$SECRET_DIR"
  python3 - "$SECRET_DIR" "$SITE_USER" "$DOMAIN" "$DB_SECRET" <<'PY'
import os, re, subprocess, sys
from pathlib import Path

secret_dir, site_user, domain, db_secret_path = sys.argv[1:]
db_secret = Path(db_secret_path)
admin_cnf = Path("/root/.brad-mysql-admin.cnf")

def write_admin_cnf() -> None:
    if admin_cnf.is_file():
        return
    raw = subprocess.check_output(["clpctl", "db:show:master-credentials"], text=True, stderr=subprocess.STDOUT)
    def grab(*patterns):
        for pat in patterns:
            m = re.search(pat, raw, re.I)
            if m:
                return m.group(1).strip().strip("|").strip()
        return None
    host = grab(r"Host[:\s|]+([^\s|]+)") or "127.0.0.1"
    user = grab(r"User(?:\s*Name)?[:\s|]+([^\s|]+)") or "root"
    password = grab(r"Password[:\s|]+([^\s|]+)")
    port = grab(r"Port[:\s|]+(\d+)") or "3306"
    if not password:
        raise SystemExit("CLOUDPANEL_BLOCKER: could not parse MySQL master credentials from clpctl")
    admin_cnf.write_text(
        f"[client]\nhost={host}\nuser={user}\npassword={password}\nport={port}\n",
        encoding="utf-8",
    )
    admin_cnf.chmod(0o600)

def mysql_admin(*args: str) -> subprocess.CompletedProcess:
    write_admin_cnf()
    return subprocess.run(
        ["mysql", f"--defaults-extra-file={admin_cnf}", *args],
        check=True,
        capture_output=True,
        text=True,
    )

if db_secret.is_file():
    vals = dict(line.split("=", 1) for line in db_secret.read_text().splitlines() if "=" in line)
    out = mysql_admin("-N", "-e", "SHOW DATABASES").stdout.splitlines()
    if vals.get("DB_DATABASE") not in out:
        raise SystemExit("CLOUDPANEL_BLOCKER: saved staging database is missing")
    print("Reusing existing staging database (name only logged)")
    raise SystemExit(0)

db_name = "bradlavida"
db_user = "bradlavida"
db_pass = subprocess.check_output(
    ["openssl", "rand", "-base64", "24"], text=True
).strip().replace("/", "x").replace("+", "y").replace("=", "z")[:24] + "Aa1!"

mysql_admin("-e", f"CREATE DATABASE IF NOT EXISTS `{db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
mysql_admin("-e", f"CREATE USER IF NOT EXISTS '{db_user}'@'localhost' IDENTIFIED BY '{db_pass}';")
subprocess.run(
    ["mysql", f"--defaults-extra-file={admin_cnf}", "-e", f"ALTER USER '{db_user}'@'localhost' IDENTIFIED BY '{db_pass}';"],
    check=False,
    capture_output=True,
    text=True,
)
mysql_admin("-e", f"GRANT ALL PRIVILEGES ON `{db_name}`.* TO '{db_user}'@'localhost'; FLUSH PRIVILEGES;")
print("Created staging database via CloudPanel master credentials")

os.umask(0o077)
db_secret.write_text(
    f"DB_DATABASE={db_name}\nDB_USERNAME={db_user}\nDB_PASSWORD={db_pass}\n",
    encoding="utf-8",
)
os.chown(db_secret, int(subprocess.check_output(["id", "-u", site_user])), int(subprocess.check_output(["id", "-g", site_user])))
db_secret.chmod(0o600)
print("Staging MySQL database is ready")
PY
}

write_env_if_needed() {
  # shellcheck disable=SC1090
  . "$DB_SECRET"
  if [ -f "$ENV_FILE" ] && grep -q '^APP_KEY=base64:' "$ENV_FILE"; then
    log "Keeping existing Laravel .env"
    return
  fi

  local example="${APP_PATH}/projects/.env.example"
  [ -f "$example" ] || fail_blocker "Missing ${example} after rsync"

  umask 077
  cp "$example" "$ENV_FILE"
  python3 - "$ENV_FILE" "$PUBLIC_URL" "$DB_DATABASE" "$DB_USERNAME" "$DB_PASSWORD" <<'PY'
import sys
path, app_url, db, user, password = sys.argv[1:]
replacements = {
    "APP_NAME": '"La Vida Belize"',
    "APP_ENV": "production",
    "APP_DEBUG": "false",
    "APP_URL": app_url,
    "LOG_LEVEL": "error",
    "DB_DATABASE": db,
    "DB_USERNAME": user,
    "DB_PASSWORD": password,
}
out = []
for line in open(path, encoding="utf-8"):
    if "=" in line and not line.lstrip().startswith("#"):
        key = line.split("=", 1)[0]
        if key in replacements:
            out.append(f"{key}={replacements[key]}\n")
            continue
    out.append(line)
open(path, "w", encoding="utf-8").writelines(out)
PY
  chown "${SITE_USER}:${SITE_USER}" "$ENV_FILE"
  chmod 600 "$ENV_FILE"
  log "Wrote Laravel .env for staging (secrets not logged)"
}

case "${1:-}" in
  prepare)
    [ -d "$APP_PATH" ] || fail_blocker "CloudPanel app path ${APP_PATH} does not exist"
    PHP_VER="$(php_version)"
    log "Using PHP ${PHP_VER}"
    LISTEN="$(ensure_fpm_pool "$PHP_VER")"
    ensure_php_vhost "$LISTEN"
    ensure_database
    install -d -m 755 -o "$SITE_USER" -g "$SITE_USER" "$APP_PATH"
    ;;
  env)
    [ -d "${APP_PATH}/projects" ] || fail_blocker "Laravel project missing at ${APP_PATH}/projects"
    write_env_if_needed
    ;;
  *)
    echo "Usage: $0 prepare|env" >&2
    exit 1
    ;;
esac
