<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds La Vida attraction categories + venue cards from the locked static copy.
 */
class LaVidaAttractionsSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('attraction_categories') || !Schema::hasTable('attractions')) {
            return;
        }

        $now = now();
        $categoryColumns = array_flip(Schema::getColumnListing('attraction_categories'));
        $attractionColumns = array_flip(Schema::getColumnListing('attractions'));

        foreach ($this->categories() as $row) {
            $payload = array_intersect_key(array_merge($row, [
                'templete' => 'common',
                'publish' => 'published',
                'updated_at' => $now,
            ]), $categoryColumns);
            $existing = DB::table('attraction_categories')->where('seo_url', $row['seo_url'])->first();
            if ($existing) {
                DB::table('attraction_categories')->where('id', $existing->id)->update($payload);
            } else {
                $payload['created_at'] = $now;
                DB::table('attraction_categories')->insert($payload);
            }
        }

        $categoryIds = DB::table('attraction_categories')->pluck('id', 'seo_url');

        foreach ($this->venues() as $row) {
            $categorySeo = $row['category_seo'];
            unset($row['category_seo']);
            $payload = array_intersect_key(array_merge($row, [
                'category_id' => $categoryIds[$categorySeo] ?? null,
                'templete' => 'common',
                'publish' => 'published',
                'type' => $row['type'] ?? 'internal',
                'updated_at' => $now,
            ]), $attractionColumns);
            $existing = DB::table('attractions')->where('seo_url', $row['seo_url'])->first();
            if ($existing) {
                DB::table('attractions')->where('id', $existing->id)->update($payload);
            } else {
                $payload['created_at'] = $now;
                DB::table('attractions')->insert($payload);
            }
        }
    }

    private function categories(): array
    {
        return [
            [
                'name' => 'Dining',
                'seo_url' => 'dining',
                'shortDescription' => 'Eat',
                'description' => 'Gelato, pizza, beach grills and village tables a short drive from the property.',
                'image' => 'assets/attractions/dining.jpg',
                'bannerImage' => 'assets/attractions/dining.jpg',
                'meta_title' => 'Dining — La Vida Belize',
                'meta_description' => 'Dining near La Vida Belize in Placencia.',
                'ordering' => 10,
            ],
            [
                'name' => 'Adventures',
                'seo_url' => 'adventures',
                'shortDescription' => 'Do',
                'description' => 'Banana farms, jungle parks, reefs, rivers and day trips inland.',
                'image' => 'assets/attractions/adventures.jpg',
                'bannerImage' => 'assets/attractions/adventures.jpg',
                'meta_title' => 'Adventures — La Vida Belize',
                'meta_description' => 'Adventures near La Vida Belize in Placencia.',
                'ordering' => 20,
            ],
            [
                'name' => 'Taxi Services',
                'seo_url' => 'taxi',
                'shortDescription' => 'Get around',
                'description' => 'Reliable local drivers for village runs and longer journeys.',
                'image' => 'assets/attractions/taxi.jpg',
                'bannerImage' => 'assets/attractions/taxi.jpg',
                'meta_title' => 'Taxi Services — La Vida Belize',
                'meta_description' => 'Taxi Services near La Vida Belize in Placencia.',
                'ordering' => 30,
            ],
            [
                'name' => 'Golf Cart Rental',
                'seo_url' => 'golf-cart',
                'shortDescription' => 'Get around',
                'description' => 'The easy way to move between beach bars, Maya Beach and town.',
                'image' => 'assets/attractions/golf.jpg',
                'bannerImage' => 'assets/attractions/golf.jpg',
                'meta_title' => 'Golf Cart Rental — La Vida Belize',
                'meta_description' => 'Golf Cart Rental near La Vida Belize in Placencia.',
                'ordering' => 40,
            ],
            [
                'name' => 'Car Rental',
                'seo_url' => 'car-rental',
                'shortDescription' => 'Get around',
                'description' => 'Useful for jungle days, the zoo, or exploring farther along the coast.',
                'image' => 'assets/attractions/car.jpg',
                'bannerImage' => 'assets/attractions/car.jpg',
                'meta_title' => 'Car Rental — La Vida Belize',
                'meta_description' => 'Car Rental near La Vida Belize in Placencia.',
                'ordering' => 50,
            ],
        ];
    }

    private function venues(): array
    {
        return [
            $this->venue('dining', 10, 'Tutti-Frutti Gelato', 'tutti-frutti-gelato', 'assets/attractions/dining.jpg',
                'Tutti-Frutti Gelato in Placencia is a must-try for anyone with a sweet tooth. This popular spot offers delicious gelato in a variety of flavors, perfect for cooling down on a warm day.'),
            $this->venue('dining', 20, 'ChaChi’s', 'chachis', 'assets/attractions/dining-chachis.jpeg',
                'Located 25 minutes south of Placencia, ChaChi’s offers pizza and live music in a lively atmosphere. It’s a fun spot for a night out with friends or family.'),
            $this->venue('dining', 30, 'Rumfish y Vino', 'rumfish-y-vino', 'assets/attractions/dining-rumfish.jpg',
                'Rumfish y Vino in Placencia offers a great menu and excellent food, making it a popular choice for dining out. Located 25 minutes south of Placencia, it’s worth the drive for a delicious meal.'),
            $this->venue('dining', 40, 'Rick’s Café', 'ricks-cafe', 'assets/attractions/dining-greenparrot.jpg',
                'Rick’s Café on the Placencia Sidewalk offers a variety of dishes in a casual setting. A 20-minute drive south from La Vida Belize, it’s a great spot for a relaxed meal.'),
            $this->venue('dining', 50, 'The Bonefish Grill', 'the-bonefish-grill', 'assets/attractions/dining-bonefish.jpg',
                'Located at Maya Beach at the Azur del Mar, just a 5-minute drive south of Placencia, The Bonefish Grill is known for its friendly service and superb food. It’s a great choice for a delicious meal close to your stay.'),
            $this->venue('dining', 60, 'Rum Bum’s', 'rum-bums', 'assets/attractions/dining-rumfish.jpg',
                'For a taste of local Belizean dishes, head to Rum Bum’s. This spot offers a variety of traditional flavors in a relaxed and friendly atmosphere.'),
            $this->venue('dining', 70, 'Ceiba Beach Resort', 'ceiba-beach-resort', 'assets/attractions/dining.jpg',
                'Ceiba Beach Resort offers elevated local flavors of Belize in an elegant setting. Enjoy a sophisticated dining experience with dishes that highlight the best of Belizean cuisine.'),
            $this->venue('dining', 80, 'The Green Parrot', 'the-green-parrot', 'assets/attractions/dining-greenparrot.jpg',
                'Close to La Vida Belize and just a 5-minute drive south to Maya Beach, The Green Parrot features a nice setting and excellent food. It\'s a great place to enjoy a meal with a view.',
                '<p class="spot-meta"><a href="tel:+5015234321">Phone 523-4321</a></p>', '523-4321'),
            $this->venue('dining', 90, 'Inky’s Grill', 'inkys-grill', 'assets/attractions/dining-chachis.jpeg',
                'Inky’s Grill offers superb food in a lovely setting, just about 6 miles south of Placencia. Don’t miss out on their Tutti-Frutti Gelato for a sweet treat after your meal.'),
            $this->venue('dining', 100, 'Jaguar Lanes', 'jaguar-lanes', 'assets/attractions/dining-bonefish.jpg',
                'Located a 20-minute drive south of Placencia, Jaguar Lanes is known for its great pizza and a variety of other dishes. This casual spot is perfect for a laid-back meal with family and friends.'),

            $this->venue('adventures', 10, 'Bunches of Fun Banana Farm Tour', 'bunches-of-fun-banana-farm-tour', 'assets/attractions/adventures.jpg',
                'A quick 10-minute drive north from Placencia, the Bunches of Fun Banana Farm Tour offers an educational and entertaining look at banana farming in Belize. This tour is perfect for families and anyone interested in agriculture.'),
            $this->venue('adventures', 20, 'River Tubing & Hiking at Cockscomb Wildlife Sanctuary', 'river-tubing-hiking-cockscomb', 'assets/attractions/adventures-tubing.jpg',
                'Just a 20-minute drive from Placencia, Cockscomb Wildlife Sanctuary offers fantastic river tubing and hiking experiences. Explore the sanctuary\'s diverse wildlife and scenic trails for a memorable adventure.'),
            $this->venue('adventures', 30, 'Ziplining', 'ziplining', 'assets/attractions/adventures-zip.jpg',
                'Experience the thrill of ziplining at Bocawina National Park and Maya King, both within a 25-minute drive of Placencia. These ziplining adventures offer an exhilarating way to see the beautiful Belizean jungle from above.'),
            $this->venue('adventures', 40, 'Belize Zoo', 'belize-zoo', 'assets/attractions/adventures-zoo.jpg',
                'Located a 2½ hour drive north from Placencia, the Belize Zoo offers a wonderful opportunity to see a variety of native animals in a natural setting. This family-friendly attraction is a great day trip for animal lovers of all ages.'),
            $this->venue('adventures', 50, 'Monkey River Eco Tours', 'monkey-river-eco-tours', 'assets/attractions/adventures-tubing.jpg',
                'Explore the natural beauty of Monkey River with an eco-tour that launches from Placencia. These tours provide an up-close look at the local wildlife and lush landscapes.',
                '<p class="spot-meta">To book a tour, call <a href="tel:+5016016359">+501-601-6359</a>.</p>', '601-6359'),
            $this->venue('adventures', 60, 'Belize Spice Farm & Botanical Gardens', 'belize-spice-farm-botanical-gardens', 'assets/attractions/adventures.jpg',
                'Located just 45 minutes from Placencia towards Punta Gorda, the Belize Spice Farm & Botanical Gardens offers a fascinating tour of exotic spices and beautiful gardens.',
                '<p class="spot-meta">For more information, call <a href="tel:+5016701338">+501-670-1338</a>.</p>', '670-1338'),
            $this->venue('adventures', 70, 'Scuba Diving with Splash Dive Center', 'scuba-diving-splash-dive-center', 'assets/attractions/adventures-zip.jpg',
                'Splash Dive Center offers an array of scuba diving experiences, launching from Placencia. Whether you\'re a beginner or an experienced diver, Splash Dive Center provides professional and safe diving excursions.',
                '<p class="spot-meta">Call <a href="tel:+5015233080">+501-523-3080</a>.</p>', '523-3080'),
            $this->venue('adventures', 80, 'Snorkeling with Kendis', 'snorkeling-with-kendis', 'assets/attractions/adventures-tubing.jpg',
                'Join Kendis for a day of snorkeling around the beautiful reefs and islands of Belize. Launching from the Placencia pier, these trips offer an unforgettable experience of Belize\'s marine life.',
                '<p class="spot-meta">Call Kendis at <a href="tel:+5016118216">+501-611-8216</a>.</p>', '611-8216'),
            $this->venue('adventures', 90, 'Fishing with Brad Bowman', 'fishing-with-brad-bowman', 'assets/attractions/adventures.jpg',
                'Experience fishing on the flats, deep-sea, or reef with expert guide Brad Bowman. Launching from Placencia, Brad offers a variety of fishing experiences tailored to both novice and experienced anglers.',
                '<p class="spot-meta">Call Brad at <a href="tel:+5016252030">+501-625-2030</a>.</p>', '625-2030'),
            $this->venue('adventures', 100, 'DTourz', 'dtourz', 'assets/attractions/adventures-zoo.jpg',
                'DTourz, operated by Doyle, provides a range of inland and sea excursions, picking you up directly from your property.',
                '<p class="spot-meta">Call Doyle at <a href="tel:+5016002318">+501-600-2318</a>.</p>', '600-2318'),

            $this->venue('taxi', 10, 'Hola Hola Taxi Cameron', 'hola-hola-taxi-cameron', 'assets/attractions/taxi.jpg',
                'Hola Hola Taxi, operated by Cameron, is a reliable and efficient taxi service in Placencia. Known for punctuality and excellent service, Cameron ensures a comfortable and safe ride to your destination. Whether you need a ride to a local attraction or transportation for a longer journey, Hola Hola Taxi is at your service.',
                '<p class="spot-meta">Contact Cameron at <a href="tel:+5016687863">668-7863</a>.</p>', '668-7863'),

            $this->venue('golf-cart', 10, 'Placencia Golf Cart Rentals', 'placencia-golf-cart-rentals', 'assets/attractions/golf.jpg',
                'Placencia Golf Cart Rentals offers a convenient and fun way to explore Placencia and its surroundings. Their fleet of golf carts is well-maintained and perfect for sightseeing or running errands around town.',
                '<p class="spot-meta">Call <a href="tel:+5016156404">615-6404</a>.</p>', '615-6404'),
            $this->venue('golf-cart', 20, 'Barefoot Rentals', 'barefoot-rentals', 'assets/attractions/golf-2.jpg',
                'Barefoot Rentals provides high-quality golf cart rentals, ideal for leisurely exploring Placencia. Their selection of golf carts is well-maintained, ensuring a reliable and enjoyable experience.',
                '<p class="spot-meta">Call <a href="tel:+5015233066">523-3066</a> or visit <a href="https://barefootrentalsbelize.com/" rel="noopener">Barefoot Rentals</a>.</p>',
                '523-3066', 'https://barefootrentalsbelize.com/', 'external'),
            $this->venue('golf-cart', 30, 'Captain Jak’s', 'captain-jaks', 'assets/attractions/golf.jpg',
                'Captain Jak\'s offers top-notch golf cart rentals, perfect for navigating the scenic beauty of Placencia. Their well-maintained fleet ensures a comfortable and convenient ride, allowing you to explore the area at your own pace.',
                '<p class="spot-meta">Call <a href="tel:+5016286447">628-6447</a>.</p>', '628-6447'),

            $this->venue('car-rental', 10, 'Barefoot Car Rental', 'barefoot-car-rental', 'assets/attractions/car.jpg',
                'Barefoot Car Rental, based in Placencia, is known for its friendly service and extensive selection of vehicles. Whether you need a car for a short trip around town or a rugged vehicle for exploring off-the-beaten-path locations, Barefoot Car Rental has the perfect option for you.',
                '<p class="spot-meta">Contact Megs at <a href="tel:+5015233066">523-3066</a> or visit <a href="https://barefootrentalsbelize.com/" rel="noopener">Barefoot Car Rental</a>.</p>',
                '523-3066', 'https://barefootrentalsbelize.com/', 'external'),
            $this->venue('car-rental', 20, 'Budget Placencia and Belize City', 'budget-placencia-belize-city', 'assets/attractions/car-2.jpg',
                'Budget Car Rental offers convenient locations in both Placencia and Belize City, providing a variety of vehicles to meet your transportation needs. Known for their competitive pricing and excellent customer service, Budget ensures a hassle-free rental experience.',
                '<p class="spot-meta">Contact Neimah at <a href="tel:+5016319777">631-9777</a> or visit <a href="https://www.budget-belize.com/" rel="noopener">Budget Car Rental</a>.</p>',
                '631-9777', 'https://www.budget-belize.com/', 'external'),
            $this->venue('car-rental', 30, 'Crystal Auto Rental', 'crystal-auto-rental', 'assets/attractions/car.jpg',
                'Crystal Auto Rental is a reliable choice for those seeking car rental services in Belize City. With a wide selection of vehicles, they cater to city driving and exploring farther afield.',
                '<p class="spot-meta">Belize <a href="tel:+5012231600">501-223-1600</a> · US <a href="tel:+19363071325">936-307-1325</a> · WhatsApp <a href="https://wa.me/5016707617">501-670-7617</a> · <a href="https://www.crystal-belize.com/" rel="noopener">crystal-belize.com</a></p>',
                '223-1600', 'https://www.crystal-belize.com/', 'external'),
        ];
    }

    private function venue(
        string $categorySeo,
        int $ordering,
        string $name,
        string $seo,
        string $image,
        string $description,
        ?string $metaHtml = null,
        ?string $mobile = null,
        ?string $mapUrl = null,
        string $type = 'internal'
    ): array {
        return [
            'category_seo' => $categorySeo,
            'name' => $name,
            'seo_url' => $seo,
            'image' => $image,
            'description' => $description,
            'longDescription' => $metaHtml,
            'mobile' => $mobile,
            'map_url' => $mapUrl,
            'ordering' => $ordering,
            'type' => $type,
        ];
    }
}
