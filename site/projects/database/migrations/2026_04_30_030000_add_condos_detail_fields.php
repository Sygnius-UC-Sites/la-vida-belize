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
                ['cd_category_heading', 'text'],
                ['cd_meta_rating', 'text'],
                ['cd_meta_location', 'text'],
                ['cd_gallery_images', 'longText'],
                ['cd_host_heading', 'text'],
                ['cd_host_meta', 'text'],
                ['cd_highlights', 'longText'],
                ['cd_about_heading', 'text'],
                ['cd_about_text', 'text'],
                ['cd_sleep_heading', 'text'],
                ['cd_sleep_cards', 'longText'],
                ['cd_amenities_heading', 'text'],
                ['cd_amenities_list', 'longText'],
                ['cd_booking_price', 'text'],
                ['cd_reviews_stats', 'text'],
                ['cd_reviews_list', 'longText'],
                ['cd_reviews_show_all', 'text'],
                ['cd_offcanvas_amenities', 'longText'],
                ['cd_offcanvas_reviews_heading', 'text'],
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
            foreach (['cd_category_heading','cd_meta_rating','cd_meta_location','cd_gallery_images','cd_host_heading','cd_host_meta','cd_highlights','cd_about_heading','cd_about_text','cd_sleep_heading','cd_sleep_cards','cd_amenities_heading','cd_amenities_list','cd_booking_price','cd_reviews_stats','cd_reviews_list','cd_reviews_show_all','cd_offcanvas_amenities','cd_offcanvas_reviews_heading'] as $c) {
                if (Schema::hasColumn('cms', $c)) $table->dropColumn($c);
            }
        });
    }
};
