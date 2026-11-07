<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('cms', function (Blueprint $table) {
            $cols = array_column(Schema::getColumns('cms'), 'name');
            if (!in_array('hero_eyebrow', $cols))     $table->string('hero_eyebrow')->nullable()->after('gallery_heading');
            if (!in_array('hero_heading', $cols))     $table->string('hero_heading')->nullable()->after('hero_eyebrow');
            if (!in_array('hero_sub', $cols))         $table->string('hero_sub')->nullable()->after('hero_heading');
            if (!in_array('story_eyebrow', $cols))    $table->string('story_eyebrow')->nullable()->after('hero_sub');
            if (!in_array('story_title', $cols))      $table->string('story_title')->nullable()->after('story_eyebrow');
            if (!in_array('story_body_2', $cols))     $table->text('story_body_2')->nullable()->after('story_title');
            if (!in_array('retreats_eyebrow', $cols)) $table->string('retreats_eyebrow')->nullable()->after('story_body_2');
            if (!in_array('retreats_title', $cols))   $table->string('retreats_title')->nullable()->after('retreats_eyebrow');
            if (!in_array('better_title', $cols))     $table->string('better_title')->nullable()->after('retreats_title');
            if (!in_array('better_sub', $cols))       $table->string('better_sub')->nullable()->after('better_title');
            if (!in_array('explore_eyebrow', $cols))  $table->string('explore_eyebrow')->nullable()->after('better_sub');
            if (!in_array('explore_title', $cols))    $table->string('explore_title')->nullable()->after('explore_eyebrow');
            if (!in_array('explore_sub', $cols))      $table->text('explore_sub')->nullable()->after('explore_title');
            if (!in_array('explore_map_url', $cols))  $table->string('explore_map_url')->nullable()->after('explore_sub');
            if (!in_array('faq_eyebrow', $cols))      $table->string('faq_eyebrow')->nullable()->after('explore_map_url');
            if (!in_array('faq_title', $cols))        $table->string('faq_title')->nullable()->after('faq_eyebrow');
            if (!in_array('whatsapp_url', $cols))     $table->string('whatsapp_url')->nullable()->after('faq_title');
        });
    }
    public function down() {
        Schema::table('cms', function (Blueprint $table) {
            $table->dropColumn([
                'hero_eyebrow','hero_heading','hero_sub',
                'story_eyebrow','story_title','story_body_2',
                'retreats_eyebrow','retreats_title',
                'better_title','better_sub',
                'explore_eyebrow','explore_title','explore_sub','explore_map_url',
                'faq_eyebrow','faq_title','whatsapp_url',
            ]);
        });
    }
};
