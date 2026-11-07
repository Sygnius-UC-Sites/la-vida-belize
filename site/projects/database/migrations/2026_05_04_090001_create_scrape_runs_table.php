<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scrape_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('scrape_source_id')->constrained()->cascadeOnDelete();
            $t->string('apify_run_id', 64)->nullable()->unique();
            $t->string('apify_actor_id', 128);
            $t->string('apify_dataset_id', 64)->nullable();
            $t->string('status', 32)->default('queued');      // queued | RUNNING | SUCCEEDED | FAILED | TIMED-OUT | ABORTED
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->longText('raw_payload')->nullable();           // first dataset item, JSON
            $t->text('error_message')->nullable();
            $t->timestamps();

            $t->index(['status', 'created_at'], 'idx_run_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_runs');
    }
};
