<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $default = json_encode([
            ['type' => 'link',     'label' => 'Home',       'url' => '/'],
            ['type' => 'dropdown', 'label' => 'About',       'children' => [
                ['label' => 'About Property', 'url' => 'about-us'],
                ['label' => 'About Owner',    'url' => 'about-owner'],
            ]],
            ['type' => 'property'],
            ['type' => 'link', 'label' => 'Attractions', 'url' => 'attractions'],
            ['type' => 'link', 'label' => 'FAQs',        'url' => 'faqs'],
            ['type' => 'link', 'label' => 'Contact',     'url' => 'contact-us'],
        ]);

        $exists = DB::table('basic_settings')->where('name', 'nav_menu')->exists();
        if (!$exists) {
            DB::table('basic_settings')->insert([
                'name'       => 'nav_menu',
                'value'      => $default,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('basic_settings')->where('name', 'nav_menu')->delete();
    }
};
