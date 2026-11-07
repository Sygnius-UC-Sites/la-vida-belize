<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pms_rooms') && !Schema::hasColumn('pms_rooms', 'max_pets')) {
            Schema::table('pms_rooms', function (Blueprint $table) {
                $table->unsignedSmallInteger('max_pets')->nullable()->after('pets_allowed');
            });
        }

        if (!Schema::hasTable('property_categories')) {
            Schema::create('property_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('status')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('property_category_property')) {
            Schema::create('property_category_property', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_category_id')
                    ->constrained('property_categories')
                    ->cascadeOnDelete();
                $table->foreignId('property_id')
                    ->constrained('properties')
                    ->cascadeOnDelete();
                $table->timestamps();
                $table->unique(
                    ['property_category_id', 'property_id'],
                    'property_category_property_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('property_category_property');
        Schema::dropIfExists('property_categories');

        if (Schema::hasTable('pms_rooms') && Schema::hasColumn('pms_rooms', 'max_pets')) {
            Schema::table('pms_rooms', function (Blueprint $table) {
                $table->dropColumn('max_pets');
            });
        }
    }
};
