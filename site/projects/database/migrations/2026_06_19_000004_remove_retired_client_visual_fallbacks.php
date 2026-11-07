<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $placeholder = 'front/themes/base/img/placeholder.svg';

    public function up(): void
    {
        $this->cleanCmsRows();
        $this->cleanSettings();
    }

    public function down(): void
    {
        // No-op: this cleanup intentionally does not restore retired client assets.
    }

    private function cleanCmsRows(): void
    {
        if (!Schema::hasTable('cms')) {
            return;
        }

        $cmsCols = collect(Schema::getColumnListing('cms'))->flip();

        $updates = [];
        foreach (['inner_banner_image', 'story_image', 'story_image_accent', 'story_block_image'] as $col) {
            if ($cmsCols->has($col)) {
                $updates[$col] = $this->placeholder;
            }
        }

        if ($cmsCols->has('hero_carousel_images')) {
            $updates['hero_carousel_images'] = json_encode(['/' . $this->placeholder]);
        }
        if ($cmsCols->has('hero_badges')) {
            $updates['hero_badges'] = json_encode([]);
        }
        if ($cmsCols->has('gallery_cards')) {
            $updates['gallery_cards'] = json_encode([]);
        }
        if ($cmsCols->has('home_gallery_images')) {
            $updates['home_gallery_images'] = json_encode([]);
        }
        if ($cmsCols->has('better_title')) {
            $updates['better_title'] = 'Book Direct Benefits';
        }
        if ($cmsCols->has('explore_map_url')) {
            $updates['explore_map_url'] = 'https://maps.google.com/?q=Your+Destination';
        }

        if (!empty($updates)) {
            DB::table('cms')->update($updates + ['updated_at' => now()]);
        }
    }

    private function cleanSettings(): void
    {
        if (!Schema::hasTable('basic_settings')) {
            return;
        }

        $settings = [
            'default_header_image' => $this->placeholder,
            'map' => 'https://www.google.com/maps?q=Your+Destination&output=embed',
            'airbnb_url' => '',
            'airbnb_booking_url' => '',
        ];

        foreach ($settings as $name => $value) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
};
