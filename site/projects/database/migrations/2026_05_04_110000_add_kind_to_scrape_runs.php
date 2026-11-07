<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('scrape_runs', function (Blueprint $t) {
            // 'listing' (property data scrape) | 'reviews' (separate review scrape).
            // Listing actor + reviews actor are different on Apify; one source can
            // have both run types. Existing rows are listings.
            $t->string('kind', 16)->default('listing')->after('apify_actor_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('scrape_runs', function (Blueprint $t) {
            $t->dropColumn('kind');
        });
    }
};
