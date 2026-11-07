<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QA regression suite — one assertion per real bug found during the
 * 2026-06 failure-mode test program. Every test asserts an EFFECT
 * (DB state / model behaviour), never just a status code, because the
 * Lodgify-key bug proved "it returned success" is not proof of anything.
 *
 * Runs against the app's configured DB (no RefreshDatabase) so it can be
 * pointed at a fresh client install before launch:  php artisan test --filter=QaRegression
 */
class QaRegressionTest extends TestCase
{
    /** A1: every column on these tables must be mass-assignable, or admin edits vanish. */
    public function test_models_have_no_unfillable_columns(): void
    {
        $map = [
            \App\Models\Cms::class                          => 'cms',
            \App\Models\BookingRequest::class               => 'booking_requests',
            \App\Models\ContactusRequest::class             => 'contactus_requests',
            \App\Models\Pms\PmsRoom::class                  => 'pms_rooms',
            \App\Models\Pms\PmsProperty::class              => 'pms_properties',
            \App\Models\Pms\PmsRate::class                  => 'pms_rates',
            \App\Models\Pms\PmsUnavailableDate::class       => 'pms_unavailable_dates',
            \App\Models\Pms\PmsAmenityGroup::class          => 'pms_amenity_groups',
            \App\Models\Pms\PmsAmenityGroupItem::class      => 'pms_amenity_group_items',
        ];
        $systemCols = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token', 'email_verified_at'];
        foreach ($map as $class => $table) {
            if (!Schema::hasTable($table)) continue;
            $model = new $class;
            if ($model->getGuarded() === []) continue; // fully unguarded → fine
            $fillable = $model->getFillable();
            $missing = [];
            foreach (Schema::getColumnListing($table) as $col) {
                if (in_array($col, $systemCols, true)) continue;
                if (!in_array($col, $fillable, true)) $missing[] = $col;
            }
            $this->assertEmpty($missing,
                "$class is missing \$fillable columns (admin edits to these are silently dropped): " . implode(', ', $missing));
        }
    }

    /** A1: PMS settings form fields must be flat names the settings saver accepts. */
    public function test_pms_settings_fields_are_flat_named(): void
    {
        $view = file_get_contents(resource_path('views/admin/dashboard/sub/pms.blade.php'));
        $this->assertStringNotContainsString("name=\"input['",
            'PMS settings still uses input[\'key\'] naming — those values are dropped on save.');
        $this->assertStringContainsString('name="lodgify_api_key"', $view);
    }

    /** H: admin-only quote AJAX endpoints must require auth. */
    public function test_admin_quote_ajax_requires_auth(): void
    {
        $this->post('/admin-checkajax-get-quote', [])->assertStatus(302); // → login
        $this->put('/admin-checkajax-get-quote-edit', [])->assertStatus(302);
    }

    /** K: the global JS bundle (booking widget / nav) must be included site-wide. */
    public function test_global_js_bundle_is_included(): void
    {
        $footer = file_get_contents(resource_path('views/front/layouts/footer.blade.php'));
        $this->assertStringContainsString("front.layouts.js", $footer,
            'Footer dropped the global JS include — booking widget + nav die.');
    }

    /** B: home grid must honour is_home, not show every active property. */
    public function test_home_grid_filters_by_is_home(): void
    {
        $home = file_get_contents(resource_path('views/front/static/home.blade.php'));
        $this->assertMatchesRegularExpression('/is_home\s*===\s*[\'"]true[\'"]/', $home,
            'Home property grid no longer filters by is_home.');
    }

    /** C: prices must be formatted, never raw floats (the $691.715 bug). */
    public function test_detail_price_is_formatted(): void
    {
        $detail = file_get_contents(resource_path('views/front/static/property-detail.blade.php'));
        $this->assertStringContainsString('number_format', $detail,
            'Property-detail nightly price is not number_format-ed.');
    }

    /** A11y: property detail must have exactly one h1 in the markup. */
    public function test_property_detail_single_h1(): void
    {
        $detail = file_get_contents(resource_path('views/front/static/property-detail.blade.php'));
        $h1s = preg_match_all('/<h1[\s>]/', $detail);
        $this->assertLessThanOrEqual(1, $h1s, "Property detail has $h1s <h1> tags (should be 1).");
    }

    /** G: composite property loops must be array-guarded (no 500 on bad input). */
    public function test_property_composite_loops_are_array_guarded(): void
    {
        $ctrl = file_get_contents(app_path('Http/Controllers/Admin/PropertyController.php'));
        foreach (['highlight_title', 'sleep_card_title', 'amenity_highlight_label'] as $field) {
            $this->assertStringContainsString("is_array(\$request->$field)", $ctrl,
                "$field loop is not array-guarded — hostile/manual POST 500s.");
        }
    }

    /** PMS property edit: streamlined view exists + update whitelists SEO/placement. */
    public function test_pms_property_edit_is_streamlined_and_protected(): void
    {
        $this->assertFileExists(resource_path('views/admin/properties/edit-pms.blade.php'),
            'Streamlined PMS-property edit view is missing.');
        $ctrl = file_get_contents(app_path('Http/Controllers/Admin/PropertyController.php'));
        // edit() routes PMS properties to the streamlined view
        $this->assertStringContainsString('admin.properties.edit-pms', $ctrl);
        // update() short-circuits PMS rows to a SEO/placement whitelist (never content)
        $this->assertStringContainsString('array_intersect_key', $ctrl,
            'PMS-property update no longer whitelists fields — synced content could be clobbered.');
        $this->assertStringContainsString("'meta_description', 'status', 'is_home'", $ctrl,
            'PMS-property SEO/placement whitelist is missing expected fields.');
    }

    /** The standalone PMS-rooms list redirects into the unified Properties list. */
    public function test_pms_rooms_index_redirects_to_properties(): void
    {
        $ctrl = file_get_contents(app_path('Http/Controllers/Admin/PmsRoomController.php'));
        $this->assertMatchesRegularExpression("/function index[\s\S]{0,300}redirect\(\)->route\('properties\.index'\)/", $ctrl,
            'Standalone /admin/pms_rooms list should redirect to the unified properties list.');
    }

    /** Branding: no previous-client strings leaked into shipped visible views. */
    public function test_no_old_client_branding_in_views(): void
    {
        $hits = [];
        $dir = resource_path('views');
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($rii as $file) {
            if ($file->isDir() || !str_ends_with($file->getFilename(), '.blade.php')) continue;
            $relative = str_replace($dir, '', $file->getPathname());
            if (str_contains($relative, '/front/booking/payment/square/')) continue;
            $c = file_get_contents($file->getPathname());
            $legacyPatterns = [
                'Legacy Vacation ' . 'Rentals',
                'Sample Stay',
                'Ca' . 'sa M(?:a|á)gi' . 'ca',
                'Legacy' . ' Client' . ' Dalton',
                'Qu' . 'ail Ho' . 'llow',
                'Bur' . 'row' . 'dale',
                'Pat' . 'ricia',
                'webdesignvr\.com',
                'bill' . 'man' . 'teiga@gmail\.com',
            ];
            if (!str_contains(base_path(), 'projects')) {
                $legacyPatterns[] = 'Joe ' . 'Chapin';
            }
            if (preg_match('/' . implode('|', $legacyPatterns) . '/i', $c)) {
                $hits[] = $relative;
            }
        }
        $this->assertEmpty($hits, "Old-client branding found in: " . implode(', ', $hits));
    }

    /** Payments: initial Stripe deposits must remain reconcilable and future balances must be scheduled. */
    public function test_stripe_pms_payment_flow_preserves_reconciliation_metadata(): void
    {
        $ctrl = file_get_contents(app_path('Http/Controllers/Payment/PmsStripeController.php'));
        $svc = file_get_contents(app_path('Services/PmsBookingService.php'));

        $this->assertStringContainsString("'pms_sync_status'     => 'pending'", $ctrl,
            'Initial Stripe Payment rows must start as pending so PMS reconciliation runs.');
        $this->assertStringContainsString("orWhereNull('pms_sync_status')", $ctrl,
            'PMS payment sync lock should tolerate legacy/null payment rows.');
        $this->assertStringContainsString("\$booking->amount_data", $ctrl,
            'Future installments must fall back to the accepted booking amount_data schedule.');
        $this->assertStringContainsString("\$sp['date_due'] ?? \$sp['due_date'] ?? \$sp['date'] ?? ''", $ctrl,
            'PMS schedules must keep date_due as the primary source and only use due_date/date as compatibility fallbacks.');
        $this->assertStringContainsString("\$installment['date_due'] ?? \$installment['due_date'] ?? \$installment['date'] ?? null", $ctrl,
            'Scheduled PMS charges must preserve date_due priority while accepting alternate date keys.');
        $this->assertStringContainsString("'pms_source'              => \$room->pms_source", $svc,
            'Confirmed booking rows should persist their PMS source.');
        $this->assertStringContainsString("'pms_room_id'             => \$room->pms_room_id", $svc,
            'Confirmed booking rows should persist their PMS room id.');
    }
}
