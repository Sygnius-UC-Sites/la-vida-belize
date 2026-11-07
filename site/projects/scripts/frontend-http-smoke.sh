#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-${FRONTEND_SMOKE_BASE_URL:-}}"
MAX_PAGES="${2:-${FRONTEND_SMOKE_MAX_PAGES:-80}}"

if [[ -z "$BASE_URL" ]]; then
  echo "Usage: $0 https://example.com [max-pages]" >&2
  echo "Or set FRONTEND_SMOKE_BASE_URL." >&2
  exit 2
fi

BASE_URL="${BASE_URL%/}"
HOST="$(php -r 'echo parse_url($argv[1], PHP_URL_HOST);' "$BASE_URL")"
BODY_FILE="$(mktemp)"
QUEUE_FILE="$(mktemp)"
SEEN_FILE="$(mktemp)"
NEXT_FILE="$(mktemp)"
trap 'rm -f "$BODY_FILE" "$QUEUE_FILE" "$SEEN_FILE" "$NEXT_FILE"' EXIT

printf '/\n' > "$QUEUE_FILE"
: > "$SEEN_FILE"

normalize_path() {
  php -r '
    $url = html_entity_decode($argv[1], ENT_QUOTES | ENT_HTML5);
    $base = $argv[2];
    $host = parse_url($base, PHP_URL_HOST);
    if ($url === "" || str_starts_with($url, "#") || preg_match("/^(mailto|tel|sms|javascript):/i", $url)) exit;
    if (str_starts_with($url, "//")) $url = "https:" . $url;
    if (preg_match("/^https?:\/\//i", $url)) {
      if (parse_url($url, PHP_URL_HOST) !== $host) exit;
      $path = parse_url($url, PHP_URL_PATH) ?: "/";
      $query = parse_url($url, PHP_URL_QUERY);
      echo $path . ($query ? "?$query" : "");
      exit;
    }
    if ($url[0] !== "/") $url = "/" . $url;
    $url = preg_replace("/#.*/", "", $url);
    echo $url;
  ' "$1" "$BASE_URL"
}

should_skip() {
  local path="${1%%\?*}"
  case "$path" in
    /admin*|/login*|/logout*|/register*|/password*|/api/*|/pms-*|/stripe*|/paypal*|/square*|/booking/thank*|/booking/pay-link*|*.pdf|*.ics|*.jpg|*.jpeg|*.png|*.webp|*.avif|*.svg|*.css|*.js|*.woff|*.woff2|*.ttf)
      return 0
      ;;
  esac
  return 1
}

bad=0
checked=0

while [[ "$checked" -lt "$MAX_PAGES" ]]; do
  path="$(grep -vxFf "$SEEN_FILE" "$QUEUE_FILE" | head -1 || true)"
  [[ -n "$path" ]] || break
  echo "$path" >> "$SEEN_FILE"

  if should_skip "$path"; then
    continue
  fi

  if ! code="$(curl -sS -L --max-time 25 -o "$BODY_FILE" -w '%{http_code}' "$BASE_URL$path")"; then
    code="000"
  fi
  title="$(perl -0777 -ne 'print "$1" if m#<title>\s*(.*?)\s*</title>#is' "$BODY_FILE" | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g' | cut -c1-80)"
  checked=$((checked + 1))

  if [[ "$code" -ge 400 || "$code" == "000" ]]; then
    printf 'FAIL %-48s HTTP %s %s\n' "$path" "$code" "$title"
    bad=1
    continue
  fi

  if grep -Eiq 'ErrorException|Undefined array key|Undefined variable|Trying to access array offset|Ignition|Whoops|Laravel\\Ignition' "$BODY_FILE"; then
    printf 'FAIL %-48s exception page %s\n' "$path" "$title"
    bad=1
    continue
  fi

  printf 'OK   %-48s HTTP %s %s\n' "$path" "$code" "$title"

  perl -ne 'while(/(?:href|src)="([^"]+)"/g){print "$1\n"}' "$BODY_FILE" | while read -r raw; do
    next="$(normalize_path "$raw" || true)"
    [[ -n "$next" ]] || continue
    should_skip "$next" && continue
    grep -qxF "$next" "$SEEN_FILE" && continue
    grep -qxF "$next" "$QUEUE_FILE" && continue
    echo "$next" >> "$NEXT_FILE"
  done

  if [[ -s "$NEXT_FILE" ]]; then
    cat "$NEXT_FILE" >> "$QUEUE_FILE"
    : > "$NEXT_FILE"
  fi
done

echo "Checked $checked public pages"
exit "$bad"
