<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms')) {
            return;
        }

        $now = now();
        $pages = [
            [
                'name' => 'Home',
                'seo_url' => 'home',
                'templete' => 'home',
                'meta_title' => 'La Vida Belize — Your Own Piece of the Caribbean',
                'meta_description' => 'Private beachfront retreat near Placencia, Belize. Casa, Casita and Studio.',
            ],
            [
                'name' => 'About Us',
                'seo_url' => 'about-us',
                'templete' => 'about',
                'meta_title' => 'About Us — La Vida Belize',
                'meta_description' => 'About La Vida Belize, a small beachfront retreat in Placencia.',
            ],
            [
                'name' => 'About Owner',
                'seo_url' => 'about-owner',
                'templete' => 'about-owner',
                'meta_title' => 'About Owner — La Vida Belize',
                'meta_description' => 'About Brad, owner of La Vida Belize in Placencia.',
            ],
            [
                'name' => 'Properties',
                'seo_url' => 'properties',
                'templete' => 'property-list',
                'meta_title' => 'Properties — La Vida Belize',
                'meta_description' => 'Casa, Casita and Studio beachfront rentals near Placencia, Belize.',
            ],
            [
                'name' => 'FAQs',
                'seo_url' => 'faq',
                'templete' => 'faq',
                'meta_title' => 'FAQs — La Vida Belize',
                'meta_description' => 'Booking, pets, amenities and stay details for La Vida Belize.',
            ],
            [
                'name' => 'Attractions',
                'seo_url' => 'attractions',
                'templete' => 'attractions',
                'meta_title' => 'Attractions — La Vida Belize',
                'meta_description' => 'Dining, adventures, taxis, golf carts and car rentals near Placencia.',
            ],
            [
                'name' => 'Contact',
                'seo_url' => 'contact',
                'templete' => 'contact',
                'meta_title' => 'Contact — La Vida Belize',
                'meta_description' => 'Contact La Vida Belize in Placencia. Address, phone, email, WhatsApp and a message form.',
            ],
            [
                'name' => 'Experience',
                'seo_url' => 'experience',
                'templete' => 'experiences',
                'meta_title' => 'Experience — La Vida Belize',
                'meta_description' => 'Beach, pier, pools and palapa at La Vida Belize near Placencia.',
            ],
            [
                'name' => 'Group Stays',
                'seo_url' => 'groups',
                'templete' => 'groups',
                'meta_title' => 'Group Stays — La Vida Belize',
                'meta_description' => 'Book more than one home, or the whole La Vida Belize property.',
            ],
            [
                'name' => 'Guest Reviews',
                'seo_url' => 'reviews',
                'templete' => 'reviews',
                'meta_title' => 'Reviews — La Vida Belize',
                'meta_description' => 'Guest reviews from stays at the Casa, Casita and Studio.',
            ],
            [
                'name' => 'Privacy',
                'seo_url' => 'privacy',
                'templete' => 'privacy',
                'meta_title' => 'Privacy Policy — La Vida Belize',
                'meta_description' => 'Privacy policy for La Vida Belize.',
            ],
            [
                'name' => 'Terms',
                'seo_url' => 'terms',
                'templete' => 'terms',
                'meta_title' => 'Terms — La Vida Belize',
                'meta_description' => 'Terms and conditions for using the La Vida Belize website.',
            ],
            [
                'name' => 'Dining',
                'seo_url' => 'attractions-dining',
                'templete' => 'attractions-dining',
                'meta_title' => 'Dining — La Vida Belize',
                'meta_description' => 'Restaurants and casual tables near La Vida Belize in Placencia.',
            ],
            [
                'name' => 'Adventures',
                'seo_url' => 'attractions-adventures',
                'templete' => 'attractions-adventures',
                'meta_title' => 'Adventures — La Vida Belize',
                'meta_description' => 'Tours, reefs, rivers and jungle days from La Vida Belize.',
            ],
            [
                'name' => 'Taxi Services',
                'seo_url' => 'attractions-taxi',
                'templete' => 'attractions-taxi',
                'meta_title' => 'Taxi Services — La Vida Belize',
                'meta_description' => 'Local taxi contacts for getting around Placencia.',
            ],
            [
                'name' => 'Golf Cart Rental',
                'seo_url' => 'attractions-golf-cart',
                'templete' => 'attractions-golf-cart',
                'meta_title' => 'Golf Cart Rental — La Vida Belize',
                'meta_description' => 'Golf cart rental near La Vida Belize and Maya Beach.',
            ],
            [
                'name' => 'Car Rental',
                'seo_url' => 'attractions-car-rental',
                'templete' => 'attractions-car-rental',
                'meta_title' => 'Car Rental — La Vida Belize',
                'meta_description' => 'Car rental for exploring beyond Placencia from La Vida Belize.',
            ],
        ];

        $columns = array_flip(Schema::getColumnListing('cms'));
        foreach ($pages as $row) {
            $payload = array_intersect_key(array_merge($row, [
                'publish' => 'published',
                'updated_at' => $now,
                'created_at' => $now,
            ]), $columns);

            DB::table('cms')->updateOrInsert(
                ['seo_url' => $row['seo_url']],
                $payload
            );
        }

        if (Schema::hasTable('basic_settings')) {
            $nav = [
                ['label' => 'Home', 'url' => '/', 'side' => 'left', 'type' => 'link'],
                ['label' => 'About Us', 'url' => '/about-us', 'side' => 'left', 'type' => 'link'],
                ['label' => 'About Owner', 'url' => '/about-owner', 'side' => 'left', 'type' => 'link'],
                ['label' => 'Properties', 'url' => '/properties', 'side' => 'left', 'type' => 'link'],
                ['label' => 'FAQs', 'url' => '/faq', 'side' => 'left', 'type' => 'link'],
                ['label' => 'Attractions', 'url' => '/attractions', 'side' => 'left', 'type' => 'link'],
                ['label' => 'Contact Us', 'url' => '/contact', 'side' => 'right', 'type' => 'link'],
            ];
            $settings = [
                'website' => 'La Vida Belize',
                'email' => 'info@lavidabelize.com',
                'mobile' => '+1 (877) 823-5493',
                'whatsapp_number' => '5016278977',
                'address' => '204 Plantation, Placencia Road, Placencia, Stann Creek, Belize',
                'copyright' => '© ' . date('Y') . ' La Vida Belize.',
                'nav_cta_label' => 'Book Now',
                'nav_cta_url' => '/#booking',
                'nav_menu' => json_encode($nav),
                'logo' => 'assets/logo.png',
                'header_logo' => 'assets/logo.png',
                'favicon' => 'assets/favicon.svg',
            ];
            $settingColumns = array_flip(Schema::getColumnListing('basic_settings'));
            foreach ($settings as $name => $value) {
                $payload = array_intersect_key([
                    'name' => $name,
                    'value' => $value,
                    'updated_at' => $now,
                    'created_at' => $now,
                ], $settingColumns);
                DB::table('basic_settings')->updateOrInsert(
                    ['name' => $name],
                    $payload
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('cms')->whereIn('seo_url', [
            'about-us', 'about-owner', 'properties', 'contact', 'experience',
            'groups', 'privacy', 'terms', 'attractions-dining', 'attractions-adventures',
            'attractions-taxi', 'attractions-golf-cart', 'attractions-car-rental',
        ])->delete();
    }
};
