<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::table('cms')->where('seo_url','home')->update([
            'condos_cta_url'        => '/condos',
            'houses_card_cta_url'   => '/houses',
            'story_cta_url'         => '/our-story',
            'amenities_cta1_url'    => '/booking',
            'amenities_cta2_url'    => '/booking',
            'location_cta_url'      => '/contact-us',
            'cta_section_cta1_url'  => '/booking',
        ]);

        DB::table('cms')->where('seo_url','houses')->update([
            'cta_section_cta1_url'  => '/booking?type=house',
            'cta_section_cta2_url'  => '/contact-us',
        ]);

        DB::table('cms')->where('seo_url','our-story')->update([
            'cta_section_cta1_url'  => '/booking',
            'cta_section_cta2_url'  => '/contact-us',
        ]);
    }

    public function down(): void {
        // Forward-only. Restore from the pre-flight backup if needed.
    }
};
