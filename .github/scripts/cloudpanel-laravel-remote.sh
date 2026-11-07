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

ensure_php_vhost() {
  local vhost="$1"
  local listen="$2"
  local backup="${vhost}.pre-laravel.bak"

  if grep -q 'fastcgi_pass' "$vhost" && grep -q 'index.php' "$vhost"; then
    if grep -q "root ${APP_PATH}" "$vhost" || grep -q "root ${APP_PATH};" "$vhost"; then
      log "Nginx vhost already serves PHP from ${APP_PATH}"
      return
    fi
    sed -i.bak-root -E "s#^[[:space:]]*root[[:space:]].*;#  root ${APP_PATH};#" "$vhost"
    nginx -t
    systemctl reload nginx || service nginx reload
    log "Updated nginx root to ${APP_PATH}"
    return
  fi

  cp -a "$vhost" "$backup"

  python3 - "$vhost" "$APP_PATH" "$listen" <<'PY'
import re, sys
path, root, listen = sys.argv[1], sys.argv[2], sys.argv[3]
text = open(path, encoding="utf-8").read()
text = re.sub(r"index\s+index\.html\s*;", "index index.php index.html;", text)
text = re.sub(
    r"location\s+/\s*\{[^}]*try_files\s+\$uri\s+\$uri/\s+=404;\s*\}",
    "location / {\n    try_files $uri $uri/ /index.php?$query_string;\n  }",
    text,
    count=1,
)
if "try_files $uri $uri/ /index.php" not in text:
    text = text.replace("try_files $uri $uri/ =404;", "try_files $uri $uri/ /index.php?$query_string;")
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
text = re.sub(r"^(\s*)root\s+.+;$", rf"\1root {root};", text, count=1, flags=re.M)
open(path, "w", encoding="utf-8").write(text)
PY

  if ! nginx -t; then
    mv "$backup" "$vhost"
    fail_blocker "nginx rejected the PHP vhost; restored the previous config"
  fi
  systemctl reload nginx || service nginx reload || fail_blocker "Could not reload nginx"
  log "Converted nginx vhost to PHP with root ${APP_PATH}"
}

ensure_database() {
  install -d -m 700 -o "$SITE_USER" -g "$SITE_USER" "$SECRET_DIR"
  if [ -f "$DB_SECRET" ]; then
    # shellcheck disable=SC1090
    . "$DB_SECRET"
    mysql -N -e "SHOW DATABASES" | grep -qx "$DB_DATABASE" || fail_blocker "Saved staging DB ${DB_DATABASE} is missing"
    log "Reusing existing staging database (name only logged)"
    return
  fi

  local db_name="brad_lavida"
  local db_user="bradlavida"
  local db_pass
  db_pass="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)Aa1!"

  mysql -e "CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql -e "CREATE USER IF NOT EXISTS '${db_user}'@'localhost' IDENTIFIED BY '${db_pass}';"
  mysql -e "ALTER USER '${db_user}'@'localhost' IDENTIFIED BY '${db_pass}';" 2>/dev/null || true
  mysql -e "GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'localhost'; FLUSH PRIVILEGES;"

  umask 077
  cat >"$DB_SECRET" <<EOF
DB_DATABASE=${db_name}
DB_USERNAME=${db_user}
DB_PASSWORD=${db_pass}
EOF
  chown "${SITE_USER}:${SITE_USER}" "$DB_SECRET"
  chmod 600 "$DB_SECRET"
  log "Staging MySQL database is ready"
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
    VHOST="$(find_vhost)"
    [ -n "$VHOST" ] || fail_blocker "Could not find nginx vhost for ${DOMAIN}"
    log "Vhost: ${VHOST}"
    LISTEN="$(ensure_fpm_pool "$PHP_VER")"
    ensure_php_vhost "$VHOST" "$LISTEN"
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
