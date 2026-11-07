<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms')) {
            return;
        }

        Schema::table('cms', function (Blueprint $table) {
            foreach ([
                'hero_carousel_images',
                'hero_badges',
                'better_items',
                'gallery_cards',
                'home_gallery_images',
            ] as $column) {
                if (!Schema::hasColumn('cms', $column)) {
                    $table->longText($column)->nullable();
                }
            }

            foreach ([
                'gallery_eyebrow',
                'gallery_title',
                'elfsight_widget_id',
                'story_image_accent',
                'inner_banner_image',
            ] as $column) {
                if (!Schema::hasColumn('cms', $column)) {
                    $table->text($column)->nullable();
                }
            }
        });

        $placeholder = 'front/themes/base/img/placeholder.svg';

        DB::table('cms')->update([
            'hero_carousel_images' => json_encode(['/' . $placeholder]),
            'hero_badges' => json_encode([]),
            'gallery_cards' => json_encode([]),
            'home_gallery_images' => json_encode([]),
            'story_image_accent' => $placeholder,
            'inner_banner_image' => $placeholder,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No-op: these are baseline CMS editor fields used by the clone templates.
    }
};
