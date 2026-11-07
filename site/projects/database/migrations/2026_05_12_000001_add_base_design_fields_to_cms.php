<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base frontend design fields on `cms`.
 *
 * Existing migrations already added the structural columns we need for the
 * shared vacation-rental layout (hero_heading, story_title, retreats_*, better_*, explore_*,
 * faq_*, story_body, story_image, etc.). This migration tops them up with the
 * couple of JSON / text fields the new design surface needs that weren't
 * already present:
 *
 *   - hero_carousel_images  (JSON list of image paths for the hero slideshow)
 *   - better_items          (JSON list of 4 value-prop cards)
 *   - gallery_eyebrow       (string heading eyebrow for the /gallery page)
 *   - gallery_title         (string heading for the /gallery page)
 *   - elfsight_widget_id    (string – Elfsight reviews widget App ID, e.g. "fa9fc250-1d25-4935-800e-8c8581a28e3e")
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cms', function (Blueprint $table) {
            $existing = array_column(Schema::getColumns('cms'), 'name');
            // The `cms` table already has 100+ VARCHAR columns from prior
            // client migrations, so InnoDB's 65535-byte row-size cap is tight.
            // We use `text` for the short fields too — TEXT lives in off-row
            // storage and doesn't count toward the limit.
            $columns = [
                ['hero_carousel_images', 'longText'],
                ['better_items',         'longText'],
                ['gallery_eyebrow',      'text'],
                ['gallery_title',        'text'],
                ['elfsight_widget_id',   'text'],
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
            $cols = ['hero_carousel_images', 'better_items', 'gallery_eyebrow', 'gallery_title', 'elfsight_widget_id'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('cms', $c)) $table->dropColumn($c);
            }
        });
    }
};
