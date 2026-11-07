<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Fold legacy 'booking-confirmed' into canonical 'booked'.
        // Logged for audit (Plan C cron migration).
        $affected = DB::table('booking_requests')
            ->where('booking_status', 'booking-confirmed')
            ->update(['booking_status' => 'booked', 'updated_at' => now()]);

        if (function_exists('logger')) {
            logger()->info("Status canonicalization: updated {$affected} booking_requests rows from 'booking-confirmed' to 'booked'");
        }
    }

    public function down(): void
    {
        // Reversibility is destructive (we'd lose the canonicalization info).
        // No-op: if rolling back, restore from the pre_status_canon_*.sql snapshot taken before migration.
    }
};
