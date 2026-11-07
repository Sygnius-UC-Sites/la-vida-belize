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
BODY_FILE="$(mktemp)"
LOGIN_HTML="$(mktemp)"
if [[ "$OWN_COOKIE_JAR" -eq 1 ]]; then
  trap 'rm -f "$COOKIE_JAR" "$BODY_FILE" "$LOGIN_HTML"' EXIT
else
  trap 'rm -f "$BODY_FILE" "$LOGIN_HTML"' EXIT
fi

failures=0

ok() {
  printf 'OK   %s\n' "$1"
}

fail() {
  printf 'FAIL %s\n' "$1" >&2
  failures=1
}

warn() {
  printf 'WARN %s\n' "$1" >&2
}

fetch() {
  local method="$1"
  local path="$2"
  shift 2

  curl -sS -L -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -X "$method" "$BASE_URL$path" \
    -o "$BODY_FILE" \
    -w '%{http_code}' \
    "$@"
}

assert_clean_body() {
  local label="$1"
  if grep -Eiq 'ErrorException|Undefined array key|Undefined variable|Trying to access array offset|Ignition|Whoops|Laravel\\Ignition' "$BODY_FILE"; then
    fail "$label rendered a Laravel exception"
    return 1
  fi
  return 0
}

assert_contains() {
  local label="$1"
  local needle="$2"
  if ! grep -Fq "$needle" "$BODY_FILE"; then
    fail "$label missing expected markup: $needle"
    return 1
  fi
  return 0
}

assert_not_contains() {
  local label="$1"
  local needle="$2"
  if grep -Fq "$needle" "$BODY_FILE"; then
    fail "$label contains blocked markup: $needle"
    return 1
  fi
  return 0
}

csrf_from_body() {
  perl -0777 -ne 'print "$1" if /name="_token"\s+value="([^"]+)"/s' "$BODY_FILE" | head -1
}

login() {
  if [[ -n "$EXISTING_COOKIE_JAR" ]]; then
    local existing_code
    existing_code="$(fetch GET /admin)"
    if [[ "$existing_code" -lt 400 ]] && ! grep -Eq 'class="[^"]*login-form|action="[^"]*/login"|action='\''[^'\'']*/login'\''' "$BODY_FILE"; then
      ok "existing admin session"
      return
    fi
    fail "existing cookie jar is not an authenticated admin session"
    return
  fi

  if [[ -z "$EMAIL" || -z "$PASSWORD" ]]; then
    fail "missing login credentials"
    return
  fi

  curl -fsS -L -c "$COOKIE_JAR" "$BASE_URL/login" -o "$LOGIN_HTML"
  local token
  token="$(perl -ne 'print "$1\n" if /name="_token"\s+value="([^"]+)"/' "$LOGIN_HTML" | head -1)"
  if [[ -z "$token" ]]; then
    fail "login CSRF token not found"
    return
  fi

  local code
  code="$(curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -X POST "$BASE_URL/login" \
    --data-urlencode "_token=$token" \
    --data-urlencode "email=$EMAIL" \
    --data-urlencode "password=$PASSWORD" \
    -o "$BODY_FILE" \
    -w '%{http_code}')"

  if [[ "$code" != "302" && "$code" != "200" ]]; then
    fail "login returned HTTP $code"
    return
  fi

  code="$(fetch GET /admin)"
  if [[ "$code" -ge 400 ]] || grep -Eq 'class="[^"]*login-form|action="[^"]*/login"|action='\''[^'\'']*/login'\''' "$BODY_FILE"; then
    fail "login credentials were not accepted"
    return
  fi

  ok "login"
}

delete_resource() {
  local path="$1"
  local token="$2"
  curl -sS -L -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -X POST "$BASE_URL$path" \
    --data-urlencode "_token=$token" \
    --data-urlencode "_method=DELETE" \
    -o "$BODY_FILE" >/dev/null || true
}

login

property_code="$(fetch GET /admin/properties/create)"
if [[ "$property_code" -ge 400 ]]; then
  fail "property create page returned HTTP $property_code"
else
  assert_clean_body "property create page" || true
  assert_contains "property create page" 'id="btn-quick-create-location-property"' || true
  assert_contains "property create page" 'id="quick-create-location-modal"' || true
  assert_contains "property create page" '/admin/locations/quick-create' || true
  assert_contains "property create page" 'Property type / division' || true
  assert_contains "admin layout CSS scope" 'body:has(.ls-hero) .ls-canvas' || true
  assert_not_contains "property create page" 'class="ls-hero"' || true
  ok "property create action markup"
fi

booking_modal_code="$(fetch GET /admin/booking-enquiries)"
if [[ "$booking_modal_code" -ge 400 ]] || ! assert_clean_body "booking enquiry page"; then
  fail "booking enquiry page returned HTTP $booking_modal_code"
else
  assert_contains "booking modal viewport fit" 'width: min(1100px, calc(100vw - 32px))' || true
  assert_contains "booking modal scroll body" 'max-height: calc(100vh - 96px)' || true
  assert_contains "booking send confirmation stacking" 'body.be-page .swal2-container' || true
  assert_contains "booking email row layout" 'be-email-row' || true
  ok "booking modal/action markup"
fi

csrf="$(csrf_from_body)"
if [[ -z "$csrf" ]]; then
  fail "property create CSRF token not found"
else
  loc_name="_qa_action_location_$(date +%s)"
  loc_slug="${loc_name//_/-}"
  quick_code="$(curl -sS -L -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -X POST "$BASE_URL/admin/locations/quick-create" \
    -H "Accept: application/json" \
    -H "X-CSRF-TOKEN: $csrf" \
    --data-urlencode "name=$loc_name" \
    --data-urlencode "seo_url=$loc_slug" \
    -o "$BODY_FILE" \
    -w '%{http_code}')"

  if [[ "$quick_code" != "200" ]] || ! assert_clean_body "quick add location"; then
    fail "quick add location returned HTTP $quick_code"
  else
    loc_id="$(php -r '$j=json_decode(file_get_contents($argv[1]), true); echo $j["id"] ?? "";' "$BODY_FILE")"
    if [[ -z "$loc_id" ]]; then
      fail "quick add location did not return an id"
    else
      ok "property quick add location endpoint"
      delete_resource "/admin/locations/$loc_id" "$csrf"
      ok "property quick add location cleanup"
    fi
  fi
fi

location_page_code="$(fetch GET /admin/locations/create)"
if [[ "$location_page_code" -ge 400 ]]; then
  fail "location create page returned HTTP $location_page_code"
else
  assert_clean_body "location create page" || true
  location_csrf="$(csrf_from_body)"
  std_name="_qa_standard_location_$(date +%s)"
  std_slug="${std_name//_/-}"
  std_code="$(curl -sS -L -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -X POST "$BASE_URL/admin/locations" \
    --data-urlencode "_token=$location_csrf" \
    --data-urlencode "name=$std_name" \
    --data-urlencode "seo_url=$std_slug" \
    -o "$BODY_FILE" \
    -w '%{http_code}')"

  if [[ "$std_code" -ge 500 ]] || ! assert_clean_body "standard add location"; then
    fail "standard add location returned HTTP $std_code"
  else
    ok "standard add location form"
    list_code="$(fetch GET /admin/locations)"
    if [[ "$list_code" -lt 400 ]] && grep -Fq "$std_name" "$BODY_FILE"; then
      std_id="$(php -r '$html = file_get_contents($argv[1]); $name = $argv[2]; if (preg_match("#<tr[^>]*>.*?" . preg_quote($name, "#") . ".*?/admin/locations/(\\d+)/edit#s", $html, $m)) echo $m[1];' "$BODY_FILE" "$std_name")"
      if [[ -n "$std_id" ]]; then
        delete_resource "/admin/locations/$std_id" "$location_csrf"
        ok "standard add location cleanup"
      else
        fail "standard location cleanup id not found"
      fi
    else
      fail "standard location was not visible in list for cleanup"
    fi
  fi
fi

attraction_code="$(fetch GET /admin/attractions/create)"
if [[ "$attraction_code" -ge 400 ]]; then
  fail "attraction create page returned HTTP $attraction_code"
else
  assert_clean_body "attraction create page" || true
  assert_contains "attraction create page" 'id="btn-quick-create-location-attraction"' || true
  assert_contains "attraction create page" 'id="btn-quick-create-category-attraction"' || true
  assert_contains "attraction create page" '/admin/attraction-categories/quick-create' || true
  ok "attraction create action markup"
fi

if [[ -n "${csrf:-}" ]]; then
  cat_name="_qa_action_category_$(date +%s)"
  cat_slug="${cat_name//_/-}"
  cat_code="$(curl -sS -L -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -X POST "$BASE_URL/admin/attraction-categories/quick-create" \
    -H "Accept: application/json" \
    -H "X-CSRF-TOKEN: $csrf" \
    --data-urlencode "name=$cat_name" \
    --data-urlencode "seo_url=$cat_slug" \
    -o "$BODY_FILE" \
    -w '%{http_code}')"

  if [[ "$cat_code" != "200" ]] || ! assert_clean_body "quick add attraction category"; then
    fail "quick add attraction category returned HTTP $cat_code"
  else
    cat_id="$(php -r '$j=json_decode(file_get_contents($argv[1]), true); echo $j["id"] ?? "";' "$BODY_FILE")"
    if [[ -z "$cat_id" ]]; then
      fail "quick add attraction category did not return an id"
    else
      ok "quick add attraction category endpoint"
      delete_resource "/admin/attraction-categories/$cat_id" "$csrf"
      ok "quick add attraction category cleanup"
    fi
  fi
fi

api_checks=(
  "/admin/api/search?q=base"
  "/admin/api/notifications"
)

for path in "${api_checks[@]}"; do
  code="$(fetch GET "$path")"
  if [[ "$code" -ge 400 ]] || ! assert_clean_body "$path"; then
    fail "$path returned HTTP $code"
  else
    ok "$path"
  fi
done

first_property_id="$(curl -sS -L -b "$COOKIE_JAR" -c "$COOKIE_JAR" "$BASE_URL/admin/properties" | perl -0777 -ne 'print "$1" if m#/admin/properties/(\d+)/edit#s' | head -1)"
properties_code="$(fetch GET /admin/properties)"
if [[ "$properties_code" -ge 400 ]] || ! assert_clean_body "property index page"; then
  fail "property index page returned HTTP $properties_code"
else
  first_public_url="$(perl -0777 -ne 'print "$1" if m#href="([^"]+/(?:stay|condos|houses)/[^"]+)"[^>]*>\s*<i[^>]*></i>View public page#s' "$BODY_FILE" | head -1)"
  bare_public_url="$(perl -0777 -ne 'print "$1" if m#href="([^"]+)"[^>]*>\s*<i[^>]*></i>View public page#s' "$BODY_FILE" | head -1)"
  if [[ -z "$first_public_url" ]]; then
    fail "property index public-page link is not canonical: ${bare_public_url:-missing}"
  else
    ok "property index canonical public link"
  fi
fi

if [[ -n "$first_property_id" ]]; then
  edit_code="$(fetch GET "/admin/properties/$first_property_id/edit")"
  if [[ "$edit_code" -ge 400 ]] || ! assert_clean_body "property edit page"; then
    fail "property edit page returned HTTP $edit_code"
  else
    assert_contains "property edit page" 'Property type / division' || true
    assert_contains "property edit page" 'property-gallery-manager' || true
    assert_contains "property edit page" 'id="photo-modal"' || true
    assert_contains "property photo modal" 'z-index: 1110' || true
    assert_contains "property photo modal backdrop" 'z-index: 1100' || true
    assert_contains "property photo modal clickability" 'pointer-events: auto' || true
    ok "property edit/gallery modal markup"
  fi

  calendar_checks=(
    "/admin/properties/$first_property_id/calendar/days?start=2027-01-01&end=2027-01-08"
    "/admin/properties/$first_property_id/calendar/reservations?start=2027-01-01&end=2027-01-08"
    "/admin/properties/$first_property_id/calendar/pricing-rules"
    "/admin/properties/$first_property_id/calendar/ical-feeds"
  )
  for path in "${calendar_checks[@]}"; do
    code="$(fetch GET "$path")"
    if [[ "$code" -ge 400 ]] || ! assert_clean_body "$path"; then
      fail "$path returned HTTP $code"
    else
      ok "$path"
    fi
  done
else
  warn "no property edit link found; skipped property edit/gallery/calendar action checks"
fi

cms_index_code="$(fetch GET /admin/cms)"
if [[ "$cms_index_code" -ge 400 ]] || ! assert_clean_body "CMS index page"; then
  fail "CMS index page returned HTTP $cms_index_code"
else
  cms_home_path="$(php -r '
    $html = file_get_contents($argv[1]);
    if (preg_match_all("#<tr[^>]*>.*?</tr>#is", $html, $rows)) {
        foreach ($rows[0] as $row) {
            $plain = trim(preg_replace("/\s+/", " ", strip_tags($row)));
            if (preg_match("/\bHome\b/i", $plain) && preg_match("#/admin/cms/(\d+)/edit#", $row, $m)) {
                echo "/admin/cms/{$m[1]}/edit";
                exit;
            }
        }
    }
  ' "$BODY_FILE")"

  if [[ -z "$cms_home_path" ]]; then
    warn "Home CMS row not found; skipped home CMS field checks"
    exit "$failures"
  fi

  cms_code="$(fetch GET "$cms_home_path")"
  if [[ "$cms_code" -ge 500 ]] || ! assert_clean_body "CMS home edit"; then
    fail "CMS home edit returned HTTP $cms_code"
    exit "$failures"
  fi

  cms_needles=(
    'Button &amp; Label Text'
    'Hero Section'
    'Hero Eyebrow'
    'Hero Carousel Images'
    'Our Story Section'
    'Retreats Section'
    'Homepage Gallery Section'
    'Gallery Photos'
  )
  for needle in "${cms_needles[@]}"; do
    assert_contains "CMS home edit" "$needle" || true
  done
  ok "CMS home editable field visibility"
fi

exit "$failures"
