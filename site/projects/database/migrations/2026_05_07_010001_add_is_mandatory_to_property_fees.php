<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('property_fees', function (Blueprint $table) {
            if (!Schema::hasColumn('property_fees', 'is_mandatory')) {
                $table->boolean('is_mandatory')->default(false)->after('applies_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('property_fees', function (Blueprint $table) {
            if (Schema::hasColumn('property_fees', 'is_mandatory')) {
                $table->dropColumn('is_mandatory');
            }
        });
    }
};
