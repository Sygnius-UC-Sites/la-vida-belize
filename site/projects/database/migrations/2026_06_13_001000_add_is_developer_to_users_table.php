<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'is_developer')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_developer')->default(false)->after('password');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_developer')) {
            DB::table('users')
                ->whereIn('email', ['steven@webdesignvr.com'])
                ->update(['is_developer' => true, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_developer')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_developer');
            });
        }
    }
};
