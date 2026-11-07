#!/usr/bin/env bash
set -euo pipefail

export COPYFILE_DISABLE=1

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
output="${1:-$root/dist/base-hollow-crm-release.tar.gz}"
stage="$(mktemp -d "${TMPDIR:-/tmp}/base-release.XXXXXX")"

cleanup() {
    rm -rf "$stage"
}
trap cleanup EXIT

mkdir -p "$(dirname "$output")"

tar -C "$root" -cf - \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='.claude' \
    --exclude='.delete-me' \
    --exclude='.trash' \
    --exclude='.DS_Store' \
    --exclude='._*' \
    --exclude='qa' \
    --exclude='dist' \
    --exclude='*.pms-backup-*' \
    --exclude='database_backup.sql' \
    --exclude='adminer.php' \
    --exclude='*.sql' \
    --exclude='*.sqlite' \
    --exclude='*.sqlite3' \
    --exclude='*.bak' \
    --exclude='projects/.env' \
    --exclude='projects/.phpunit.cache' \
    --exclude='projects/.phpunit.result.cache' \
    --exclude='projects/tests' \
    --exclude='projects/vendor' \
    --exclude='projects/vendor.old' \
    --exclude='projects/storage/debugbar' \
    --exclude='projects/storage/backups' \
    --exclude='projects/storage/logs' \
    --exclude='projects/storage/framework/cache' \
    --exclude='projects/storage/framework/sessions' \
    --exclude='projects/storage/framework/views' \
    . | tar -C "$stage" -xf -

composer install \
    --working-dir="$stage/projects" \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

rm -f "$stage/projects/bootstrap/cache/"*.php
mkdir -p \
    "$stage/projects/storage/framework/cache/data" \
    "$stage/projects/storage/framework/sessions" \
    "$stage/projects/storage/framework/views" \
    "$stage/projects/storage/logs"

"$root/scripts/verify-release.sh" "$stage"
tar -C "$stage" -czf "$output" .

echo "Release created: $output"
