<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $properties = DB::table('properties')->get();

        foreach ($properties as $prop) {
            $this->seedGallery($prop);
            $this->seedOffcanvasAmenities($prop);
            $this->seedHighlights($prop);
            $this->seedSleepCards($prop);
            $this->seedAmenityHighlights($prop);
        }
    }

    public function down(): void {
        // Forward-only. Restore from the pre-flight backup if needed.
    }

    private function seedGallery($prop): void {
        if (DB::table('property_galleries')->where('property_id', $prop->id)->exists()) return;
        $items = json_decode($prop->detail_gallery_images ?? '[]', true) ?: [];
        foreach ($items as $i => $g) {
            DB::table('property_galleries')->insert([
                'property_id' => $prop->id,
                'image'       => $g['src']   ?? '',
                'caption'     => $g['alt']   ?? '',
                'sorting'     => $i,
                'status'      => 'true',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    private function seedOffcanvasAmenities($prop): void {
        if (DB::table('property_amenity_groups')->where('property_id', $prop->id)->exists()) return;
        $groups = json_decode($prop->detail_offcanvas_amenities ?? '[]', true) ?: [];
        foreach ($groups as $gi => $group) {
            $groupId = DB::table('property_amenity_groups')->insertGetId([
                'property_id' => $prop->id,
                'name'        => $group['heading'] ?? '',
                'image'       => '',
                'sorting'     => $gi,
                'status'      => 'true',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            $items = $group['items'] ?? [];
            foreach ($items as $ii => $item) {
                DB::table('property_amenities')->insert([
                    'property_amenity_id' => $groupId,
                    'name'                => is_string($item) ? strip_tags($item) : '',
                    'image'               => '',
                    'sorting'             => $ii,
                    'status'              => 'true',
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }
        }
    }

    private function seedHighlights($prop): void {
        if (DB::table('property_highlights')->where('property_id', $prop->id)->exists()) return;
        $items = json_decode($prop->detail_highlights ?? '[]', true) ?: [];
        foreach ($items as $i => $h) {
            DB::table('property_highlights')->insert([
                'property_id' => $prop->id,
                'icon'        => $h['icon']  ?? '',
                'title'       => $h['title'] ?? '',
                'description' => $h['desc']  ?? '',
                'sorting'     => $i,
                'status'      => 'true',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    private function seedSleepCards($prop): void {
        if (DB::table('property_sleep_cards')->where('property_id', $prop->id)->exists()) return;
        $items = json_decode($prop->detail_sleep_cards ?? '[]', true) ?: [];
        foreach ($items as $i => $s) {
            DB::table('property_sleep_cards')->insert([
                'property_id' => $prop->id,
                'title'       => $s['title'] ?? '',
                'text'        => $s['text']  ?? '',
                'sorting'     => $i,
                'status'      => 'true',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    private function seedAmenityHighlights($prop): void {
        if (DB::table('property_amenity_highlights')->where('property_id', $prop->id)->exists()) return;
        $items = json_decode($prop->detail_amenities_list ?? '[]', true) ?: [];
        foreach ($items as $i => $a) {
            DB::table('property_amenity_highlights')->insert([
                'property_id' => $prop->id,
                'emoji'       => $a['emoji'] ?? '',
                'label'       => $a['label'] ?? '',
                'sorting'     => $i,
                'status'      => 'true',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
};
