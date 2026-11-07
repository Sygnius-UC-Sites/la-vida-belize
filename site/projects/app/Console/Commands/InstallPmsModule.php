<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstallPmsModule extends Command
{
    protected $signature = 'pms:install {--dry-run : Show what would be done without making changes}';

    protected $description = 'Detect the site PMS provider, map credentials, and seed pms_rooms when a legacy table exists';

    /** Detect whether basic_settings uses "key" or "name" as its lookup column (varies per site) */
    private function settingsKeyCol(): string
    {
        return Schema::hasColumn('basic_settings', 'key') ? 'key' : 'name';
    }

    private function getSetting(string $setting): ?string
    {
        return DB::table('basic_settings')->where($this->settingsKeyCol(), $setting)->value('value');
    }

    private function upsertSetting(string $setting, string $value): void
    {
        DB::table('basic_settings')->updateOrInsert(
            [$this->settingsKeyCol() => $setting],
            ['value' => $value, 'updated_at' => now()]
        );
    }

    /** Append PMS_PAGE_MODE to .env once, if absent (never overrides a manual choice). */
    private function ensurePageMode(string $mode): void
    {
        $env = base_path('.env');
        if (!is_file($env) || !is_writable($env)) {
            return;
        }
        $body = (string) file_get_contents($env);
        if (preg_match('/^PMS_PAGE_MODE=/m', $body)) {
            return; // respect an existing choice
        }
        file_put_contents($env, rtrim($body) . "\nPMS_PAGE_MODE={$mode}\n");
        $this->line("  .env: PMS_PAGE_MODE={$mode} (auto)");
    }

    /**
     * Detect the site's PMS provider WITHOUT assuming Guesty:
     *  1. explicit pms_source / pms_driver setting if present
     *  2. the dominant pms_source already in pms_rooms (PMS-native sites)
     *  3. which credentials exist in basic_settings
     *  4. presence of a legacy guesty_properties table (old Guesty sites)
     * Returns 'guesty'|'lodgify'|'hostaway'|'hostfully' or '' if undetermined.
     */
    private function detectProvider(): string
    {
        foreach (['pms_source', 'pms_driver'] as $k) {
            $v = strtolower((string) $this->getSetting($k));
            if (in_array($v, ['guesty', 'lodgify', 'hostaway', 'hostfully'], true)) {
                return $v;
            }
        }
        if (Schema::hasTable('pms_rooms')) {
            $row = DB::table('pms_rooms')->select('pms_source')
                ->whereNotNull('pms_source')->where('pms_source', '!=', '')
                ->groupBy('pms_source')->orderByRaw('COUNT(*) DESC')->first();
            if ($row && in_array($row->pms_source, ['guesty', 'lodgify', 'hostaway', 'hostfully'], true)) {
                return $row->pms_source;
            }
        }
        if ($this->getSetting('lodgify_api_key'))   return 'lodgify';
        if ($this->getSetting('hostaway_api_key'))  return 'hostaway';
        if ($this->getSetting('hostfully_api_key')) return 'hostfully';
        if ($this->getSetting('OpenClientid') || $this->getSetting('guesty_client_id')) return 'guesty';
        if (Schema::hasTable('guesty_properties')) return 'guesty';
        return '';
    }

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        $this->info($dry ? '[DRY-RUN] pms:install starting' : 'pms:install starting');
        $this->line('  basic_settings key column: ' . $this->settingsKeyCol());

        if (!Schema::hasTable('pms_rooms')) {
            $this->warn('  pms_rooms table does not exist yet -- run migrations first');
            return 1;
        }

        // ── Detect which PMS THIS site uses (never assume Guesty) ────────────
        $provider = $this->detectProvider();
        $this->info("  Detected PMS provider: " . ($provider ?: 'none yet — set credentials in admin then run pms:sync-all'));
        if ($provider && !$dry) {
            $this->upsertSetting('pms_source', $provider);
            $this->upsertSetting('pms_driver', $provider); // PmsManager::driver() reads this
        }

        // Auto-pick the page mode that suits this site (only if not already set
        // in .env). Old Guesty sites keep their own pages → 'widget'. PMS-native
        // sites (Lodgify/Hostaway/Hostfully) serve the unified page → 'full'.
        if ($provider && !$dry) {
            $this->ensurePageMode($provider === 'guesty' ? 'widget' : 'full');
        }

        // Rewrite any hardcoded legacy model refs in site views so pages don't
        // 500 on null (e.g. LodgifyRoom → PmsRoom). Idempotent, backs up files.
        if (!$dry) {
            try { $this->call('pms:upgrade-views'); } catch (\Throwable $e) { $this->warn('  view upgrade skipped: '.$e->getMessage()); }
        }

        // ── Map legacy Guesty credentials ONLY on Guesty sites ──────────────
        if ($provider === 'guesty') {
            $clientId = $this->getSetting('OpenClientid');
            if ($clientId !== null && !$dry) { $this->upsertSetting('guesty_client_id', $clientId); $this->line('  guesty_client_id mapped'); }
            $apiKey = $this->getSetting('OpenClientSecretkey');
            if ($apiKey !== null && !$dry) { $this->upsertSetting('guesty_api_key', $apiKey); $this->line('  guesty_api_key mapped'); }
        }

        // ── Legacy seed: ONLY Guesty sites have guesty_properties to seed from.
        // Lodgify/Hostaway/Hostfully sites populate pms_rooms via pms:sync-all
        // (the adapters), so there is nothing to seed here.
        $guestyModel = 'App\\Models\\Guesty\\GuestyProperty';
        if ($provider !== 'guesty' || !Schema::hasTable('guesty_properties') || !class_exists($guestyModel)) {
            $this->info('  No Guesty legacy table to seed (provider=' . ($provider ?: 'n/a') . '). Run: php artisan pms:sync-all' . ($provider ? " --provider={$provider}" : ''));
            $this->info($dry ? '[DRY-RUN] pms:install complete (no changes made)' : 'pms:install complete');
            return 0;
        }

        $properties = $guestyModel::where('active', 1)->get();
        $allGuesty  = $guestyModel::get(['_id', 'seo_url', 'nickname']);
        $this->info("  Found {$properties->count()} active GuestyProperty records ({$allGuesty->count()} total)");

        $created = 0;
        $skipped = 0;

        $properties = GuestyProperty::where('active', 1)->get();
        $allGuesty  = GuestyProperty::get(['_id', 'seo_url', 'nickname']);
        $this->info("  Found {$properties->count()} active GuestyProperty records ({$allGuesty->count()} total)");

        $created = 0;
        $skipped = 0;

        foreach ($properties as $property) {
            $guestyId = $property->_id;
            if (!$guestyId) {
                continue;
            }

            $existing = DB::table('pms_rooms')
                ->where('pms_room_id', $guestyId)
                ->where('pms_source', 'guesty')
                ->first();

            if ($existing) {
                $skipped++;
                continue;
            }

            $this->line("  Creating pms_room for property: {$guestyId} ({$property->nickname})");

            if (!$dry) {
                DB::table('pms_rooms')->insert([
                    'pms_room_id'      => $guestyId,
                    'pms_property_id'  => $guestyId,
                    'pms_source'       => 'guesty',
                    'name'             => $property->nickname ?? $property->title ?? $guestyId,
                    // Keep the SAME public URL the old property page used, so the
                    // PMS intercept matches and no SEO links break.
                    'seo_url'          => $property->seo_url ?: null,
                    'accommodates'     => $property->accommodates ?? null,
                    'bedrooms'         => $property->bedrooms ?? null,
                    'bathrooms'        => $property->bathrooms ?? null,
                    'min_stay'         => $property->terms_min_night ?? 1,
                    'active'           => 1,
                    // GOTCHA: pms_rooms.status is a VARCHAR using 'true'/'false'.
                    // The front-end filters where('status','true') — writing 1/0
                    // silently hides the room from guests.
                    'status'           => 'true',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
            $created++;
        }

        // 5. Clean ugly slugs EVERYWHERE: any slug carrying the raw PMS id
        // suffix (24-hex tail) or non-ASCII chars becomes a clean name-based
        // URL. Original site slugs (no id tail, plain ASCII) are never touched.
        foreach (DB::table('pms_rooms')->get() as $row) {
            $slug = (string) $row->seo_url;
            $ugly = $slug === ''
                || preg_match('/[^\x20-\x7E]/', $slug)
                || preg_match('/-[0-9a-f]{24}$/i', $slug);
            if (!$ugly) continue;
            $base = \Illuminate\Support\Str::slug($row->name ?: 'listing') ?: 'listing-' . $row->id;
            $clean = $base; $i = 2;
            while (DB::table('pms_rooms')->where('seo_url', $clean)->where('id', '!=', $row->id)->exists()
                || DB::table('guesty_properties')->where('seo_url', $clean)->where('_id', '!=', $row->pms_room_id)->exists()) {
                $clean = $base . '-' . $i++;
            }
            if ($clean !== $slug && !$dry) {
                DB::table('pms_rooms')->where('id', $row->id)->update(['seo_url' => $clean]);
            }
            $this->line("  Cleaned slug for {$row->name}: {$clean}");
        }

        $this->info("  pms_rooms: {$created} created, {$skipped} already existed");
        $this->info($dry ? '[DRY-RUN] pms:install complete (no changes made)' : 'pms:install complete');

        return 0;
    }
}
