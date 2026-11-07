#!/usr/bin/env bash
# =============================================================================
# pms-install.sh — MultiPms module installer
# =============================================================================
# Usage:
#   bash pms-install.sh /path/to/client-site/projects
#
# Run from the multipms-module folder. Installs MultiPms onto any live client
# site running the same Laravel PMS codebase — without touching anything else.
# Safe to re-run — every step checks if already done.
# =============================================================================

set -e

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
ok()   { echo -e "${GREEN}  ✔  $*${NC}"; }
info() { echo -e "${YELLOW}  →  $*${NC}"; }
err()  { echo -e "${RED}  ✘  $*${NC}"; exit 1; }

echo ""
echo "================================================"
echo "  MultiPms Module Installer"
echo "================================================"
echo ""

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
MODULE_SOURCE="$SCRIPT_DIR/app/Modules/MultiPms"
ABSTRACT_SOURCE="$SCRIPT_DIR/app/Modules/AbstractModuleServiceProvider.php"

if [ -z "$1" ]; then
  echo -e "${YELLOW}Usage: bash pms-install.sh /path/to/client-site/projects${NC}"
  echo "  Example: bash pms-install.sh ~/sites/client-abc/projects"
  exit 1
fi

TARGET_PROJECTS="$(cd "$1" && pwd)" || err "Target path not found: $1"
[ -d "$MODULE_SOURCE" ]           || err "MultiPms module not found at $MODULE_SOURCE"
[ -f "$TARGET_PROJECTS/artisan" ] || err "No artisan found at $TARGET_PROJECTS"

info "Installing into: $TARGET_PROJECTS"
echo ""

# ── Step 1: Copy module ───────────────────────────────────────────────────────
echo "Step 1/8 — Copy MultiPms module files"
mkdir -p "$TARGET_PROJECTS/app/Modules"
cp -r "$MODULE_SOURCE" "$TARGET_PROJECTS/app/Modules/"
if [ -f "$ABSTRACT_SOURCE" ] && [ ! -f "$TARGET_PROJECTS/app/Modules/AbstractModuleServiceProvider.php" ]; then
  cp "$ABSTRACT_SOURCE" "$TARGET_PROJECTS/app/Modules/AbstractModuleServiceProvider.php"
fi
ok "Module files copied"

# ── Step 2: Register ServiceProvider ─────────────────────────────────────────
echo ""
echo "Step 2/8 — Register ServiceProvider in config/app.php"
CONFIG="$TARGET_PROJECTS/config/app.php"
[ -f "$CONFIG" ] || err "config/app.php not found"

if grep -q "MultiPmsServiceProvider" "$CONFIG"; then
  ok "Already registered"
else
  python3 - "$CONFIG" <<'PYEOF'
import sys, re
path = sys.argv[1]
with open(path, 'r') as f:
    content = f.read()
block = (
    "\n        /*\n"
    "         * MultiPms Module\n"
    "         */\n"
    "        App\\Modules\\MultiPms\\MultiPmsServiceProvider::class,\n"
)
anchor = re.search(r'App\\\\Providers\\\\RouteServiceProvider::class,[ \t]*\n', content)
if not anchor:
    anchor = re.search(r'App\\\\Providers\\\\[A-Za-z]+ServiceProvider::class,[ \t]*\n', content)
if anchor:
    pos = anchor.end()
    content = content[:pos] + block + content[pos:]
    with open(path, 'w') as f:
        f.write(content)
    print("  Registered in config/app.php")
else:
    print("  WARNING: Could not auto-register. Add manually:")
    print("  App\\Modules\\MultiPms\\MultiPmsServiceProvider::class,")
PYEOF
  ok "ServiceProvider registered"
fi

# ── Step 3: Run migrations ────────────────────────────────────────────────────
echo ""
echo "Step 3/8 — Run migrations"
cd "$TARGET_PROJECTS"
php artisan migrate --path=app/Modules/MultiPms/database/migrations --force 2>&1 | \
  grep -E "Migrating|Migrated|Nothing|already" | sed 's/^/    /'
ok "Migrations done"

# ── Step 4: Patch LiveCart.php ────────────────────────────────────────────────
echo ""
echo "Step 4/8 — Patch LiveCart.php (wire PMS blocked dates)"
LIVECART="$TARGET_PROJECTS/app/Helper/LiveCart.php"
[ -f "$LIVECART" ] || err "LiveCart.php not found"

if grep -q "PMS bookings" "$LIVECART"; then
  ok "Already patched"
else
  python3 - "$LIVECART" <<'PYEOF'
import sys
path = sys.argv[1]
with open(path, 'r') as f:
    content = f.read()
anchor = "            }\n        }\n        $result = array_intersect"
patch = (
    "            }\n        }\n"
    "        // PMS bookings — synced from external PMS (Lodgify, Hostfully, Guesty, Hostaway).\n"
    "        $pmsRoomId = DB::table('properties')->where('id', $property_id)->value('pms_room_id');\n"
    "        if ($pmsRoomId && class_exists(\\App\\Modules\\MultiPms\\Models\\PmsBooking::class)) {\n"
    "            $cancelledStatuses = ['cancelled','canceled','declined','rejected','refused','cancel'];\n"
    "            $pmsBookings = \\App\\Modules\\MultiPms\\Models\\PmsBooking::where('property_id', $pmsRoomId)\n"
    "                ->where('departure', '>', date('Y-m-d'))\n"
    "                ->whereNotNull('arrival')->whereNotNull('departure')\n"
    "                ->where(function ($q) use ($cancelledStatuses) {\n"
    "                    $q->whereNull('status')\n"
    "                      ->orWhereRaw('LOWER(status) NOT IN ('.implode(',', array_fill(0, count($cancelledStatuses), '?')).')', $cancelledStatuses);\n"
    "                })->get(['arrival', 'departure']);\n"
    "            foreach ($pmsBookings as $row) {\n"
    "                $checkin[]  = $row->arrival; $checkout[] = $row->departure;\n"
    "                $now = strtotime($row->arrival); $your_date = strtotime($row->departure);\n"
    "                $day = ceil(($your_date - $now) / 86400);\n"
    "                for ($i = 1; $i < $day; $i++) {\n"
    "                    $blocked[] = date('Y-m-d', strtotime(\"+{$i} day\", $now));\n"
    "                }\n"
    "            }\n"
    "        }\n"
    "        $result = array_intersect"
)
if anchor in content:
    content = content.replace(anchor, patch, 1)
    with open(path, 'w') as f:
        f.write(content)
    print("  LiveCart.php patched")
else:
    print("  WARNING: anchor not found — patch manually.")
PYEOF
  ok "LiveCart.php patched"
fi

# ── Step 5: Patch admin property list (PMS badge) ─────────────────────────────
echo ""
echo "Step 5/8 — Add PMS badge to admin property list"
ADMIN_VIEW="$TARGET_PROJECTS/resources/views/admin/properties/index.blade.php"

if [ ! -f "$ADMIN_VIEW" ]; then
  info "Admin properties view not found — skipping"
else
  if grep -q "pms_source" "$ADMIN_VIEW"; then
    ok "Already patched"
  else
    python3 - "$ADMIN_VIEW" <<'PYEOF'
import sys, re
path = sys.argv[1]
with open(path, 'r') as f:
    content = f.read()
badge = (
    'title="Photos in gallery"></i> {{ $p->photos_count ?? 0 }}</span>\n'
    '                                                @if($p->pms_source)\n'
    '                                                    @php\n'
    "                                                        $pmsColors=['lodgify'=>'info','hostfully'=>'success','guesty'=>'warning','hostaway'=>'primary'];\n"
    "                                                        $pmsColor=$pmsColors[$p->pms_source]??'secondary';\n"
    '                                                    @endphp\n'
    '                                                    <span class="badge badge-{{ $pmsColor }}">\n'
    '                                                        <i class="fa fa-sync-alt"></i> {{ ucfirst($p->pms_source) }}\n'
    '                                                    </span>\n'
    '                                                @endif'
)
content = re.sub(
    r'title="Photos in gallery"><i class="fa fa-image"></i> \{\{ \$p->photos_count \?\? 0 \}\}</span>',
    badge, content, count=1
)
with open(path, 'w') as f:
    f.write(content)
print("  Admin view patched")
PYEOF
    ok "PMS badge added"
  fi
fi

# ── Step 6: Patch single-pms.blade.php (datepicker fixes) ────────────────────
echo ""
echo "Step 6/8 — Patch single-pms.blade.php (nights tooltip + datepicker fixes)"
SINGLE_PMS="$TARGET_PROJECTS/resources/views/front/property/single-pms.blade.php"

if [ ! -f "$SINGLE_PMS" ]; then
  info "single-pms.blade.php not found — skipping"
else
  python3 - "$SINGLE_PMS" <<'PYEOF'
import sys
path = sys.argv[1]
with open(path, 'r') as f:
    content = f.read()

changed = False

# 1. Fix lockDaysFilter to guard against null d1
old_filter = (
    "lockDaysFilter: function(d1, d2, pickedDates) {\n"
    "                    const dateStr = new Date(d1).toISOString().split('T')[0];"
)
new_filter = (
    "lockDaysFilter: function(d1, d2, pickedDates) {\n"
    "                    if (!d1) return false;\n"
    "                    const date = new Date(d1);\n"
    "                    if (isNaN(date.getTime())) return false;\n"
    "                    const dateStr = date.toISOString().split('T')[0];"
)
if old_filter in content:
    content = content.replace(old_filter, new_filter, 1)
    changed = True
    print("  lockDaysFilter null guard added")
else:
    print("  lockDaysFilter already patched or not found")

# 2. Add tooltipNumber + tooltipText (nights instead of days)
if 'tooltipNumber' not in content:
    old_opts = "autoApply:        true,"
    new_opts = (
        "autoApply:        true,\n"
        "                tooltipNumber:    function(totalDays) { return totalDays - 1; },\n"
        "                tooltipText:      { one: 'night', other: 'nights' },"
    )
    if old_opts in content:
        content = content.replace(old_opts, new_opts, 1)
        changed = True
        print("  tooltipNumber/tooltipText added")
    else:
        print("  autoApply anchor not found — add tooltipNumber/tooltipText manually")

# 3. Fix parentEl to document.body (prevents off-screen positioning)
if 'parentEl:         triggerEl.parentElement' in content:
    content = content.replace(
        'parentEl:         triggerEl.parentElement',
        'parentEl:         document.body',
        1
    )
    changed = True
    print("  parentEl fixed to document.body")

if changed:
    with open(path, 'w') as f:
        f.write(content)
    print("  single-pms.blade.php saved")
else:
    print("  No changes needed")
PYEOF
  ok "single-pms.blade.php patched"
fi

# ── Step 7: Add guesty:warm-token to scheduler ────────────────────────────────
echo ""
echo "Step 7/8 — Register guesty:warm-token in Kernel.php"
KERNEL="$TARGET_PROJECTS/app/Console/Kernel.php"

if [ ! -f "$KERNEL" ]; then
  info "Kernel.php not found — skipping"
else
  if grep -q "guesty:warm-token" "$KERNEL"; then
    ok "Already registered"
  else
    python3 - "$KERNEL" <<'PYEOF'
import sys, re
path = sys.argv[1]
with open(path, 'r') as f:
    content = f.read()
schedule_line = (
    "\n        // Keep Guesty OAuth token warm — runs every 6 hours.\n"
    "        $schedule->command('guesty:warm-token')->everySixHours();\n"
)
# Insert after the first $schedule->command(...) line
anchor = re.search(r'(\$schedule->command\([^\)]+\)->[^\;]+;\n)', content)
if anchor:
    pos = anchor.end()
    content = content[:pos] + schedule_line + content[pos:]
    with open(path, 'w') as f:
        f.write(content)
    print("  guesty:warm-token added to scheduler")
else:
    print("  WARNING: Could not find schedule anchor. Add manually:")
    print("  $schedule->command('guesty:warm-token')->everySixHours();")
PYEOF
    ok "Scheduler updated"
  fi
fi

# ── Step 8: Clear caches ──────────────────────────────────────────────────────
echo ""
echo "Step 8/8 — Clear caches"
cd "$TARGET_PROJECTS"
php artisan config:clear --quiet && ok "config cleared"
php artisan route:clear  --quiet && ok "routes cleared"
php artisan view:clear   --quiet && ok "views cleared"
php artisan cache:clear  --quiet && ok "app cache cleared"

echo ""
echo "================================================"
echo -e "\033[0;32m  MultiPms installed successfully!\033[0m"
echo "================================================"
echo ""
echo "  Next steps:"
echo "  1. Add PMS API keys in Admin → Settings:"
echo "       Hostfully : hostfully_api_key"
echo "       Guesty     : guesty_client_id + guesty_api_key"
echo "       Hostaway   : hostaway_client_id + hostaway_client_secret"
echo "       Lodgify    : lodgify_api_key"
echo ""
echo "  2. Warm the Guesty token once:"
echo "       cd $TARGET_PROJECTS && php artisan guesty:warm-token"
echo ""
echo "  3. Run first sync:"
echo "       cd $TARGET_PROJECTS && php artisan pms:sync-all"
echo ""
echo "  4. Check any PMS property page — calendar blocked dates should show."
echo ""
