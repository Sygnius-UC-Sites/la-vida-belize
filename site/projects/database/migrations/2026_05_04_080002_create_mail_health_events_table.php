<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Lightweight rolling-window metrics for HealthMonitor. One row per
        // delivery/bounce/complaint event, aggregated via SQL over the last 24h.
        Schema::create('mail_health_events', function (Blueprint $t) {
            $t->id();
            $t->string('event_type', 16)->index();          // delivery | bounce | complaint
            $t->string('email', 255)->nullable();
            $t->string('ses_message_id', 255)->nullable()->index();
            $t->timestamp('occurred_at')->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_health_events');
    }
};
