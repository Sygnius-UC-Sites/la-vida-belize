<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('cms', function (Blueprint $table) {
            $existing = array_column(Schema::getColumns('cms'), 'name');
            $columns = [
                ['hero_heading_gold', 'text'],
                ['hero_video_src', 'text'],
                ['hero_cta1_label', 'text'],
                ['hero_cta1_url', 'text'],
                ['hero_cta2_label', 'text'],
                ['hero_cta2_url', 'text'],
                ['condos_heading', 'text'],
                ['condos_cta_label', 'text'],
                ['condos_cta_url', 'text'],
                ['condo_tabs', 'longText'],
                ['houses_heading', 'text'],
                ['houses_card_eyebrow', 'text'],
                ['houses_card_heading', 'text'],
                ['houses_card_desc', 'text'],
                ['houses_card_tags', 'longText'],
                ['houses_card_cta_label', 'text'],
                ['houses_card_cta_url', 'text'],
                ['experiences_eyebrow', 'text'],
                ['experiences_heading', 'text'],
                ['experiences_desc', 'text'],
                ['experiences_cta_label', 'text'],
                ['experiences_cta_url', 'text'],
                ['story_image', 'text'],
                ['story_body', 'longText'],
                ['story_cta_label', 'text'],
                ['story_cta_url', 'text'],
                ['amenities_heading', 'text'],
                ['amenities_list', 'longText'],
                ['amenities_cta1_label', 'text'],
                ['amenities_cta1_url', 'text'],
                ['amenities_cta2_label', 'text'],
                ['amenities_cta2_url', 'text'],
                ['location_heading', 'text'],
                ['location_body', 'text'],
                ['location_distances', 'longText'],
                ['location_cta_label', 'text'],
                ['location_cta_url', 'text'],
                ['location_image', 'text'],
                ['location_caption_eyebrow', 'text'],
                ['location_caption_text', 'text'],
                ['testimonials_heading', 'text'],
                ['cta_section_heading', 'text'],
                ['cta_section_body', 'text'],
                ['cta_section_cta1_label', 'text'],
                ['cta_section_cta1_url', 'text'],
                ['cta_section_cta2_label', 'text'],
                ['cta_section_cta2_url', 'text'],
                ['cta_section_response_note', 'text'],
            ];

            foreach ($columns as [$name, $type]) {
                if (!in_array($name, $existing)) {
                    $table->{$type}($name)->nullable();
                }
            }
        });
    }

    public function down()
    {
        Schema::table('cms', function (Blueprint $table) {
            $cols = [
                'hero_heading_gold','hero_video_src','hero_cta1_label','hero_cta1_url','hero_cta2_label','hero_cta2_url',
                'condos_heading','condos_cta_label','condos_cta_url','condo_tabs',
                'houses_heading','houses_card_eyebrow','houses_card_heading','houses_card_desc','houses_card_tags','houses_card_cta_label','houses_card_cta_url',
                'experiences_eyebrow','experiences_heading','experiences_desc','experiences_cta_label','experiences_cta_url',
                'story_image','story_body','story_cta_label','story_cta_url',
                'amenities_heading','amenities_list','amenities_cta1_label','amenities_cta1_url','amenities_cta2_label','amenities_cta2_url',
                'location_heading','location_body','location_distances','location_cta_label','location_cta_url','location_image','location_caption_eyebrow','location_caption_text',
                'testimonials_heading','cta_section_heading','cta_section_body','cta_section_cta1_label','cta_section_cta1_url','cta_section_cta2_label','cta_section_cta2_url','cta_section_response_note',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('cms', $c)) $table->dropColumn($c);
            }
        });
    }
};
