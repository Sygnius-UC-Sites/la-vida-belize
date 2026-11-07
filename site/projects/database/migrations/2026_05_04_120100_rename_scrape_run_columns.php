<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('scrape_runs', function (Blueprint $t) {
            $t->renameColumn('apify_run_id', 'provider_run_id');
            $t->renameColumn('apify_actor_id', 'provider_actor_id');
            $t->renameColumn('apify_dataset_id', 'provider_dataset_id');
        });
    }

    public function down(): void
    {
        Schema::table('scrape_runs', function (Blueprint $t) {
            $t->renameColumn('provider_run_id', 'apify_run_id');
            $t->renameColumn('provider_actor_id', 'apify_actor_id');
            $t->renameColumn('provider_dataset_id', 'apify_dataset_id');
        });
    }
};
