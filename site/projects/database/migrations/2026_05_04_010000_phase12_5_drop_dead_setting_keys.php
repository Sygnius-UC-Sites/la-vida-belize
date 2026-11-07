<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const DEAD_KEYS = [
        'linedin',
        'pricelab_access_token',
        'igms_access_token',
        'hosttools_access_token',
        'google_captcha_access_token',
    ];

    public function up(): void
    {
        DB::table('basic_settings')->whereIn('name', self::DEAD_KEYS)->delete();
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Phase 12.5 row deletes are not reversible from migration. '
            . 'Restore from projects/storage/backups/pre_phase12_5_*.sql.'
        );
    }
};
