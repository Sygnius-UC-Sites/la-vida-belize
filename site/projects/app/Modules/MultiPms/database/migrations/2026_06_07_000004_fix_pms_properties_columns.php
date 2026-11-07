<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('pms_properties', 'latitude'))
                $table->decimal('latitude', 10, 7)->nullable();
            if (!Schema::hasColumn('pms_properties', 'longitude'))
                $table->decimal('longitude', 10, 7)->nullable();
            if (!Schema::hasColumn('pms_properties', 'meta_title'))
                $table->string('meta_title')->nullable();
            if (!Schema::hasColumn('pms_properties', 'meta_keywords'))
                $table->string('meta_keywords')->nullable();
            if (!Schema::hasColumn('pms_properties', 'meta_description'))
                $table->text('meta_description')->nullable();
        });
    }

    public function down(): void {}
};
