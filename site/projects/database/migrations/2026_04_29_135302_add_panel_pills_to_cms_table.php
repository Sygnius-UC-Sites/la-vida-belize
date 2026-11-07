<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cms', function (Blueprint $table) {
            if (!Schema::hasColumn('cms', 'rooftop_features')) {
                $table->text('rooftop_features')->nullable();
            }
            if (!Schema::hasColumn('cms', 'living_pills')) {
                $table->text('living_pills')->nullable();
            }
            if (!Schema::hasColumn('cms', 'beach_pills')) {
                $table->text('beach_pills')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cms', function (Blueprint $table) {
            $table->dropColumn(['rooftop_features', 'living_pills', 'beach_pills']);
        });
    }
};
