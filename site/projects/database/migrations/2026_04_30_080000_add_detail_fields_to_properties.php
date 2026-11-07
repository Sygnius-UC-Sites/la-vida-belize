<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('properties', function (Blueprint $table) {
            $stringCols = [
                'detail_category_heading','detail_meta_rating','detail_meta_location',
                'detail_host_heading','detail_about_heading','detail_sleep_heading',
                'detail_amenities_heading','detail_offcanvas_reviews_heading',
                'detail_share_label','detail_booking_disclaimer','detail_map_heading',
            ];
            foreach ($stringCols as $c) {
                if (!Schema::hasColumn('properties', $c)) $table->string($c)->nullable();
            }
            $textCols = ['detail_host_meta'];
            foreach ($textCols as $c) {
                if (!Schema::hasColumn('properties', $c)) $table->text($c)->nullable();
            }
            $longTextCols = [
                'detail_gallery_images','detail_highlights','detail_about_text',
                'detail_sleep_cards','detail_amenities_list','detail_offcanvas_amenities',
                'detail_map_iframe_src',
            ];
            foreach ($longTextCols as $c) {
                if (!Schema::hasColumn('properties', $c)) $table->longText($c)->nullable();
            }
        });
    }

    public function down(): void {
        Schema::table('properties', function (Blueprint $table) {
            $cols = [
                'detail_category_heading','detail_meta_rating','detail_meta_location',
                'detail_gallery_images','detail_host_heading','detail_host_meta',
                'detail_highlights','detail_about_heading','detail_about_text',
                'detail_sleep_heading','detail_sleep_cards','detail_amenities_heading',
                'detail_amenities_list','detail_offcanvas_amenities','detail_offcanvas_reviews_heading',
                'detail_share_label','detail_booking_disclaimer','detail_map_heading','detail_map_iframe_src',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('properties', $c)) $table->dropColumn($c);
            }
        });
    }
};
