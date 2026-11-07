<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('property_reviews', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('property_id')->index();
            $t->string('source', 32)->default('airbnb');     // airbnb | vrbo | booking | manual
            $t->string('external_review_id', 128)->nullable();
            // 40-char SHA-1 of (reviewer_name + comment) used as a dedup fallback
            // when the upstream Actor doesn't expose a stable review id.
            $t->char('content_hash', 40)->nullable();
            $t->string('reviewer_name', 255)->nullable();
            $t->string('reviewer_photo_url', 1024)->nullable();
            $t->string('reviewer_photo_local', 500)->nullable();
            $t->string('reviewer_location', 255)->nullable();
            $t->unsignedTinyInteger('rating')->nullable();   // 1-5
            $t->longText('comment')->nullable();
            $t->longText('comment_translated')->nullable();
            $t->string('language', 8)->nullable();
            $t->longText('response_text')->nullable();
            $t->timestamp('response_date')->nullable();
            $t->timestamp('review_date')->nullable();
            $t->string('status', 20)->default('imported');   // imported | published | hidden
            $t->longText('raw_payload')->nullable();
            $t->timestamps();

            $t->index(['property_id', 'status', 'review_date'], 'idx_property_status_date');
            $t->index(['source', 'external_review_id'],         'idx_source_external');
            $t->index(['property_id', 'content_hash'],          'idx_property_content_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_reviews');
    }
};
