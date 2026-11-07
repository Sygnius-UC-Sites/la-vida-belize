<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    private array $cdToDetail = [
        'cd_category_heading'           => 'detail_category_heading',
        'cd_meta_rating'                => 'detail_meta_rating',
        'cd_meta_location'              => 'detail_meta_location',
        'cd_gallery_images'             => 'detail_gallery_images',
        'cd_host_heading'               => 'detail_host_heading',
        'cd_host_meta'                  => 'detail_host_meta',
        'cd_highlights'                 => 'detail_highlights',
        'cd_about_heading'              => 'detail_about_heading',
        'cd_about_text'                 => 'detail_about_text',
        'cd_sleep_heading'              => 'detail_sleep_heading',
        'cd_sleep_cards'                => 'detail_sleep_cards',
        'cd_amenities_heading'          => 'detail_amenities_heading',
        'cd_amenities_list'             => 'detail_amenities_list',
        'cd_offcanvas_amenities'        => 'detail_offcanvas_amenities',
        'cd_offcanvas_reviews_heading'  => 'detail_offcanvas_reviews_heading',
        'cd_share_label'                => 'detail_share_label',
        'cd_booking_disclaimer'         => 'detail_booking_disclaimer',
        'cd_map_heading'                => 'detail_map_heading',
        'cd_map_iframe_src'             => 'detail_map_iframe_src',
    ];
    // cd_reviews_list, cd_reviews_stats, cd_reviews_show_all, cd_booking_price intentionally NOT copied.

    public function up(): void {
        $this->seedDetailFor('condos-detail', 'condo');
        $this->seedDetailFor('house-detail', 'house');
        $this->dropCmsDetailRows();
        $this->dropCdColumns();
    }

    private function seedDetailFor(string $cmsSeo, string $propType): void {
        $cmsRow = DB::table('cms')->where('seo_url', $cmsSeo)->first();
        if (!$cmsRow) return;
        $properties = DB::table('properties')->where('type', $propType)->get();

        foreach ($properties as $prop) {
            $update = [];
            foreach ($this->cdToDetail as $cd => $detail) {
                if (property_exists($cmsRow, $cd)) {
                    $update[$detail] = $cmsRow->$cd;
                }
            }
            if (!empty($update)) {
                DB::table('properties')->where('id', $prop->id)->update($update);
            }

            $reviewsJson = $cmsRow->cd_reviews_list ?? null;
            if ($reviewsJson) {
                $reviews = json_decode($reviewsJson, true) ?: [];
                foreach ($reviews as $r) {
                    $exists = DB::table('testimonials')
                        ->where('property_id', $prop->id)
                        ->where('name', $r['name'] ?? '')
                        ->where('message', $r['text'] ?? '')
                        ->exists();
                    if (!$exists) {
                        DB::table('testimonials')->insert([
                            'property_id' => $prop->id,
                            'name'        => $r['name']  ?? '',
                            'message'     => $r['text']  ?? '',
                            'stay_date'   => $r['date']  ?? '',
                            'score'       => null,
                            'is_home'     => 'false',
                            'status'      => 'true',
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);
                    }
                }
            }
        }
    }

    private function dropCmsDetailRows(): void {
        DB::table('cms')->whereIn('seo_url', ['condos-detail','house-detail'])->delete();
    }

    private function dropCdColumns(): void {
        $cdCols = array_keys($this->cdToDetail);
        $cdCols[] = 'cd_reviews_list';
        $cdCols[] = 'cd_reviews_stats';
        $cdCols[] = 'cd_reviews_show_all';
        $cdCols[] = 'cd_booking_price';
        Schema::table('cms', function (Blueprint $t) use ($cdCols) {
            foreach ($cdCols as $c) {
                if (Schema::hasColumn('cms', $c)) $t->dropColumn($c);
            }
        });
    }

    public function down(): void {
        // Forward-only. Restore from the pre-flight DB backup if needed.
    }
};
