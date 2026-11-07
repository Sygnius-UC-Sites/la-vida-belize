<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base Vacation Rentals — content seed.
 *
 * Seeds the Base Vacation Rentals brand: 3 Your Destination homes, hosts Demo Hosts.
 *
 * Follows docs/DESIGN-MIGRATION-PLAYBOOK.md §2: every insert goes through
 * filterToColumns() + buildDefaultsFor() so it survives schema drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        /* ── 1. Basic settings ─────────────────────────────────────────── */
        $settings = [
            'website'              => 'Base Vacation Rentals',
            'email'                => 'hello@example.com',
            'mobile'               => '555-0100',
            'mobile1'              => '',
            'mobile2'              => '',
            'whatsapp_number'      => '15550100',
            'whatsapp_url'         => 'https://wa.me/15550100',
            'address'              => 'Your Destination',
            'copyright'            => '© 2026 Base Vacation Rentals. All rights reserved. · Your Destination',
            'about'                => 'Boutique vacation homes near the local trails and scenery of Your Destination — hosted directly by Demo Hosts.',
            'footer_brand_desc'    => 'Boutique vacation homes near the local trails and scenery of Your Destination — hosted directly by Demo Hosts.',
            'footer_brand_sub'     => 'DESTINATION',
            'footer_brand_tagline' => 'DESTINATION',
            'owner_name'           => 'Demo Hosts',
            'logo'                 => 'front/themes/base/img/logo.svg',
            'header_logo'          => 'front/themes/base/img/logo.svg',
            'favicon'              => 'front/themes/base/img/logo.svg',
            'footer-image'         => 'front/themes/base/img/logo.svg',
            'map'                  => 'https://www.google.com/maps?q=Your+Destination&output=embed',
            'map_embed_url'        => 'https://www.google.com/maps?q=Your+Destination&output=embed',
            'map_open_url'         => 'https://maps.google.com/?q=Your+Destination',
            'airbnb_url'           => '',
            'airbnb_booking_url'   => '',
            'book_external_url'    => '',
            'facebook'             => '',
            'instagram'            => '',
            'yelp_url'             => '',
            'pms_driver'           => 'lodgify',
            'pms_source'           => 'lodgify',
            'mail_from_name'       => 'Base Vacation Rentals',
            'checkin_default_host_name' => 'Demo Hosts',
            'property-heading-text'     => 'Three ways to wake up in the scenery',
            'property-subheading-text'  => 'The Homes',
            'testimonial-heading-text'  => 'Loved by 500+ guests',
            'testimonial-subheading-text' => 'Guest Favorites',
            'attraction-heading-text'   => 'Your Destination, minutes from your door',
            'attraction-subheading-text'=> 'Explore',
            'faq-heading-text'          => 'Frequently Asked Questions',
            'faq-subheading-text'       => 'Good to Know',
            'home_video'                => '',
            'nav_menu' => json_encode([
                ['label' => 'Home',        'url' => '/',            'side' => 'left',  'type' => 'link'],
                ['label' => 'Stays',       'url' => '/stays',       'side' => 'left',  'type' => 'link'],
                ['label' => 'Attractions', 'url' => '/attractions', 'side' => 'left',  'type' => 'link'],
                ['label' => 'About',       'url' => '/our-story',   'side' => 'right', 'type' => 'link'],
                ['label' => 'Reviews',     'url' => '/reviews',     'side' => 'right', 'type' => 'link'],
                ['label' => 'Contact',     'url' => '/contact-us',  'side' => 'right', 'type' => 'link'],
            ]),
        ];
        foreach ($settings as $name => $value) {
            if (DB::table('basic_settings')->where('name', $name)->exists()) {
                DB::table('basic_settings')->where('name', $name)->update(['value' => $value, 'updated_at' => $now]);
            } else {
                DB::table('basic_settings')->insert($this->filterToColumns('basic_settings', array_merge(
                    $this->buildDefaultsFor('basic_settings'),
                    ['name' => $name, 'value' => $value, 'created_at' => $now, 'updated_at' => $now]
                )));
            }
        }

        /* ── 2. Properties (single type → /stay/{slug}) ────────────────── */
        DB::table('property_galleries')->delete();
        DB::table('properties')->delete();

        $img = fn (string $f = '') => 'front/themes/base/img/placeholder.svg';

        $properties = [
            [
                'name' => 'Demo Beach House',
                'seo_url' => 'demo-beach-house',
                'type' => 'house',
                'subtitle' => 'Scenic Views',
                'heading' => 'Scenic views from three sun-tracking decks',
                'sleeps' => 6, 'bedroom' => 2, 'bathroom' => 2, 'beds' => 3,
                'standard_rate' => 329, 'min_stay' => 2,
                'checkin' => '16:00', 'checkout' => '10:00',
                'feature_image' => $img('retreat.jpg'),
                'banner_image' => $img('retreat.jpg'),
                'short_description' => 'Three sun-tracking decks, a fireplace for cool nights, and scenery framed from nearly every window.',
                'long_description' => '<p>Demo Beach House sits quietly in the Destination Area with scenic views from nearly every window. Mornings start with coffee on one of three decks as the sky turns gold; evenings end at the fire tables under some of the darkest skies in Your Destination.</p><p>The gourmet kitchen is fully stocked for everything from trail-day sandwiches to slow dinners, the indoor fireplace takes the edge off cool nights, and Landmark Trail, Scenic Trail, golf, restaurants, and the shops of the neighborhood are all just minutes away.</p>',
                'description' => 'Three sun-tracking decks, a fireplace for cool nights, and scenery framed from nearly every window.',
                'card_amenities' => json_encode(['Three decks', 'Gourmet kitchen', 'Fireplace', 'Gas grill & fire tables', 'Minutes to hiking & golf']),
                'detail_amenities_list' => json_encode([
                    'Scenic views', 'Three private decks', 'Gourmet kitchen', 'Indoor fireplace',
                    'Gas grill & fire tables', 'High-speed WiFi', 'Self check-in', 'Washer & dryer',
                    'Dedicated workspace', 'Free parking on premises', 'Air conditioning', 'Outdoor dining areas',
                ]),
                'detail_highlights' => json_encode([
                    ['title' => 'Scenic mornings', 'text' => 'Coffee on the east deck as the sun clears the horizon.'],
                    ['title' => 'Indoor-outdoor living', 'text' => 'Three decks, a gas grill, and fire tables built around the view.'],
                    ['title' => 'Minutes from it all', 'text' => 'Landmark Trail, Scenic Trail, golf, and the neighborhood within minutes.'],
                ]),
                'is_home' => 'true', 'status' => 'true', 'ordering' => 1,
                'airbnb_link' => '',
                'accepts_pets' => 0, 'instant_booking_button' => 'false',
                'no_of_guest' => 6,
                'meta_title' => 'Demo Beach House · 2BR Your Destination Vacation Home with Scenic Views',
                'meta_description' => 'Boutique 2-bedroom Your Destination vacation home with scenic views, three decks, gourmet kitchen, fireplace, and fire tables — minutes from Landmark Trail. Book direct.',
                'gallery' => [
                    ['image' => $img('retreat.jpg'),      'caption' => 'Demo Beach House — scenic backdrop'],
                    ['image' => $img('retreat-2.jpg'),    'caption' => 'Living space with natural light'],
                    ['image' => $img('morning-deck.jpg'), 'caption' => 'Morning coffee on the deck'],
                    ['image' => $img('deck-sunset.jpg'),  'caption' => 'Sunset dinners outdoors'],
                    ['image' => $img('firepit.jpg'),      'caption' => 'Evenings at the fire table'],
                ],
            ],
            [
                'name' => 'Demo Garden Studio',
                'seo_url' => 'demo-garden-studio',
                'type' => 'house',
                'subtitle' => 'Private Cottage',
                'heading' => 'A private remodeled studio made for slow evenings',
                'sleeps' => 3, 'bedroom' => 0, 'bathroom' => 1, 'beds' => 1,
                'standard_rate' => 189, 'min_stay' => 2,
                'checkin' => '16:00', 'checkout' => '10:00',
                'feature_image' => $img('studio.jpg'),
                'banner_image' => $img('studio.jpg'),
                'short_description' => 'A private remodeled studio with a pergola deck and a fire-table patio made for slow, unhurried evenings.',
                'long_description' => '<p>The studio is a private studio cottage tucked into Base Vacation Rentals — fully remodeled, quietly stylish, and made for two (plus one more on the sleeper). The remodeled kitchen handles real cooking, the pergola deck is built for long breakfasts, and the patio fire table is where evenings go to slow down.</p><p>Starlink keeps you connected when you want to be; the scenery make a strong case for staying offline. Hiking, golf, restaurants, and the Destination Area are minutes away.</p>',
                'description' => 'A private remodeled studio with a pergola deck and a fire-table patio made for slow, unhurried evenings.',
                'card_amenities' => json_encode(['Studio · 1 bed', 'Remodeled kitchen', 'Pergola deck', 'Patio fire table', 'Starlink internet']),
                'detail_amenities_list' => json_encode([
                    'Private entrance', 'Remodeled kitchen', 'Pergola-covered deck', 'Patio fire table',
                    'Starlink high-speed internet', 'Self check-in', 'Air conditioning', 'Outdoor dining',
                    'Free parking on premises', 'Coffee bar', 'Smart TV', 'Garden setting',
                ]),
                'detail_highlights' => json_encode([
                    ['title' => 'All yours', 'text' => 'A fully private cottage with its own entrance, deck, and patio.'],
                    ['title' => 'Starlink fast', 'text' => 'High-speed Starlink internet — stream, work, stay connected.'],
                    ['title' => 'Fire-table evenings', 'text' => 'A patio made for slow dinners and starlit nightcaps.'],
                ]),
                'is_home' => 'true', 'status' => 'true', 'ordering' => 2,
                'airbnb_link' => '',
                'accepts_pets' => 0, 'instant_booking_button' => 'false',
                'no_of_guest' => 3,
                'meta_title' => 'Demo Garden Studio · Private Your Destination Studio Cottage',
                'meta_description' => 'Private remodeled studio in the Destination Area with pergola deck, patio fire table, and Starlink internet — minutes from Landmark Trail. Book direct.',
                'gallery' => [
                    ['image' => $img('studio.jpg'),   'caption' => 'The studio — pergola deck'],
                    ['image' => $img('studio-2.jpg'), 'caption' => 'Remodeled interior'],
                    ['image' => $img('firepit.jpg'),  'caption' => 'Patio fire table at dusk'],
                ],
            ],
            [
                'name' => 'Demo Mountain Lodge',
                'seo_url' => 'demo-mountain-lodge',
                'type' => 'house',
                'subtitle' => "Scenic Trail Views",
                'heading' => "An expansive deck over Scenic Trail, under the stars",
                'sleeps' => 6, 'bedroom' => 2, 'bathroom' => 2, 'beds' => 3,
                'standard_rate' => 299, 'min_stay' => 2,
                'checkin' => '16:00', 'checkout' => '10:00',
                'feature_image' => $img('hillside.jpg'),
                'banner_image' => $img('hillside.jpg'),
                'short_description' => "An expansive deck over Scenic Trail, a quiet western hillside, and some of the darkest, starriest skies in the neighborhood.",
                'long_description' => '<p>Set on a quiet hillside above a local scenic trail, this home was made for the sky. Days fill with scenic views from the expansive deck; nights belong to the stars over the Destination Area.</p><p>Two bedrooms and two baths sleep six comfortably, Starlink keeps the internet fast, and the trailheads, golf, and restaurants of the neighborhood are minutes from the door.</p>',
                'description' => "An expansive deck over Scenic Trail, a quiet western hillside, and some of the darkest, starriest skies in the neighborhood.",
                'card_amenities' => json_encode(["Scenic Trail views", 'Expansive deck', 'Stargazing', 'Quiet western retreat', 'Starlink internet']),
                'detail_amenities_list' => json_encode([
                    "Scenic Trail views", 'Expansive viewing deck', 'Stargazing chairs', 'Starlink high-speed internet',
                    'Full kitchen', 'Self check-in', 'Washer & dryer', 'Air conditioning',
                    'Outdoor dining', 'Free parking on premises', 'Smart TV', 'Quiet hillside setting',
                ]),
                'detail_highlights' => json_encode([
                    ['title' => 'Dark-sky nights', 'text' => 'Some of the starriest skies in the neighborhood, right off the deck.'],
                    ['title' => 'Canyon panorama', 'text' => "Scenic Trail stretches out below the expansive deck."],
                    ['title' => 'Quiet by design', 'text' => 'A western hillside retreat away from the crowds.'],
                ]),
                'is_home' => 'true', 'status' => 'true', 'ordering' => 3,
                'airbnb_link' => '',
                'accepts_pets' => 0, 'instant_booking_button' => 'false',
                'no_of_guest' => 6,
                'meta_title' => 'Demo Mountain Lodge · 2BR Your Destination Home near Scenic Trail',
                'meta_description' => "2-bedroom Your Destination vacation home with Scenic Trail views, expansive deck, dark-sky stargazing, and Starlink internet. Book your scenic escape direct.",
                'gallery' => [
                    ['image' => $img('hillside.jpg'), 'caption' => 'The deck over Scenic Trail'],
                    ['image' => $img('stars.jpg'),    'caption' => 'Stargazing after dark'],
                    ['image' => $img('sky.jpg'),      'caption' => 'Your Destination skies at dusk'],
                    ['image' => $img('canyon.jpg'),   'caption' => "Scenic trails nearby"],
                ],
            ],
        ];

        $propDefaults = $this->buildDefaultsFor('properties');
        $galDefaults  = $this->buildDefaultsFor('property_galleries');
        foreach ($properties as $p) {
            $gallery = $p['gallery'];
            unset($p['gallery']);
            $row = $this->filterToColumns('properties', array_merge($propDefaults, $p, [
                'created_at' => $now, 'updated_at' => $now,
            ]));
            $id = DB::table('properties')->insertGetId($row);
            foreach ($gallery as $idx => $g) {
                DB::table('property_galleries')->insert($this->filterToColumns('property_galleries', array_merge($galDefaults, [
                    'property_id' => $id,
                    'image'       => $g['image'],
                    'caption'     => $g['caption'],
                    'sorting'     => $idx + 1,
                    'status'      => 'true',
                    'created_at'  => $now, 'updated_at' => $now,
                ])));
            }
        }

        /* ── 3. Attractions ────────────────────────────────────────────── */
        DB::table('attractions')->delete();
        DB::table('attraction_categories')->delete();

        $catDefaults = $this->buildDefaultsFor('attraction_categories');
        $categories = [
            ['name' => 'Hiking & Trails',    'seo_url' => 'hiking-trails',    'shortDescription' => 'Local paths, overlooks, and that wide-open quiet — world-class trails start minutes from the door.'],
            ['name' => 'Sights & Stargazing','seo_url' => 'sights-stargazing','shortDescription' => 'Scenic icons by day, dark-sky skies by night.'],
            ['name' => 'Golf & Leisure',     'seo_url' => 'golf-leisure',     'shortDescription' => 'Tee times under the scenery, spa days, and slow afternoons.'],
            ['name' => 'Dining & Shopping',  'seo_url' => 'dining-shopping',  'shortDescription' => 'From neighborhood favorites to the arts district\'s galleries and patios.'],
        ];
        $catIds = [];
        foreach ($categories as $c) {
            $catIds[$c['seo_url']] = DB::table('attraction_categories')->insertGetId(
                $this->filterToColumns('attraction_categories', array_merge($catDefaults, $c, [
                    'publish' => 'published', 'templete' => '',
                    'created_at' => $now, 'updated_at' => $now,
                ]))
            );
        }

        $attrDefaults = $this->buildDefaultsFor('attractions');
        $attractions = [
            ['name' => 'Landmark Trail', 'seo_url' => 'bell-rock', 'cat' => 'hiking-trails', 'ordering' => 1,
             'image' => $img('canyon.jpg'),
             'shortDescription' => 'Your Destination\'s most iconic formation, with the easy-to-moderate Landmark Trail Pathway starting practically at your door.',
             'description' => '<p>Landmark Trail is the Destination Area\'s signature overlook, catching soft light at sunrise and sunset. The Landmark Trail pathway loop offers 4+ miles of gentle, view-saturated trail, with plenty of spurs for scramblers. From Base Vacation Rentals you can be on the trail in under five minutes.</p>',
             'map_url' => 'https://maps.google.com/?q=Landmark+Trail,+Your+Destination'],
            ['name' => "Scenic Trail", 'seo_url' => 'jacks-canyon-trail', 'cat' => 'hiking-trails', 'ordering' => 2,
             'image' => $img('morning-deck.jpg'),
             'shortDescription' => 'The quiet backyard trail — a peaceful out-and-back that locals love, starting in the neighborhood.',
             'description' => '<p>Scenic Trail is the quieter side of Your Destination hiking: a peaceful trail that climbs gradually toward local overlook views without the crowds of the famous trailheads. It starts essentially in Base Vacation Rentals backyard, which makes it the perfect sunrise or golden-hour walk.</p>',
             'map_url' => 'https://maps.google.com/?q=Scenic+Trail,+Your+Destination'],
            ['name' => 'Scenic Overlook', 'seo_url' => 'scenic-overlook', 'cat' => 'hiking-trails', 'ordering' => 3,
             'image' => $img('hero-aerial.jpg'),
             'shortDescription' => 'The short, steep, unforgettable scramble to Your Destination\'s most photographed saddle.',
             'description' => '<p>Scenic Overlook is short (about 1.2 miles round trip) but memorably steep — a hands-and-feet scramble to a saddle with one of the Southwest\'s great views. Go at sunrise to beat the heat and the crowds, then reward yourself with breakfast back in the neighborhood.</p>',
             'map_url' => 'https://maps.google.com/?q=Scenic+Overlook,+Your+Destination'],
            ['name' => 'Local Scenic Byway', 'seo_url' => 'red-rock-scenic-byway', 'cat' => 'sights-stargazing', 'ordering' => 4,
             'image' => $img('sky.jpg'),
             'shortDescription' => 'America\'s first All-American Road — 7.5 miles of scenic cinema between the neighborhood and Uptown.',
             'description' => '<p>The local scenic road threads between Landmark Trail and Scenic Overlook on its way into Your Destination proper. Every pullout is a postcard; sunset turns the whole route golden. It begins right at the Destination Area — your daily commute to dinner is a scenic byway.</p>',
             'map_url' => 'https://maps.google.com/?q=Local+Scenic+Byway,+Your+Destination'],
            ['name' => 'Historic Viewpoint', 'seo_url' => 'historic-viewpoint', 'cat' => 'sights-stargazing', 'ordering' => 5,
             'image' => $img('retreat-2.jpg'),
             'shortDescription' => 'A favorite viewpoint and photo stop a short drive away.',
             'description' => '<p>Rising directly out of a scenic hillside, the Historic Viewpoint is one of Your Destination\'s most striking pieces of architecture — and one of its best easy viewpoints. Visit late afternoon when the light softens and the crowds thin.</p>',
             'map_url' => 'https://maps.google.com/?q=Historic+Viewpoint,+Your+Destination'],
            ['name' => 'Dark-Sky Stargazing', 'seo_url' => 'dark-sky-stargazing', 'cat' => 'sights-stargazing', 'ordering' => 6,
             'image' => $img('stars.jpg'),
             'shortDescription' => 'Quiet evening skies make the neighborhood a favorite place to stargaze.',
             'description' => '<p>The Destination Area keeps evenings calm and low-lit, making the decks a natural place to wind down after dark. Bring a blanket, dim the lights, and look up.</p>',
             'map_url' => 'https://maps.google.com/?q=Your+Destination'],
            ['name' => 'Local Golf Club', 'seo_url' => 'local-golf-club', 'cat' => 'golf-leisure', 'ordering' => 7,
             'image' => $img('deck-sunset.jpg'),
             'shortDescription' => 'A local course five minutes away, framed by scenery.',
             'description' => '<p>Golf with a scenic backdrop: Local Golf Club sits just minutes from Base Vacation Rentals. The nearby local resort adds a second course option.</p>',
             'map_url' => 'https://maps.google.com/?q=Local+Golf+Club,+Your+Destination'],
            ['name' => 'Arts & Dining District', 'seo_url' => 'arts-district', 'cat' => 'dining-shopping', 'ordering' => 8,
             'image' => $img('firepit.jpg'),
             'shortDescription' => 'Your Destination\'s arts-and-crafts village — galleries, courtyards, and patio dining under the sycamores.',
             'description' => '<p>Modeled after a traditional Mexican village, the arts district is Your Destination\'s favorite afternoon: galleries and studios, fountain courtyards, and some of the area\'s best restaurants. Fifteen minutes up the byway from the neighborhood.</p>',
             'map_url' => 'https://maps.google.com/?q=Arts+Dining+District,+Your+Destination'],
            ['name' => 'Destination Area Dining', 'seo_url' => 'voc-dining', 'cat' => 'dining-shopping', 'ordering' => 9,
             'image' => $img('studio-2.jpg'),
             'shortDescription' => 'Coffee, tacos, steakhouses, and date-night spots — all without leaving the neighborhood.',
             'description' => '<p>The Destination Area keeps it walkable: morning coffee and bakeries, casual tacos and pizza, and a handful of genuinely good dinner rooms. Ask us for the current favorites — we live here, and we keep the list fresh.</p>',
             'map_url' => 'https://maps.google.com/?q=Your+Destination+restaurants'],
        ];
        foreach ($attractions as $a) {
            $cat = $a['cat'];
            unset($a['cat']);
            DB::table('attractions')->insert($this->filterToColumns('attractions', array_merge($attrDefaults, $a, [
                'category_id' => $catIds[$cat],
                'publish'     => 'published',
                'created_at'  => $now, 'updated_at' => $now,
            ])));
        }

        /* ── 4. Testimonials ───────────────────────────────────────────── */
        DB::table('testimonials')->delete();
        $tDefaults = $this->buildDefaultsFor('testimonials');
        $testimonials = [
            ['name' => 'Marisa', 'profile' => 'Austin, TX · Verified stay', 'stay_date' => 'March 2026',
             'message' => 'The views are unreal and the home was spotless. the hosts thought of everything — we already want to come back.'],
            ['name' => 'David & Lena', 'profile' => 'Chicago, IL · Verified stay', 'stay_date' => 'February 2026',
             'message' => 'Check-in was effortless and the deck at sunset is something we\'ll remember for a long time. Truly a cut above.'],
            ['name' => 'The Okafors', 'profile' => 'Denver, CO · Verified stay', 'stay_date' => 'January 2026',
             'message' => 'Quiet, private, and minutes from the trails. The most seamless stay we\'ve had in Your Destination, hands down.'],
            ['name' => 'Priya & Sam', 'profile' => 'San Diego, CA · Verified stay', 'stay_date' => 'November 2025',
             'message' => 'The studio is even better than the photos — the fire table under the stars made the whole trip. Hosts who actually care.'],
            ['name' => 'Jennifer', 'profile' => 'Seattle, WA · Verified stay', 'stay_date' => 'October 2025',
             'message' => 'We stargazed from the deck every single night. Communication was instant and the local tips were perfect.'],
        ];
        foreach ($testimonials as $i => $t) {
            DB::table('testimonials')->insert($this->filterToColumns('testimonials', array_merge($tDefaults, $t, [
                'rating'     => 5,
                'status'     => 'true',
                'is_home'    => $i < 3 ? 'true' : 'false',
                'created_at' => $now, 'updated_at' => $now,
            ])));
        }

        /* ── 5. FAQs ───────────────────────────────────────────────────── */
        DB::table('faqs')->delete();
        $fDefaults = $this->buildDefaultsFor('faqs');
        $faqs = [
            ['type' => 'booking', 'question' => 'How do I book — and why book direct?',
             'answer' => 'Book right here on the site: pick your home, choose dates, and pay securely online. Booking direct always gets you our best rate — no third-party service fees — and you\'re talking straight to Demo Hosts from day one.'],
            ['type' => 'booking', 'question' => 'What are check-in and check-out times?',
             'answer' => 'Check-in is from 4:00 PM and check-out is by 10:00 AM. Self check-in is via smart lock — we send your personal door code and simple directions the day before you arrive.'],
            ['type' => 'booking', 'question' => 'What is the cancellation policy?',
             'answer' => 'Our standard policy offers a full refund for cancellations made well in advance of check-in; the exact terms for your dates are shown clearly before you pay. If plans change, message us — we\'re humans, and we\'ll always do our best.'],
            ['type' => 'amenities', 'question' => 'Is the internet really fast enough to work remotely?',
             'answer' => 'Yes — the studio and Mountain Lodge run on Starlink and the beach house has high-speed WiFi. Streaming, video calls, and uploads are all comfortable. Many guests work a morning and hike the afternoon.'],
            ['type' => 'amenities', 'question' => 'Are the kitchens fully equipped?',
             'answer' => 'Fully. Cookware, knives, spices, coffee setups (yes, a real grinder), and full-size appliances. The beach house\'s gourmet kitchen handles a proper dinner party; the studio\'s remodeled kitchen covers everything a couple needs.'],
            ['type' => 'amenities', 'question' => 'Which outdoor features does each home have?',
             'answer' => 'The beach house has three decks, a gas grill, and fire tables. The studio has a pergola-covered deck and a patio fire table. Mountain Lodge has an expansive deck near Scenic Trail that\'s made for stargazing.'],
            ['type' => 'property', 'question' => 'How close are the trails, golf, and restaurants?',
             'answer' => 'Genuinely minutes. Landmark Trail Pathway and the Scenic Trail trailhead are practically out the back door, Local Golf Club is a five-minute drive, and the Destination Area\'s restaurants and shops are just down the road.'],
            ['type' => 'property', 'question' => 'Are the homes suitable for families?',
             'answer' => 'The beach house and Mountain Lodge each sleep six across two bedrooms and are great for families. The studio is a studio best suited to couples (plus one). Note that the decks and fire tables mean little ones need supervision outdoors.'],
            ['type' => 'policies', 'question' => 'Are pets allowed?',
             'answer' => 'We love them, but to keep the homes allergy-friendly we don\'t host pets. Service animals are always welcome — just let us know in advance so we can prepare.'],
            ['type' => 'policies', 'question' => 'Is smoking allowed?',
             'answer' => 'All three homes are strictly non-smoking indoors and out — Your Destination\'s fire risk is real. Violations forfeit the deposit and may end the stay early.'],
        ];
        foreach ($faqs as $f) {
            DB::table('faqs')->insert($this->filterToColumns('faqs', array_merge($fDefaults, $f, [
                'created_at' => $now, 'updated_at' => $now,
            ])));
        }

        /* ── 6. CMS pages ──────────────────────────────────────────────── */
        $cms = [
            'home' => [
                'name' => 'Home',
                'meta_title' => 'Base Vacation Rentals · Boutique Your Destination Vacation Homes',
                'meta_description' => 'Boutique vacation homes in the Destination Area, Your Destination. Scenic mornings, quiet evenings, and effortless direct booking — hosted by Demo Hosts.',
                'hero_eyebrow' => 'A Private Collection · Your Destination',
                'hero_heading' => 'Stay Where Your Destination Slows Down',
                'hero_sub' => 'Boutique vacation homes in the Destination Area, designed for scenic mornings, quiet evenings, and effortless direct booking.',
                'hero_carousel_images' => json_encode([
                    '/front/themes/base/img/placeholder.svg',
                ]),
                'hero_cta1_label' => 'Explore Stays', 'hero_cta1_url' => '/stays',
                'hero_cta2_label' => 'Check Availability', 'hero_cta2_url' => '/stays',
                'hero_video_src' => '',
                'story_eyebrow' => 'A Private Your Destination Collection',
                'story_title' => 'Not a rental portfolio — a handful of homes we tend ourselves, tucked against the scenery of the Destination Area.',
                'retreats_eyebrow' => 'The Homes',
                'retreats_title' => 'Three ways to wake up in the scenery',
                'better_title' => 'Boutique hospitality, handled.',
                'better_sub' => 'The details that turn a rental into a retreat — considered, consistent, and quietly taken care of.',
                'experiences_eyebrow' => 'A Day, The Your Destination Way',
                'experiences_heading' => 'From first light to the last star',
                'experiences_desc' => 'One unhurried day at Base Vacation Rentals — sunrise coffee on the deck, an afternoon on the trails, dinner as the rocks turn gold, and a night that ends under the stars.',
                'testimonials_heading' => 'Loved by 500+ guests',
                'cta_section_heading' => 'Your Destination Stay Starts Here',
                'cta_section_body' => 'Compare homes, check availability, and book your scenic escape directly — with the people who host you.',
                'cta_section_cta1_label' => 'Check Availability', 'cta_section_cta1_url' => '/stays',
                'cta_section_cta2_label' => 'Contact the Host', 'cta_section_cta2_url' => '/contact-us',
                'stats_list' => json_encode([
                    ['n' => '4.97', 'l' => 'Average rating'],
                    ['n' => '500+', 'l' => 'Guests welcomed'],
                    ['n' => 'Top 1%', 'l' => 'Guest favorites'],
                    ['n' => '9 yrs', 'l' => 'Hosting experience'],
                ]),
            ],
            'our-story' => [
                'name' => 'About Us',
                'meta_title' => 'About Demo Hosts · Base Vacation Rentals, Your Destination',
                'meta_description' => 'Meet Demo Hosts, Superhosts of Base Vacation Rentals — boutique vacation homes in the Destination Area, Your Destination.',
                'hero_eyebrow' => 'Your Hosts',
                'hero_heading' => 'Hosted by the people who live here',
                'hero_sub' => 'We\'re the hosts — retired to Your Destination in 2021, hosting travelers from across the country and around the world ever since.',
                'story_block_label' => 'Our Story',
                'story_block_heading' => 'Hi, we\'re the hosts.',
                'story_block_body' => 'We retired to Your Destination in 2021 and love hosting travelers from across the country and around the world. Our goal is to make every stay seamless, comfortable, and memorable — from a smooth self check-in to the local recommendations that turn a good trip into the one you keep talking about. We live nearby, we answer quickly, and we treat your stay the way we\'d want ours treated.',
                'story_block_image' => 'front/themes/base/img/placeholder.svg',
                'story_block_author_name' => 'Demo Hosts',
                'story_block_author_role' => 'Superhosts · Destination Area',
                'stats_list' => json_encode([
                    ['n' => '4.96★', 'l' => 'Average rating'],
                    ['n' => '500+', 'l' => 'Guests hosted'],
                    ['n' => '<1 hr', 'l' => 'Typical reply'],
                    ['n' => '9 yrs', 'l' => 'Hosting experience'],
                ]),
                'inner_banner_image' => 'front/themes/base/img/placeholder.svg',
            ],
            'attractions' => [
                'name' => 'Attractions',
                'meta_title' => 'Your Destination Attractions Near the Destination Area · Base Vacation Rentals',
                'meta_description' => 'Landmark Trail, Scenic Trail, golf, stargazing, the arts district, and more — the best of Your Destination, minutes from Base Vacation Rentals.',
                'hero_eyebrow' => 'Explore',
                'hero_heading' => 'Your Destination, minutes from your door',
                'hero_sub' => 'Trails, tee times, galleries, and dark-sky nights — everything that makes the Destination Area the quiet side of Your Destination.',
                'inner_banner_image' => 'front/themes/base/img/placeholder.svg',
            ],
            'reviews' => [
                'name' => 'Reviews',
                'meta_title' => 'Guest Reviews · Base Vacation Rentals, Your Destination',
                'meta_description' => '4.97 average across 500+ guests. Read what travelers say about staying at Base Vacation Rentals in Your Destination.',
                'hero_eyebrow' => 'Guest Favorites',
                'hero_heading' => 'Loved by 500+ guests',
                'hero_sub' => 'A 4.97 average rating, nine years of hosting, and the kind of reviews we work hard to keep earning.',
                'inner_banner_image' => 'front/themes/base/img/placeholder.svg',
            ],
            'contact-us' => [
                'name' => 'Contact',
                'meta_title' => 'Contact Demo Hosts · Base Vacation Rentals, Your Destination',
                'meta_description' => 'Questions about a stay? Contact Demo Hosts, hosts of Base Vacation Rentals in the Destination Area, Your Destination.',
                'hero_eyebrow' => 'Get in Touch',
                'hero_heading' => 'Talk to your hosts',
                'hero_sub' => 'Questions about dates, the homes, or Your Destination itself — we answer fast, usually within the hour.',
                'inner_banner_image' => 'front/themes/base/img/placeholder.svg',
            ],
            'faq' => [
                'name' => 'FAQ',
                'meta_title' => 'FAQs · Base Vacation Rentals, Your Destination',
                'meta_description' => 'Check-in, WiFi, kitchens, pets, policies — everything you need to know before your Base Vacation Rentals stay.',
                'hero_eyebrow' => 'Good to Know',
                'hero_heading' => 'Frequently asked questions',
                'hero_sub' => 'The practical details, answered. Anything else — just ask.',
                'inner_banner_image' => 'front/themes/base/img/placeholder.svg',
            ],
            'booking' => [
                'name' => 'Stays',
                'meta_title' => 'Our Your Destination Homes · Check Availability · Base Vacation Rentals',
                'meta_description' => 'Compare our three boutique Your Destination vacation homes, check availability, and book direct for the best rate.',
                'hero_eyebrow' => 'The Homes',
                'hero_heading' => 'Three ways to wake up in the scenery',
                'hero_sub' => 'Each home is hand-prepared for your arrival, with full kitchens, fast internet, and outdoor living built around the view.',
                'booking_empty_message' => 'No homes match those filters — try widening your dates or guest count, or contact us and we\'ll help you find the right fit.',
                'inner_banner_image' => 'front/themes/base/img/placeholder.svg',
            ],
            'gallery' => [
                'name' => 'Gallery',
                'meta_title' => 'Photo Gallery · Base Vacation Rentals, Your Destination',
                'meta_description' => 'Scenic views, decks at sunset, fire tables and starry skies — scenes from Base Vacation Rentals.',
                'hero_eyebrow' => 'The Collection',
                'hero_heading' => 'Scenes from Base Vacation Rentals',
                'hero_sub' => 'Scenic mornings, golden-hour dinners, and the dark skies in between.',
                'inner_banner_image' => 'front/themes/base/img/placeholder.svg',
            ],
        ];
        foreach ($cms as $seo => $fields) {
            DB::table('cms')->where('seo_url', $seo)->update(array_merge(
                $this->filterToColumns('cms', $fields),
                ['updated_at' => $now]
            ));
        }

        // Page-level meta keywords (replaces previous client's keywords).
        $keywords = [
            'home'        => 'base vacation rentals properties, destination vacation rental, destination area, scenic views, direct booking, scenic trail',
            'our-story'   => 'demo hosts, destination superhosts, base vacation rentals hosts',
            'attractions' => 'destination attractions, landmark trail, scenic trail, local golf, arts-district, destination stargazing',
            'reviews'     => 'base vacation rentals reviews, destination vacation rental reviews, guest favorites',
            'contact-us'  => 'contact base vacation rentals properties, destination vacation rental contact',
            'faq'         => 'base vacation rentals faq, destination rental check-in, starlink wifi, pet policy',
            'booking'     => 'destination vacation homes, check availability, book direct destination',
            'gallery'     => 'base vacation rentals photos, destination vacation home gallery',
        ];
        foreach ($keywords as $seo => $kw) {
            DB::table('cms')->where('seo_url', $seo)->update(['meta_keywords' => $kw]);
        }

        // Legacy duplicate pages from the previous client — not in the base nav.
        DB::table('cms')->whereIn('seo_url', ['condos', 'houses', 'experiences', 'faqs'])
            ->update(['publish' => 'draft']);
        DB::table('cms')->where('seo_url', 'privacy-policy')->update([
            'meta_title' => 'Privacy Policy · Base Vacation Rentals', 'meta_keywords' => '',
            'meta_description' => 'Base Vacation Rentals, Your Destination.',
        ]);
        DB::table('cms')->where('seo_url', 'terms-of-service')->update([
            'meta_title' => 'Terms of Service · Base Vacation Rentals', 'meta_keywords' => '',
            'meta_description' => 'Base Vacation Rentals, Your Destination.',
        ]);
    }

    public function down(): void
    {
        // Content seed — irreversible by design.
    }

    /* ── helpers (per DESIGN-MIGRATION-PLAYBOOK §2) ────────────────────── */

    private function filterToColumns(string $table, array $row): array
    {
        if (!Schema::hasTable($table)) return [];
        $cols = array_column(Schema::getColumns($table), 'name');
        return array_intersect_key($row, array_flip($cols));
    }

    private function buildDefaultsFor(string $table): array
    {
        if (!Schema::hasTable($table)) return [];
        $out = [];
        foreach (Schema::getColumns($table) as $c) {
            if (in_array($c['name'], ['id', 'created_at', 'updated_at', 'deleted_at'], true)) continue;
            if (!empty($c['nullable'])) continue;
            if (($c['default'] ?? null) !== null) continue;
            $type = strtolower($c['type_name'] ?? $c['type'] ?? '');
            if (preg_match('/int|decimal|float|double|numeric/', $type)) {
                $out[$c['name']] = 0;
            } elseif (str_contains($type, 'json')) {
                $out[$c['name']] = '[]';
            } else {
                $out[$c['name']] = '';
            }
        }
        return $out;
    }
};
