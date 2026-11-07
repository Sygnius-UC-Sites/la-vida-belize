<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Upserts Casa, Casita, and Studio from content/listings/listings.json.
 *
 * Run after the SQL dump is imported:
 *   php artisan db:seed --class=LaVidaPropertiesSeeder
 *
 * Not called from DatabaseSeeder — the clone ships that file empty.
 */
class LaVidaPropertiesSeeder extends Seeder
{
    private const SOURCE = 'hostaway';

    public function run(): void
    {
        $jsonPath = $this->listingsJsonPath();
        if (!is_file($jsonPath)) {
            throw new \RuntimeException("Listings JSON not found: {$jsonPath}");
        }

        $payload = json_decode((string) file_get_contents($jsonPath), true);
        if (!is_array($payload) || empty($payload['properties'])) {
            throw new \RuntimeException("Listings JSON has no properties array: {$jsonPath}");
        }

        foreach (['properties', 'property_galleries', 'property_amenity_groups', 'property_amenities', 'property_reviews'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new \RuntimeException("Required table missing: {$table}");
            }
        }

        $contentRoot = dirname($jsonPath);

        DB::transaction(function () use ($payload, $contentRoot) {
            foreach ($payload['properties'] as $index => $listing) {
                $this->upsertListing((array) $listing, $contentRoot, $index + 1);
            }
        });
    }

    private function upsertListing(array $listing, string $contentRoot, int $order): void
    {
        $seo = (string) ($listing['seo_url'] ?? '');
        if ($seo === '') {
            throw new \RuntimeException('Listing is missing seo_url');
        }

        $copied = $this->copyImages($listing, $contentRoot);
        $propertyId = $this->upsertPropertyRow($listing, $copied['feature'], $order);
        $this->replaceGallery($propertyId, $copied['gallery']);
        $this->replaceAmenities($propertyId, $listing);
        $this->replaceReviews($propertyId, $listing);

        $this->info(sprintf(
            'Seeded %s (id %d): %d gallery, %d amenities, %d reviews',
            $seo,
            $propertyId,
            count($copied['gallery']),
            count($listing['amenities'] ?? []),
            count($listing['reviews'] ?? [])
        ));
    }

    /** @return array{feature: ?string, gallery: list<array{relative: string, caption: ?string, category: ?string, sorting: int}>} */
    private function copyImages(array $listing, string $contentRoot): array
    {
        $seo = (string) $listing['seo_url'];
        $key = (string) ($listing['key'] ?? '');
        $destDir = public_path('uploads/properties/' . $seo);
        if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            throw new \RuntimeException("Could not create upload dir: {$destDir}");
        }

        $featureRelative = null;
        $featureSrc = $contentRoot . DIRECTORY_SEPARATOR . $key . DIRECTORY_SEPARATOR . 'feature.jpg';
        if (is_file($featureSrc)) {
            $dest = $destDir . DIRECTORY_SEPARATOR . 'feature.jpg';
            if (!copy($featureSrc, $dest)) {
                throw new \RuntimeException("Failed to copy feature image: {$featureSrc}");
            }
            $featureRelative = 'uploads/properties/' . $seo . '/feature.jpg';
        }

        $gallery = [];
        foreach ((array) ($listing['gallery'] ?? []) as $photo) {
            $filename = (string) ($photo['filename'] ?? '');
            if ($filename === '') {
                continue;
            }
            $src = $contentRoot . DIRECTORY_SEPARATOR . $key . DIRECTORY_SEPARATOR . 'gallery' . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($src)) {
                throw new \RuntimeException("Gallery file missing: {$src}");
            }
            $dest = $destDir . DIRECTORY_SEPARATOR . $filename;
            if (!copy($src, $dest)) {
                throw new \RuntimeException("Failed to copy gallery image: {$src}");
            }
            $caption = trim((string) ($photo['caption'] ?? ''));
            $gallery[] = [
                'relative' => 'uploads/properties/' . $seo . '/' . $filename,
                'caption'  => $caption !== '' ? Str::limit($caption, 250, '') : null,
                'category' => $this->guessGalleryCategory($caption, $filename),
                'sorting'  => (int) ($photo['sort_order'] ?? (count($gallery) + 1)),
            ];
        }

        return ['feature' => $featureRelative, 'gallery' => $gallery];
    }

    private function upsertPropertyRow(array $listing, ?string $featureRelative, int $order): int
    {
        $overview = (string) ($listing['overview'] ?? '');
        $short = (string) ($listing['short_description'] ?? '');
        $name = (string) ($listing['name'] ?? $listing['listing_name'] ?? 'La Vida Belize');
        $listingName = (string) ($listing['listing_name'] ?? $name);
        $seo = (string) $listing['seo_url'];
        $location = (string) ($listing['location'] ?? '');
        $sleeps = (int) ($listing['sleeps'] ?? 0);
        $bedrooms = (int) ($listing['bedrooms'] ?? 0);
        $beds = (int) ($listing['beds'] ?? 0);
        $baths = (int) ($listing['baths'] ?? 0);
        $areaSqft = $listing['area_sqft'] ?? null;
        $rating = $listing['rating'] ?? null;
        $reviewCount = (int) ($listing['counts']['reviews'] ?? ($listing['listed_review_count'] ?? 0));
        $hostawayId = (string) ($listing['hostaway_listing_id'] ?? '');
        $amenityNames = array_values(array_filter(array_map(
            fn ($a) => (string) ($a['name'] ?? ''),
            (array) ($listing['amenities'] ?? [])
        )));

        $reviewText = null;
        if ($rating !== null && $reviewCount > 0) {
            $reviewText = sprintf(
                'Rated ★ %s from %d guest review%s.',
                number_format((float) $rating, 2),
                $reviewCount,
                $reviewCount === 1 ? '' : 's'
            );
        }

        $key = (string) ($listing['key'] ?? '');
        $queenBeds = in_array($key, ['casita', 'studio'], true) ? 1 : 0;

        $row = [
            'name'                          => $name,
            'heading'                       => $listingName,
            'seo_url'                       => $seo,
            'type'                          => $key === 'casa' ? 'house' : 'property',
            'address'                       => $location !== '' ? $location : null,
            'short_description'             => $short !== '' ? $short : Str::limit($overview, 500, '…'),
            'long_description'              => $overview !== '' ? $overview : null,
            'description'                   => $overview !== '' ? $overview : null,
            'detail_about_text'             => $overview !== '' ? $overview : null,
            'detail_about_heading'          => 'About this stay',
            'detail_amenities_heading'      => 'What this place offers',
            'detail_offcanvas_reviews_heading' => 'Guest reviews',
            'detail_meta_location'          => $location !== '' ? $location : null,
            'detail_meta_rating'            => $rating !== null ? (string) $rating : null,
            'detail_category_heading'       => $listingName,
            'subtitle'                      => $short !== '' ? Str::limit($short, 250, '') : null,
            'bedroom'                       => $bedrooms,
            'bathroom'                      => $baths,
            'full_bath'                     => $baths,
            'beds'                          => $beds,
            'sleeps'                        => $sleeps,
            'no_of_guest'                   => $sleeps > 0 ? (string) $sleeps : null,
            'area'                          => $areaSqft ? ((int) $areaSqft) . ' sq ft' : null,
            'standard_rate'                 => isset($listing['standard_rate']) ? (float) $listing['standard_rate'] : null,
            'rating'                        => $rating !== null ? (string) $rating : null,
            'review_text'                   => $reviewText,
            'feature_image'                 => $featureRelative,
            'banner_image'                  => $featureRelative,
            'api_id'                        => $hostawayId !== '' ? $hostawayId : null,
            'api_pms'                       => $hostawayId !== '' ? 'hostaway' : null,
            'website'                       => $listing['source_url'] ?? null,
            'airbnb_link'                   => $listing['source_url'] ?? null,
            'status'                        => 'true',
            'property_status'               => 'published',
            'is_home'                       => 'true',
            'is_feature'                    => 'true',
            'ordering'                      => $order,
            'instant_booking_button'        => 'true',
            'bedroom_text'                  => $bedrooms > 0 ? $bedrooms . ' bedroom' . ($bedrooms === 1 ? '' : 's') : null,
            'bathroom_text'                 => $baths > 0 ? $baths . ' bath' . ($baths === 1 ? '' : 's') : null,
            'beds_text'                     => $beds > 0 ? $beds . ' bed' . ($beds === 1 ? '' : 's') : null,
            'sleeps_text'                   => $sleeps > 0 ? 'Sleeps ' . $sleeps : null,
            'queen_beds'                    => $queenBeds,
            'property_view'                 => $this->detectPropertyView($amenityNames),
            'accepts_pets'                  => $this->amenityContains($amenityNames, ['pet-friendly', 'pets allowed']) ? 1 : 0,
            'wheelchair_accessible'         => $this->amenityContains($amenityNames, ['wheelchair']) ? 1 : 0,
            'smoking_allowed'               => $this->amenityContains($amenityNames, ['smoking allowed']) ? 1 : 0,
            'scraped_amenities_raw'         => !empty($amenityNames) ? json_encode($amenityNames, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'card_amenities'                => json_encode($this->cardAmenityNames($amenityNames), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'meta_title'                    => $listingName,
            'meta_description'              => $short !== '' ? Str::limit($short, 160, '') : Str::limit($overview, 160, ''),
            'updated_at'                    => now(),
        ];

        $allowed = array_flip(Schema::getColumnListing('properties'));
        $row = array_intersect_key($row, $allowed);

        $existingId = DB::table('properties')->where('seo_url', $seo)->value('id');
        if ($existingId) {
            DB::table('properties')->where('id', $existingId)->update($row);
            return (int) $existingId;
        }

        $row['created_at'] = now();
        return (int) DB::table('properties')->insertGetId($row);
    }

    /** @param list<array{relative: string, caption: ?string, category: ?string, sorting: int}> $gallery */
    private function replaceGallery(int $propertyId, array $gallery): void
    {
        DB::table('property_galleries')->where('property_id', (string) $propertyId)->delete();
        if ($gallery === []) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($gallery as $photo) {
            $rows[] = [
                'property_id' => (string) $propertyId,
                'image'       => $photo['relative'],
                'caption'     => $photo['caption'],
                'category'    => $photo['category'],
                'sorting'     => $photo['sorting'],
                'status'      => 'true',
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }
        DB::table('property_galleries')->insert($rows);
    }

    private function replaceAmenities(int $propertyId, array $listing): void
    {
        $groupIds = DB::table('property_amenity_groups')
            ->where('property_id', (string) $propertyId)
            ->pluck('id');
        if ($groupIds->isNotEmpty()) {
            DB::table('property_amenities')->whereIn('property_amenity_id', $groupIds)->delete();
            DB::table('property_amenity_groups')->where('property_id', (string) $propertyId)->delete();
        }

        $groups = $this->groupAmenities((array) ($listing['amenities'] ?? []));
        if ($groups === []) {
            return;
        }

        $highlightLimit = 6;
        try {
            $highlightLimit = (int) (\Helper::getCardHighlightLimit() ?? 6);
        } catch (\Throwable $e) {
            $highlightLimit = 6;
        }

        $now = now();
        $highlighted = 0;
        $groupSort = 0;
        foreach ($groups as $groupName => $items) {
            $groupId = DB::table('property_amenity_groups')->insertGetId([
                'property_id' => (string) $propertyId,
                'name'        => Str::limit((string) $groupName, 250, ''),
                'status'      => 'true',
                'sorting'     => $groupSort++,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $rows = [];
            foreach (array_values($items) as $j => $item) {
                $isHighlight = ($j === 0 && $highlighted < $highlightLimit) ? 1 : 0;
                if ($isHighlight) {
                    $highlighted++;
                }
                $rows[] = [
                    'property_amenity_id' => (string) $groupId,
                    'name'                => Str::limit((string) $item['name'], 250, ''),
                    'icon'                => $item['icon'] !== '' ? $item['icon'] : null,
                    'status'              => 'true',
                    'sorting'             => $j,
                    'is_card_highlight'   => $isHighlight,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }
            if ($rows !== []) {
                DB::table('property_amenities')->insert($rows);
            }
        }
    }

    private function replaceReviews(int $propertyId, array $listing): void
    {
        if (Schema::hasTable('property_reviews')) {
            DB::table('property_reviews')
                ->where('property_id', $propertyId)
                ->where('source', self::SOURCE)
                ->delete();
        }

        $reviews = (array) ($listing['reviews'] ?? []);
        if ($reviews === []) {
            return;
        }

        $now = now();
        $hostawayId = (string) ($listing['hostaway_listing_id'] ?? '');
        $rows = [];
        foreach ($reviews as $review) {
            $review = (array) $review;
            $name = trim((string) ($review['guest_name'] ?? ''));
            $comment = (string) ($review['text'] ?? '');
            $hash = sha1(strtolower($name) . '|' . trim($comment));
            $rating = isset($review['rating']) ? (int) round((float) $review['rating']) : null;
            if ($rating !== null) {
                $rating = max(1, min(5, $rating));
            }

            $rows[] = [
                'property_id'          => $propertyId,
                'source'               => self::SOURCE,
                'external_review_id'   => 'hostaway-' . $hostawayId . '-' . substr($hash, 0, 16),
                'content_hash'         => $hash,
                'reviewer_name'        => $name !== '' ? $name : 'Guest',
                'reviewer_photo_url'   => null,
                'reviewer_photo_local' => null,
                'reviewer_location'    => null,
                'rating'               => $rating,
                'comment'              => $comment !== '' ? $comment : null,
                'comment_translated'   => null,
                'language'             => 'en',
                'response_text'        => null,
                'response_date'        => null,
                'review_date'          => $this->parseReviewDate($review['date_iso'] ?? null, $review['date'] ?? null),
                'status'               => 'published',
                'raw_payload'          => json_encode($review, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at'           => $now,
                'updated_at'           => $now,
            ];

            if (count($rows) >= 50) {
                DB::table('property_reviews')->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            DB::table('property_reviews')->insert($rows);
        }
    }

    /** @param list<array<string, mixed>> $amenities */
    private function groupAmenities(array $amenities): array
    {
        $library = $this->amenityLibrary();
        $lookup = [];
        foreach ($library as $groupName => $items) {
            foreach ($items as $item) {
                $lookup[$this->norm((string) $item['name'])] = [
                    'group' => $groupName,
                    'name'  => $item['name'],
                    'icon'  => $item['icon'],
                ];
            }
        }

        $grouped = [];
        foreach ($library as $groupName => $_) {
            $grouped[$groupName] = [];
        }
        $grouped['What this place offers'] = [];

        foreach ($amenities as $amenity) {
            $rawName = trim((string) ($amenity['name'] ?? ''));
            if ($rawName === '') {
                continue;
            }
            $mapped = $this->mapAmenity($rawName, $lookup);
            $grouped[$mapped['group']][] = [
                'name' => $rawName,
                'icon' => $mapped['icon'],
            ];
        }

        return array_filter($grouped, fn ($items) => $items !== []);
    }

    /** @param array<string, array{group: string, name: string, icon: string}> $lookup */
    private function mapAmenity(string $name, array $lookup): array
    {
        $norm = $this->norm($name);
        if (isset($lookup[$norm])) {
            return $lookup[$norm];
        }

        foreach ($this->amenitySynonyms() as $alias => $canonicalNorm) {
            if ($norm === $this->norm($alias) && isset($lookup[$canonicalNorm])) {
                return $lookup[$canonicalNorm];
            }
        }

        foreach ($this->amenityKeywordGroups() as $group => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($norm, $this->norm($needle))) {
                    return ['group' => $group, 'name' => $name, 'icon' => $this->iconForKeyword($needle)];
                }
            }
        }

        return ['group' => 'What this place offers', 'name' => $name, 'icon' => ''];
    }

    private function amenityLibrary(): array
    {
        try {
            $lib = \Helper::getAmenityLibrary();
            if (is_array($lib) && $lib !== []) {
                return $lib;
            }
        } catch (\Throwable $e) {
            // fall through to the compact copy below
        }

        return [
            'Bathroom' => [
                ['name' => 'Hair dryer', 'icon' => 'wind'],
                ['name' => 'Shower', 'icon' => 'shower'],
                ['name' => 'Shampoo', 'icon' => 'pump-soap'],
                ['name' => 'Hot water', 'icon' => 'temperature-high'],
            ],
            'Bedroom & laundry' => [
                ['name' => 'Washer', 'icon' => 'shirt'],
                ['name' => 'Dryer', 'icon' => 'wind'],
                ['name' => 'Hangers', 'icon' => 'shirt'],
                ['name' => 'Iron', 'icon' => 'shirt'],
            ],
            'Entertainment' => [
                ['name' => 'TV', 'icon' => 'tv'],
            ],
            'Heating & cooling' => [
                ['name' => 'Air conditioning', 'icon' => 'snowflake'],
                ['name' => 'Ceiling fan', 'icon' => 'fan'],
            ],
            'Home safety' => [
                ['name' => 'Smoke alarm', 'icon' => 'bell'],
                ['name' => 'Carbon monoxide alarm', 'icon' => 'bell'],
                ['name' => 'Fire extinguisher', 'icon' => 'fire-extinguisher'],
                ['name' => 'First aid kit', 'icon' => 'kit-medical'],
            ],
            'Internet & office' => [
                ['name' => 'Wi-Fi', 'icon' => 'wifi'],
            ],
            'Kitchen & dining' => [
                ['name' => 'Kitchen', 'icon' => 'kitchen-set'],
                ['name' => 'Refrigerator', 'icon' => 'kitchen-set'],
                ['name' => 'Coffee maker', 'icon' => 'mug-hot'],
                ['name' => 'Wine glasses', 'icon' => 'wine-glass'],
            ],
            'Outdoor' => [
                ['name' => 'Pool', 'icon' => 'swimming-pool'],
                ['name' => 'Hammock', 'icon' => 'tree'],
                ['name' => 'Kayak', 'icon' => 'water'],
            ],
            'Parking & facilities' => [
                ['name' => 'Free parking on premises', 'icon' => 'car'],
            ],
        ];
    }

    /** Hostaway labels → Helper library canonical names (normalized later). */
    private function amenitySynonyms(): array
    {
        return [
            'Internet' => 'wifi',
            'Wireless' => 'wifi',
            'Free WiFi' => 'wifi',
            'WiFi speed (50+ Mbps)' => 'wifi',
            'Hair Dryer' => 'hairdryer',
            'Washing Machine' => 'washer',
            'Smoke detector' => 'smokealarm',
            'Carbon Monoxide Detector' => 'carbonmonoxidealarm',
            'Fire Extinguisher' => 'fireextinguisher',
            'Essentials' => 'essentialstowelssheetssoap',
            'Linens' => 'bedlinens',
            'Towels' => 'essentialstowelssheetssoap',
            'Swimming pool' => 'pool',
            'Private pool' => 'pool',
            'Coffee' => 'coffeeprovided',
            'Coffee/tea maker' => 'coffeemaker',
            'Baby crib' => 'crib',
            'Childrens dinnerware' => 'childrensdinnerware',
            'Extra pillows and blankets' => 'extrapillowsblankets',
            'Drying rack for clothing' => 'dryingrack',
            'Laptop Friendly workspace' => 'laptopfriendlyworkspace',
            'Long term stays allowed' => 'longtermstaysallowed',
            'Luggage dropoff allowed' => 'luggagedropoffallowed',
            'Garden or backyard' => 'backyard',
            'Outdoor dining' => 'outdoordiningarea',
            'Outdoor grill' => 'bbqgrill',
            'Barbeque utensils' => 'barbecueutensils',
            'Free parking' => 'freeparkingonpremises',
            'Private entrance' => 'privateentrance',
            'Single level home' => 'singlelevelhome',
            'Pack n play travel crib' => 'packnplaytravelcrib',
            'Ocean front' => 'waterfront',
            'Ocean view' => 'oceanview',
            'Beach' => 'beachfront',
            'Bicycle' => 'bikes',
            'Kayak canoe' => 'kayak',
            'Kayaking' => 'kayak',
            'Smart TV' => 'tv',
            'Portable fans' => 'portablefan',
            'Electric kettle' => 'hotwaterkettle',
            'Kitchen utensils' => 'cookingbasics',
            'Shower gel' => 'bodysoap',
            'Balcony' => 'patioorbalcony',
            'Desk chair' => 'dedicatedworkspace',
            'Dining area' => 'diningtable',
        ];
    }

    private function amenityKeywordGroups(): array
    {
        return [
            'Bathroom' => ['shampoo', 'conditioner', 'soap', 'shower', 'toilet', 'bath', 'hot water', 'body soap'],
            'Bedroom & laundry' => ['hanger', 'iron', 'linen', 'pillow', 'clothing', 'washer', 'dryer', 'towel'],
            'Entertainment' => ['tv', 'game', 'video', 'board game'],
            'Family' => ['baby', 'child', 'infant', 'crib', 'high chair', 'stair gate'],
            'Heating & cooling' => ['air conditioning', 'ceiling fan', 'portable fan', 'fan'],
            'Home safety' => ['smoke', 'carbon', 'fire', 'first aid', 'deadbolt', 'security', 'safe'],
            'Internet & office' => ['wifi', 'internet', 'wireless', 'laptop', 'desk'],
            'Kitchen & dining' => ['kitchen', 'oven', 'stove', 'fridge', 'refrigerator', 'coffee', 'blender', 'toaster', 'microwave', 'freezer', 'wine', 'spice', 'baking', 'kettle', 'cooking'],
            'Location features' => ['beach', 'ocean', 'waterfront', 'mountain view'],
            'Outdoor' => ['pool', 'kayak', 'bicycle', 'hammock', 'grill', 'outdoor', 'garden', 'balcony', 'dock', 'pier', 'snorkel', 'fish', 'boat', 'swim', 'sunlounger'],
            'Parking & facilities' => ['parking', 'car recommended', 'private entrance', 'private living'],
            'Services' => ['cleaning', 'long term', 'luggage', 'contactless', 'massage', 'romantic', 'family', 'shopping', 'eco'],
        ];
    }

    private function iconForKeyword(string $needle): string
    {
        $map = [
            'wifi' => 'wifi', 'internet' => 'wifi', 'pool' => 'swimming-pool', 'kayak' => 'water',
            'beach' => 'umbrella-beach', 'ocean' => 'water', 'kitchen' => 'kitchen-set',
            'tv' => 'tv', 'parking' => 'car', 'baby' => 'baby', 'grill' => 'fire-burner',
            'hammock' => 'tree', 'bicycle' => 'bicycle', 'fan' => 'fan', 'coffee' => 'mug-hot',
        ];
        $norm = $this->norm($needle);
        foreach ($map as $key => $icon) {
            if (str_contains($norm, $this->norm($key))) {
                return $icon;
            }
        }
        return '';
    }

    /** @param list<string> $amenityNames */
    private function cardAmenityNames(array $amenityNames): array
    {
        $preferred = [
            'Beach', 'Ocean front', 'Ocean view', 'Swimming pool', 'Private pool', 'Pool',
            'Kitchen', 'Air conditioning', 'Wireless', 'Free WiFi', 'Internet',
            'Kayak canoe', 'Private dock', 'Free parking',
        ];
        $have = [];
        foreach ($amenityNames as $name) {
            $have[$this->norm($name)] = $name;
        }
        $out = [];
        foreach ($preferred as $name) {
            $key = $this->norm($name);
            if (isset($have[$key])) {
                $out[] = $have[$key];
            }
            if (count($out) >= 6) {
                break;
            }
        }
        if (count($out) < 6) {
            foreach ($amenityNames as $name) {
                if (!in_array($name, $out, true)) {
                    $out[] = $name;
                }
                if (count($out) >= 6) {
                    break;
                }
            }
        }
        return $out;
    }

    /** @param list<string> $amenityNames */
    private function detectPropertyView(array $amenityNames): ?string
    {
        foreach (['Ocean view', 'Ocean front', 'Beach', 'Waterfront', 'Mountain view'] as $label) {
            if ($this->amenityContains($amenityNames, [$label])) {
                return $label;
            }
        }
        return null;
    }

    /** @param list<string> $amenityNames @param list<string> $needles */
    private function amenityContains(array $amenityNames, array $needles): bool
    {
        $blob = $this->norm(implode(' ', $amenityNames));
        foreach ($needles as $needle) {
            if (str_contains($blob, $this->norm($needle))) {
                return true;
            }
        }
        return false;
    }

    private function guessGalleryCategory(string $caption, string $filename): ?string
    {
        $hay = strtolower($caption . ' ' . $filename);
        $map = [
            'pool' => 'pool', 'palapa' => 'pool',
            'kitchen' => 'kitchen',
            'bath' => 'bathroom', 'shower' => 'bathroom',
            'bedroom' => 'bedroom', 'bed' => 'bedroom',
            'living' => 'living_room',
            'dining' => 'dining',
            'beach' => 'view', 'sunrise' => 'view', 'village' => 'view', 'pier' => 'view', 'dock' => 'view', 'view' => 'view',
            'parking' => 'exterior', 'entrance' => 'exterior',
            'deck' => 'outdoor', 'outdoor' => 'outdoor',
        ];
        foreach ($map as $needle => $category) {
            if (str_contains($hay, $needle)) {
                return $category;
            }
        }
        return 'other';
    }

    private function parseReviewDate($iso, $label): ?string
    {
        foreach ([$iso, $label] as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            try {
                $parsed = Carbon::parse($value);
                if (preg_match('/^\d{4}-\d{2}$/', $value)) {
                    $parsed = $parsed->startOfMonth();
                }
                return $parsed->toDateTimeString();
            } catch (\Throwable $e) {
                continue;
            }
        }
        return null;
    }

    private function listingsJsonPath(): string
    {
        $candidates = [
            public_path('content/listings/listings.json'),
            dirname(public_path()) . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'listings' . DIRECTORY_SEPARATOR . 'listings.json',
            base_path('../content/listings/listings.json'),
            base_path('../../content/listings/listings.json'),
            dirname(base_path(), 2) . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'listings' . DIRECTORY_SEPARATOR . 'listings.json',
        ];
        foreach ($candidates as $path) {
            $real = realpath($path);
            if ($real && is_file($real)) {
                return $real;
            }
        }
        return $candidates[0];
    }

    private function norm(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
    }

    private function info(string $message): void
    {
        if ($this->command) {
            $this->command->info($message);
        }
    }
}
