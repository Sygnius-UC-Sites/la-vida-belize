<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scrape_sources', function (Blueprint $t) {
            $t->id();
            $t->string('source_url', 1024);
            $t->string('platform', 32)->default('airbnb');     // airbnb | vrbo | booking | other
            $t->unsignedBigInteger('property_id')->nullable()->index();
            $t->string('status', 32)->default('pending');      // pending | scraping | imported | failed
            $t->text('notes')->nullable();
            $t->timestamp('last_scraped_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();

            $t->index(['platform', 'status'], 'idx_platform_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_sources');
    }
};
