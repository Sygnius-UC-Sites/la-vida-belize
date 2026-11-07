<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('booking_requests', function (Blueprint $t) {
            // Track each email's attempt timestamp + error message (if any).
            // _sent_at columns already exist for welcome / checkin / checkout / review.
            // _attempted_at fires whether or not the send succeeded; _error holds
            // the exception message on failure (NULL on success or never tried).
            foreach (['welcome', 'checkin', 'checkout', 'review'] as $email) {
                if (!Schema::hasColumn('booking_requests', $email . '_email_attempted_at')) {
                    $t->timestamp($email . '_email_attempted_at')->nullable()->after($email . '_email_sent_at');
                }
                if (!Schema::hasColumn('booking_requests', $email . '_email_error')) {
                    $t->text($email . '_email_error')->nullable()->after($email . '_email_attempted_at');
                }
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Phase 13I column additions are not reversible from migration. Restore from a SQL backup if needed.');
    }
};
