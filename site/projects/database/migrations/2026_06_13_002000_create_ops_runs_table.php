<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ops_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_type', 64)->index();
            $table->string('source', 32)->default('system')->index();
            $table->string('provider', 32)->nullable()->index();
            $table->string('event', 128)->nullable()->index();
            $table->string('command', 255)->nullable()->index();
            $table->string('status', 24)->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('summary', 500)->nullable();
            $table->longText('output')->nullable();
            $table->longText('error')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['run_type', 'status', 'created_at'], 'idx_ops_runs_type_status_created');
            $table->index(['provider', 'event', 'created_at'], 'idx_ops_runs_provider_event_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_runs');
    }
};
