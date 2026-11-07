#!/usr/bin/env bash
# Composer, APP_KEY, DB import/migrate/seed, AdminLTE assets. Never prints secrets.
set -euo pipefail
set +x

SITE_USER="${DEPLOY_USER:?}"
APP_PATH="${DEPLOY_APP_PATH:?}"
DB_SECRET="/home/${SITE_USER}/.laravel-deploy/db.env"
PROJECT="${APP_PATH}/projects"

[ -f "$DB_SECRET" ] || { echo "CLOUDPANEL_BLOCKER: missing DB secret file" >&2; exit 2; }
[ -f "${PROJECT}/artisan" ] || { echo "CLOUDPANEL_BLOCKER: Laravel artisan missing after rsync" >&2; exit 2; }

PHP_BIN="php"
if command -v php8.2 >/dev/null 2>&1; then
  PHP_BIN="php8.2"
elif command -v php8.3 >/dev/null 2>&1; then
  PHP_BIN="php8.3"
fi

if ! command -v composer >/dev/null 2>&1; then
  "$PHP_BIN" -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
  "$PHP_BIN" /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer >/dev/null
  rm -f /tmp/composer-setup.php
fi
COMPOSER_BIN="$(command -v composer)"
COMPOSER_HOME="/home/${SITE_USER}/.composer"
install -d -m 700 -o "$SITE_USER" -g "$SITE_USER" "$COMPOSER_HOME" "${COMPOSER_HOME}/cache"

install -d -m 775 -o "$SITE_USER" -g "$SITE_USER" \
  "${PROJECT}/storage" \
  "${PROJECT}/storage/app" \
  "${PROJECT}/storage/app/public" \
  "${PROJECT}/storage/framework" \
  "${PROJECT}/storage/framework/cache" \
  "${PROJECT}/storage/framework/cache/data" \
  "${PROJECT}/storage/framework/sessions" \
  "${PROJECT}/storage/framework/views" \
  "${PROJECT}/storage/logs" \
  "${PROJECT}/bootstrap/cache" \
  "${APP_PATH}/vendor"

as_site() {
  su -s /bin/bash "$SITE_USER" -c "export COMPOSER_HOME='$COMPOSER_HOME' COMPOSER_CACHE_DIR='$COMPOSER_HOME/cache'; cd '$PROJECT' && $*"
}

as_site "$PHP_BIN -d memory_limit=1G '$COMPOSER_BIN' install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-ansi"

if ! grep -q '^APP_KEY=base64:' "${PROJECT}/.env"; then
  as_site "$PHP_BIN artisan key:generate --force --ansi"
fi

python3 - "$DB_SECRET" "$PROJECT" "$SITE_USER" "$APP_PATH" <<'PY'
import subprocess, sys
from pathlib import Path

secret, project, site_user, app_path = sys.argv[1:]
vals = {}
for line in Path(secret).read_text(encoding="utf-8").splitlines():
    if "=" in line:
        k, v = line.split("=", 1)
        vals[k] = v

cnf = Path("/tmp/brad-mysql.cnf")
cnf.write_text(
    "[client]\nuser={u}\npassword={p}\ndatabase={d}\n".format(
        u=vals["DB_USERNAME"], p=vals["DB_PASSWORD"], d=vals["DB_DATABASE"]
    ),
    encoding="utf-8",
)
cnf.chmod(0o600)

count = subprocess.check_output(
    ["mysql", "--defaults-extra-file=" + str(cnf), "-N", "-e", "SHOW TABLES"],
    text=True,
).strip().splitlines()
table_count = len([row for row in count if row.strip()])
dump = Path(project) / "database" / "master-clean.sql"
if table_count < 5:
    if not dump.is_file():
        sys.exit("CLOUDPANEL_BLOCKER: master-clean.sql missing and database is empty")
    with dump.open("rb") as fh:
        subprocess.check_call(["mysql", "--defaults-extra-file=" + str(cnf)], stdin=fh)
    print("Imported sanitized master_clean schema")
else:
    print("Staging database already has tables; skipping SQL import")
cnf.unlink(missing_ok=True)
PY

as_site "$PHP_BIN artisan migrate --force --ansi"
as_site "$PHP_BIN artisan db:seed --class=LaVidaPropertiesSeeder --force --ansi"
as_site "$PHP_BIN artisan view:clear --ansi"
as_site "$PHP_BIN artisan cache:clear --ansi" || true
as_site "$PHP_BIN artisan adminlte:install --only=assets --force --ansi"
as_site "$PHP_BIN artisan storage:link --ansi" || true
if command -v "$PHP_BIN" >/dev/null 2>&1; then
  "$PHP_BIN" -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "opcache reset\n"; }' || true
fi

chown -R "${SITE_USER}:${SITE_USER}" "$APP_PATH"
chmod -R ug+rwx "${PROJECT}/storage" "${PROJECT}/bootstrap/cache"
echo "Laravel boot complete"
