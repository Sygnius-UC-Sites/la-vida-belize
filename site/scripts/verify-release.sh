#!/usr/bin/env bash
set -euo pipefail

target="${1:-}"
if [[ -z "$target" || ! -d "$target" ]]; then
    echo "Usage: $0 /path/to/release-directory" >&2
    exit 2
fi

forbidden="$(
    find "$target" \
        \( -name '.env' \
        -o -name '.DS_Store' \
        -o -name '._*' \
        -o -name 'database_backup.sql' \
        -o -name 'adminer.php' \
        -o -name '*.pms-backup-*' \
        -o -name '*.sql' \
        -o -name '*.sqlite' \
        -o -name '*.sqlite3' \
        -o -name '*.bak' \
        -o -path '*/storage/debugbar/*' \
        -o -path '*/storage/backups/*' \
        -o -path '*/storage/logs/*' \
        -o -path "$target/projects/tests/*" \
        -o -path "$target/projects/vendor.old/*" \
        -o -path '*/vendor/barryvdh/laravel-debugbar/*' \) \
        -print
)"

if [[ -n "$forbidden" ]]; then
    echo "Release contains forbidden files:" >&2
    echo "$forbidden" >&2
    exit 1
fi

secret_hits="$(
    find "$target" -type f \
        ! -path '*/vendor/*' \
        ! -path '*/front/assets/*' \
        ! -name 'composer.lock' \
        ! -name '.env.example' \
        -print0 \
        | xargs -0 grep -IlE \
            'sk_live_[A-Za-z0-9]{16,}|AKIA[0-9A-Z]{16}|APP_KEY=base64:[A-Za-z0-9+/=]{20,}|-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----' \
        || true
)"

if [[ -n "$secret_hits" ]]; then
    echo "Release contains credential-like material:" >&2
    echo "$secret_hits" >&2
    exit 1
fi

echo "RELEASE_ARTIFACT_CHECK=OK"
