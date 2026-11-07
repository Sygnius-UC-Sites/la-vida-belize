<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('property_highlights')) {
            Schema::create('property_highlights', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('property_id')->index();
                $t->string('icon')->nullable();
                $t->string('title')->nullable();
                $t->text('description')->nullable();
                $t->integer('sorting')->default(0);
                $t->string('status')->default('true');
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('property_sleep_cards')) {
            Schema::create('property_sleep_cards', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('property_id')->index();
                $t->string('title')->nullable();
                $t->text('text')->nullable();
                $t->integer('sorting')->default(0);
                $t->string('status')->default('true');
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('property_amenity_highlights')) {
            Schema::create('property_amenity_highlights', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('property_id')->index();
                $t->string('emoji')->nullable();
                $t->string('label')->nullable();
                $t->integer('sorting')->default(0);
                $t->string('status')->default('true');
                $t->timestamps();
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('property_amenity_highlights');
        Schema::dropIfExists('property_sleep_cards');
        Schema::dropIfExists('property_highlights');
    }
};
