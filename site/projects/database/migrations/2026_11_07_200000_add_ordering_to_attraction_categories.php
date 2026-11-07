<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attraction_categories') && !Schema::hasColumn('attraction_categories', 'ordering')) {
            Schema::table('attraction_categories', function (Blueprint $table) {
                $table->integer('ordering')->default(0)->after('publish');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attraction_categories') && Schema::hasColumn('attraction_categories', 'ordering')) {
            Schema::table('attraction_categories', function (Blueprint $table) {
                $table->dropColumn('ordering');
            });
        }
    }
};
