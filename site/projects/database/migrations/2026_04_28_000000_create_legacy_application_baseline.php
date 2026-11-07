<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the legacy CMS tables that existed in the original database dump
 * but never had migrations. Later dated migrations evolve this baseline.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Existing installations already received these tables from the original
        // database snapshot. This migration only repairs genuinely fresh installs.
        if (Schema::hasTable('migrations')
            && DB::table('migrations')->where('migration', '2026_04_29_000001_seed_nav_menu_basic_setting')->exists()) {
            return;
        }

        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('standard_rate', 10, 2)->nullable();
            $table->string('is_home', 10)->default('false');
            $table->string('instant_booking_button', 10)->default('false');
            $table->integer('ordering')->default(0);
            $table->string('banner_image')->nullable();
            $table->string('property_view')->nullable();
            $table->longText('card_amenities')->nullable();
            $table->longText('tags')->nullable();
            $table->string('pms_source')->nullable();
            $table->string('pms_room_id')->nullable();
            $table->string('is_trending', 10)->default('false');
            $table->string('is_top', 10)->default('false');
            $table->string('is_feature', 10)->default('false');
            $table->string('is_bestseller', 10)->default('false');
            $table->string('is_sale', 10)->default('false');
            $table->string('is_hot', 10)->default('false');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('property_status', 50)->nullable();
            $table->integer('min_stay')->nullable();
            $table->mediumText('map')->nullable();
            $table->string('checkin', 50)->nullable();
            $table->string('checkout', 50)->nullable();
            $table->string('category', 50)->nullable();
            $table->string('bed_type', 50)->nullable();
            $table->text('rental_aggrement_attachment')->nullable();
            $table->longText('welcome_package_description')->nullable();
            $table->text('welcome_package_attachment')->nullable();
            $table->text('api_id')->nullable();
            $table->text('api_pms')->nullable();
            $table->integer('king_beds')->default(0);
            $table->integer('queen_beds')->default(0);
            $table->string('extra_bed', 1025)->nullable();
            $table->text('vrbo_link')->nullable();
            $table->text('airbnb_link')->nullable();
            $table->string('checkin_day')->nullable();
            $table->string('checkout_day')->nullable();
            $table->string('pet_fee_interval', 50)->nullable();
            $table->string('max_pet', 50)->nullable();
            $table->string('guest_fee', 50)->nullable();
            $table->string('no_of_guest', 50)->nullable();
            $table->double('heating_pool_fee')->nullable();
            $table->string('pet_fee_type', 191)->default('taxable');
            $table->string('heating_pool_fee_type', 191)->default('taxable');
            $table->double('firebase_base_price')->nullable();
            $table->text('bedroom_text')->nullable();
            $table->text('bathroom_text')->nullable();
            $table->text('beds_text')->nullable();
            $table->text('sleeps_text')->nullable();
            $table->longText('rates')->nullable();
            $table->longText('get_quote_description')->nullable();
            $table->integer('max_rv_hook_up')->default(0);
            $table->longText('review_text')->nullable();
            $table->integer('is_parent')->nullable();
            $table->text('box_heading')->nullable();
            $table->text('box_description')->nullable();
            $table->integer('security_deposit_amount')->nullable();
            $table->text('security_deposit_text')->nullable();
            $table->text('visitor_rate_info')->nullable();
            $table->text('total_amount_include_text')->nullable();
        });

        Schema::create('basic_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->longText('value')->nullable();
            $table->timestamps();
            $table->unique('name');
        });

        Schema::create('cms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('seo_url')->unique();
            $table->longText('shortDescription')->nullable();
            $table->longText('mediumDescription')->nullable();
            $table->longText('longDescription')->nullable();
            $table->longText('description')->nullable();
            $table->string('image')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('templete');
            $table->string('bannerImage')->nullable();
            $table->string('publish')->default('published');
            $table->longText('footer_section')->nullable();
            $table->longText('header_section')->nullable();
            $table->longText('seo_section')->nullable();
            $table->string('image_2')->nullable();
            $table->string('image_3')->nullable();
            $table->string('faq_title')->nullable();
            $table->text('faq_desction')->nullable();
            $table->string('faq_image')->nullable();
            $table->string('strip_title')->nullable();
            $table->text('strip_desction')->nullable();
            $table->string('strip_image')->nullable();
            $table->string('about_image1')->nullable();
            $table->string('about_image2')->nullable();
            $table->string('owner_image')->nullable();
            $table->string('gallery_background')->nullable();
            $table->string('property_background')->nullable();
            $table->string('about_background')->nullable();
            $table->string('gallery_heading')->nullable();
            $table->timestamps();
        });

        Schema::create('attraction_categories', function (Blueprint $table) {
            $this->contentColumns($table);
            $table->string('attraction_image')->nullable();
        });

        Schema::create('attractions', function (Blueprint $table) {
            $this->contentColumns($table);
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->integer('ordering')->default(0);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('type')->default('internal');
            $table->string('mobile')->nullable();
            $table->text('address')->nullable();
        });

        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->string('status', 10)->default('true');
            $table->integer('sorting')->default(0);
            $table->timestamps();
        });

        Schema::create('sliders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('image')->nullable();
            $table->longText('description')->nullable();
            $table->string('status', 10)->default('true');
            $table->integer('sorting')->default(0);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('type')->nullable();
            $table->longText('question_ger')->nullable();
            $table->longText('answer_ger')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('message')->nullable();
            $table->string('image')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('profile')->nullable();
            $table->string('stay_date')->nullable();
            $table->string('score')->nullable();
            $table->string('property_id')->nullable();
            $table->string('status')->default('false');
            $table->string('end_date')->nullable();
            $table->string('is_home')->default('false');
            $table->timestamps();
        });

        Schema::create('contactus_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 1025);
            $table->string('email', 1025);
            $table->string('mobile', 1025)->nullable();
            $table->longText('message');
            $table->string('date_of_request', 1025)->nullable();
            $table->string('budget', 1025)->nullable();
            $table->string('guests', 1025)->nullable();
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('code')->unique();
            $table->string('amount');
            $table->string('type');
            $table->unsignedBigInteger('property_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('min_stay')->nullable();
            $table->timestamps();
        });

        Schema::create('properties_rates_group', function (Blueprint $table) {
            $table->id();
            $table->string('property_id');
            $table->date('start_date');
            $table->integer('start_date_timestamp');
            $table->date('end_date');
            $table->integer('end_date_timestamp');
            $table->decimal('price', 8, 2)->nullable();
            $table->string('is_available')->default('1');
            $table->string('platform_type')->default('airbnb');
            $table->string('currency')->default('USD');
            $table->decimal('base_price', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('min_stay')->nullable();
            $table->string('base_min_stay')->nullable();
            $table->string('discount_weekly', 1025)->nullable();
            $table->string('discount_monthly', 1025)->nullable();
            $table->string('name_of_price')->default('');
            $table->string('type_of_price')->default('');
            $table->double('monday_price')->nullable();
            $table->double('tuesday_price')->nullable();
            $table->double('wednesday_price')->nullable();
            $table->double('thrusday_price')->nullable();
            $table->double('friday_price')->nullable();
            $table->double('saturday_price')->nullable();
            $table->double('sunday_price')->nullable();
            $table->string('checkin_day')->nullable();
            $table->string('checkout_day')->nullable();
            $table->timestamps();
        });

        Schema::create('ical_import_list', function (Blueprint $table) {
            $table->id();
            $table->text('ical_link');
            $table->longText('property_id');
            $table->timestamps();
        });

        Schema::create('ical_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ppp_id')->default(0);
            $table->string('ical_link')->default('');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('text');
            $table->unsignedBigInteger('event_pid');
            $table->unsignedBigInteger('cat_id')->default(0);
            $table->string('uid');
            $table->string('event_type', 30);
            $table->integer('booking_status')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->text('receipt_url')->nullable();
            $table->string('customer_id', 1025)->nullable();
            $table->text('balance_transaction')->nullable();
            $table->text('tran_id')->nullable();
            $table->longText('description')->nullable();
            $table->string('status', 1025)->default('pending');
            $table->string('type')->default('stripe');
            $table->double('amount');
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $this->contentColumns($table);
            $table->string('attraction_image')->nullable();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->char('iso3', 3)->nullable();
            $table->char('numeric_code', 3)->nullable();
            $table->char('iso2', 2)->nullable();
            $table->string('phonecode')->nullable();
            $table->string('capital')->nullable();
            $table->string('currency')->nullable();
            $table->string('currency_name')->nullable();
            $table->string('currency_symbol')->nullable();
            $table->string('tld')->nullable();
            $table->string('native')->nullable();
            $table->string('region')->nullable();
            $table->string('subregion')->nullable();
            $table->text('timezones')->nullable();
            $table->text('translations')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('emoji', 191)->nullable();
            $table->string('emojiU', 191)->nullable();
            $table->integer('flag')->default(1);
            $table->string('wikiDataId')->nullable();
            $table->longText('footer_section')->nullable();
            $table->longText('header_section')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->text('meta_title')->nullable();
            $table->longText('seo_text')->nullable();
            $table->string('seo_url')->nullable();
            $table->string('bannerImage')->nullable();
            $table->timestamps();
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('country_id');
            $table->char('country_code', 2)->nullable();
            $table->string('fips_code')->nullable();
            $table->string('iso2')->nullable();
            $table->string('type', 191)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('flag')->default(1);
            $table->string('wikiDataId')->nullable();
            $table->string('seo_url')->nullable();
            $table->longText('footer_section')->nullable();
            $table->longText('header_section')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->text('meta_title')->nullable();
            $table->longText('seo_text')->nullable();
            $table->string('bannerImage')->nullable();
            $table->integer('is_featured')->default(0);
            $table->string('image')->nullable();
            $table->timestamps();
        });

        DB::table('countries')->insert([
            'id' => 233,
            'name' => 'United States',
            'iso3' => 'USA',
            'numeric_code' => '840',
            'iso2' => 'US',
            'phonecode' => '1',
            'capital' => 'Washington',
            'currency' => 'USD',
            'currency_name' => 'United States dollar',
            'currency_symbol' => '$',
            'native' => 'United States',
            'region' => 'Americas',
            'subregion' => 'Northern America',
            'latitude' => 38,
            'longitude' => -97,
            'seo_url' => 'united-states',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('states')->insert([
            'name' => 'Arizona',
            'country_id' => 233,
            'country_code' => 'US',
            'iso2' => 'AZ',
            'latitude' => 34.048928,
            'longitude' => -111.093731,
            'seo_url' => 'arizona',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('property_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_title', 1025);
            $table->string('room_sub_title', 1025)->nullable();
            $table->longText('room_description')->nullable();
            $table->string('room_status')->nullable()->default('active');
            $table->string('property_id');
            $table->timestamps();
        });

        Schema::create('property_room_items', function (Blueprint $table) {
            $table->id();
            $table->string('sub_room_title', 1025);
            $table->string('sub_room_sub_title', 1025)->nullable();
            $table->longText('sub_room_description')->nullable();
            $table->string('sub_room_status')->nullable()->default('active');
            $table->string('room_id');
            $table->timestamps();
        });

        Schema::create('property_room_item_images', function (Blueprint $table) {
            $table->id();
            $table->string('sub_room_id');
            $table->string('sub_room_image', 1025);
            $table->timestamps();
        });

        Schema::create('property_spaces', function (Blueprint $table) {
            $table->id();
            $table->string('property_id');
            $table->text('space_name');
            $table->string('space_image', 1025)->nullable();
            $table->string('space_status')->default('active');
            $table->timestamps();
        });

        Schema::create('email_templetes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject')->nullable();
            $table->longText('message')->nullable();
            $table->timestamps();
        });
    }

    private function contentColumns(Blueprint $table): void
    {
        $table->id();
        $table->string('name');
        $table->string('seo_url')->unique();
        $table->longText('shortDescription')->nullable();
        $table->longText('mediumDescription')->nullable();
        $table->longText('longDescription')->nullable();
        $table->longText('description')->nullable();
        $table->string('image')->nullable();
        $table->string('meta_title')->nullable();
        $table->text('meta_keywords')->nullable();
        $table->text('meta_description')->nullable();
        $table->string('templete');
        $table->string('bannerImage')->nullable();
        $table->string('publish')->default('published');
        $table->longText('footer_section')->nullable();
        $table->longText('header_section')->nullable();
        $table->timestamps();
    }

    public function down(): void
    {
        foreach ([
            'email_templetes', 'property_spaces', 'property_room_item_images',
            'property_room_items', 'property_rooms', 'states', 'countries',
            'locations', 'payments',
            'properties_rates_group', 'ical_events',
            'ical_import_list', 'coupons', 'contactus_requests', 'testimonials',
            'faqs', 'sliders', 'galleries', 'attractions',
            'attraction_categories', 'cms', 'basic_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
