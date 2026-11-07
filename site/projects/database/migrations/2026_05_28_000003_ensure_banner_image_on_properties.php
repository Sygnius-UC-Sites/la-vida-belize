<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Defensive migration — ensure `properties.banner_image` exists.
 *
 * The Property model has `banner_image` in $fillable, the admin form has a
 * file-upload field for it, and the property-detail frontend reads it for
 * the inner banner. However the original 2022 create-table
 * migration never added this column — historic deployments have it because
 * it was added manually or via an out-of-tree migration. A fresh re-run of
 * `php artisan migrate` against a clean DB would have no `banner_image`
 * column at all and the admin save + frontend read would silently no-op.
 *
 * This migration adds the column only if missing. Safe to run repeatedly.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('properties', 'banner_image')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->string('banner_image')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Intentionally no-op. Dropping the column would lose admin-uploaded
        // banners on any existing deployment.
    }
};
