<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateFaqCategoriesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('faq_categories')) {
            Schema::create('faq_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('seo_url')->unique();
                $table->integer('ordering')->default(0);
                $table->string('publish')->default('published');
                $table->timestamps();
            });

            // Seed the categories already in use by the front FAQ filter tabs so
            // behaviour is identical the moment the table exists.
            $now = now();
            $seed = [
                ['name' => 'Booking',    'seo_url' => 'booking',    'ordering' => 1],
                ['name' => 'Property',   'seo_url' => 'property',   'ordering' => 2],
                ['name' => 'Amenities',  'seo_url' => 'amenities',  'ordering' => 3],
                ['name' => 'Policies',   'seo_url' => 'policies',   'ordering' => 4],
            ];
            foreach ($seed as $row) {
                DB::table('faq_categories')->insert(array_merge($row, [
                    'publish'    => 'published',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('faq_categories');
    }
}
