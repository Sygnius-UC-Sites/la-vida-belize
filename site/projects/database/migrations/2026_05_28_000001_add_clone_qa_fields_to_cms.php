<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clone QA round — additional CMS columns surfaced from frontend feedback.
 *
 *   - story_image_accent   : 2nd image in the Our Story section (frontend
 *                            already renders two images; admin previously
 *                            had no way to upload the second one).
 *   - home_gallery_images  : JSON list of image paths the admin curates for
 *                            the homepage Gallery preview strip. When empty,
 *                            the homepage falls back to aggregated
 *                            PropertyGallery rows.
 *   - inner_banner_image   : per-CMS-page banner image override (FAQ,
 *                            Contact, Attractions, Testimonial pages).
 *                            Currently `bannerImage` exists on `cms` and is
 *                            shared with image upload but never reached the
 *                            inner-page banner partial — this is a clean
 *                            named field for that purpose with admin UI.
 *
 * Per the project's row-size cap on `cms`, use TEXT for short strings so
 * they live in off-row storage.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cms', function (Blueprint $table) {
            $existing = array_column(Schema::getColumns('cms'), 'name');
            $columns = [
                ['story_image_accent',  'text'],
                ['home_gallery_images', 'longText'],
                ['inner_banner_image',  'text'],
            ];
            foreach ($columns as [$name, $type]) {
                if (!in_array($name, $existing)) {
                    $table->{$type}($name)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('cms', function (Blueprint $table) {
            foreach (['story_image_accent', 'home_gallery_images', 'inner_banner_image'] as $c) {
                if (Schema::hasColumn('cms', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
