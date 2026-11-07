#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-${ADMIN_SMOKE_BASE_URL:-}}"
EMAIL="${2:-${ADMIN_SMOKE_EMAIL:-}}"
PASSWORD="${3:-${ADMIN_SMOKE_PASSWORD:-}}"
EXISTING_COOKIE_JAR="${ADMIN_SMOKE_COOKIE_JAR:-}"

if [[ -z "$BASE_URL" ]]; then
  echo "Usage: $0 https://example.com admin@example.com password" >&2
  echo "Or set ADMIN_SMOKE_BASE_URL, ADMIN_SMOKE_EMAIL, ADMIN_SMOKE_PASSWORD." >&2
  echo "For an already-authenticated staging session, set ADMIN_SMOKE_COOKIE_JAR=/path/to/cookies.txt." >&2
  exit 2
fi

BASE_URL="${BASE_URL%/}"
if [[ -n "$EXISTING_COOKIE_JAR" ]]; then
  COOKIE_JAR="$EXISTING_COOKIE_JAR"
  OWN_COOKIE_JAR=0
else
  COOKIE_JAR="$(mktemp)"
  OWN_COOKIE_JAR=1
fi
LOGIN_HTML="$(mktemp)"
BODY_FILE="$(mktemp)"
if [[ "$OWN_COOKIE_JAR" -eq 1 ]]; then
  trap 'rm -f "$COOKIE_JAR" "$LOGIN_HTML" "$BODY_FILE"' EXIT
else
  trap 'rm -f "$LOGIN_HTML" "$BODY_FILE"' EXIT
fi

if [[ -z "$EXISTING_COOKIE_JAR" ]]; then
  if [[ -z "$EMAIL" || -z "$PASSWORD" ]]; then
    echo "FAIL login: missing credentials" >&2
    exit 1
  fi

  curl -fsS -L -c "$COOKIE_JAR" "$BASE_URL/login" -o "$LOGIN_HTML"
  TOKEN="$(perl -ne 'print "$1\n" if /name="_token"\s+value="([^"]+)"/' "$LOGIN_HTML" | head -1)"

  if [[ -z "$TOKEN" ]]; then
    echo "FAIL login: CSRF token not found at $BASE_URL/login" >&2
    exit 1
  fi

  login_code="$(curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -X POST "$BASE_URL/login" \
    --data-urlencode "_token=$TOKEN" \
    --data-urlencode "email=$EMAIL" \
    --data-urlencode "password=$PASSWORD" \
    -o "$BODY_FILE" \
    -w '%{http_code}')"

  if [[ "$login_code" != "302" && "$login_code" != "200" ]]; then
    echo "FAIL login: unexpected HTTP $login_code" >&2
    exit 1
  fi
fi

curl -fsS -L -b "$COOKIE_JAR" -c "$COOKIE_JAR" "$BASE_URL/admin" -o "$BODY_FILE"

if grep -Eq 'class="[^"]*login-form|action="[^"]*/login"|action='\''[^'\'']*/login'\''' "$BODY_FILE"; then
  echo "FAIL login: credentials were not accepted" >&2
  exit 1
fi

paths=(
  /admin
  /admin/booking-enquiries
  /admin/booking-enquiries/create
  /admin/contact-us-enquiries
  /admin/payments
  /admin/scheduled-payments
  /admin/pms-bookings
  /admin/properties
  /admin/properties/create
  /admin/property-categories
  /admin/property-categories/create
  /admin/locations
  /admin/locations/create
  /admin/attractions
  /admin/attractions/create
  /admin/attraction-categories
  /admin/attraction-categories/create
  /admin/coupons
  /admin/coupons/create
  /admin/scrape-sources
  /admin/cms
  /admin/cms/create
  /admin/cms/1/edit
  /admin/faqs
  /admin/faqs/create
  /admin/faq-categories
  /admin/faq-categories/create
  /admin/testimonials
  /admin/testimonials/create
  /admin/our-teams
  /admin/our-teams/create
  /admin/settings/stay-communications
  /admin/email-templates
  /admin/settings/rental-agreement
  /admin/setting
  /admin/users
  /admin/pms-settings
  /admin/operations
  /admin/settings/payment
  /admin/settings/payment-gateways
  /admin/settings/mail
  /admin/settings/spam-protection
  /admin/help
)

bad=0
for path in "${paths[@]}"; do
  code="$(curl -sS -L -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -o "$BODY_FILE" -w '%{http_code}' "$BASE_URL$path")"

  title="$(perl -0777 -ne 'print "$1" if m#<title>\s*(.*?)\s*</title>#is' "$BODY_FILE" | tr '\n' ' ' | sed 's/[[:space:]]\+/ /g' | cut -c1-80)"

  if [[ "$code" -ge 500 ]]; then
    printf 'FAIL %-42s HTTP %s %s\n' "$path" "$code" "$title"
    bad=1
    continue
  fi

  if grep -Eiq 'ErrorException|Undefined array key|Undefined variable|Trying to access array offset|Ignition|Whoops|Laravel\\Ignition' "$BODY_FILE"; then
    printf 'FAIL %-42s exception page %s\n' "$path" "$title"
    bad=1
    continue
  fi

  if grep -Eq 'class="[^"]*login-form|action="[^"]*/login"|action='\''[^'\'']*/login'\''' "$BODY_FILE"; then
    printf 'FAIL %-42s redirected to login\n' "$path"
    bad=1
    continue
  fi

  printf 'OK   %-42s HTTP %s %s\n' "$path" "$code" "$title"
done

exit "$bad"
