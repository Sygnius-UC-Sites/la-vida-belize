#!/usr/bin/env bash
set -euo pipefail

STRICT=0
TARGETS=()

for arg in "$@"; do
  case "$arg" in
    --strict)
      STRICT=1
      ;;
    *)
      TARGETS+=("$arg")
      ;;
  esac
done

if [[ "${#TARGETS[@]}" -eq 0 ]]; then
  echo "Usage: $0 [--strict] path/to/client-css-or-dir [...]" >&2
  exit 2
fi

tmp_files="$(mktemp)"
trap 'rm -f "$tmp_files"' EXIT

for target in "${TARGETS[@]}"; do
  if [[ -d "$target" ]]; then
    rg --files "$target" -g '*.css' >> "$tmp_files"
  elif [[ -f "$target" && "$target" == *.css ]]; then
    printf '%s\n' "$target" >> "$tmp_files"
  else
    echo "WARN: skipping non-css target: $target" >&2
  fi
done

sort -u "$tmp_files" -o "$tmp_files"

if [[ ! -s "$tmp_files" ]]; then
  echo "No CSS files found." >&2
  exit 2
fi

bad=0

scan_pattern() {
  local label="$1"
  local pattern="$2"
  local found=0

  while IFS= read -r file; do
    case "$file" in
      */assets/fontawesome-*|*/bootstrap*|*/vendor/*|*/node_modules/*)
        continue
        ;;
    esac

    if rg -n --pcre2 "$pattern" "$file" >/tmp/css-scan-hit.$$ 2>/dev/null; then
      if [[ "$found" -eq 0 ]]; then
        printf '\n[%s]\n' "$label"
      fi
      found=1
      bad=1
      sed "s#^#$file:#" /tmp/css-scan-hit.$$
    fi
    rm -f /tmp/css-scan-hit.$$
  done < "$tmp_files"
}

scan_pattern "Global element selectors" '(^|[,{]\s*)(html|body|main|section|article|header|footer|nav|button|input|select|textarea|a|img|\*)\s*[,>{.#:\[]'
scan_pattern "Core/framework class selectors" '(^|[,{]\s*)\.(container|row|col(-|$)|btn|navbar|dropdown|modal|card|form-control|table|alert|hero|booking-bar|calendar|datepicker|gallery|property|footer|header)\b'
scan_pattern "High stacking contexts" 'z-index\s*:\s*[1-9][0-9]{3,}'
scan_pattern "Forced overrides" '!important\b'
scan_pattern "Clipping/position risks" '(overflow\s*:\s*hidden|position\s*:\s*fixed)'

if [[ "$bad" -eq 0 ]]; then
  echo "CSS collision scan passed."
  exit 0
fi

echo
echo "CSS collision scan found risky selectors/properties."
echo "Scope imported client CSS under a client root class, or move the rule into a page-specific layer."

if [[ "$STRICT" -eq 1 ]]; then
  exit 1
fi

exit 0
