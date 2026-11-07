<?php

namespace App\Services\Scraping\Mappers;

use Illuminate\Support\Str;

/**
 * Maps `tri_angle/airbnb-scraper` dataset items to property fields.
 *
 * The Actor's output is roughly:
 *   {
 *     "url": "...",
 *     "name": "Cosy 2BR cabin near the lake",
 *     "description": "...",
 *     "address": "Lake Tahoe, CA",
 *     "lat": 39.0..., "lng": -120.0...,
 *     "personCapacity": 4,
 *     "bedrooms": 2,
 *     "bathrooms": 1.5,
 *     "beds": 3,
 *     "price": { "rate": 199, "currency": "USD" },
 *     "photos": [{ "large": "https://..." }, ...],
 *     "amenities": [{ "title": "Wifi" }, ...]
 *   }
 *
 * Schemas drift between Actor versions; mapper is defensive — every read uses
 * `??` chains so a missing field doesn't crash the import.
 */
class AirbnbMapper implements PlatformMapper
{
    public function buildReviewsActorInput(string $sourceUrl, int $maxReviews): ?array
    {
        if ($maxReviews <= 0) return null;
        // tri_angle/airbnb-reviews-scraper input shape. Sending multiple aliases
        // for forward-compatibility across Actor revisions.
        return [
            'startUrls'           => [['url' => $sourceUrl]],
            'urls'                => [$sourceUrl],
            'maxReviews'          => $maxReviews,
            'maxReviewsPerListing'=> $maxReviews,
        ];
    }

    public function mapReviewItem(array $r): ?array
    {
        // The dedicated reviews Actor outputs one review per item — same field
        // shape as elements of the listing Actor's nested `reviews` array.
        // Re-use the same defensive extraction by wrapping and delegating.
        $extracted = $this->mapReviews(['reviews' => [$r]]);
        return $extracted[0] ?? null;
    }

    public function buildActorInput(string $sourceUrl, int $maxReviews = 0): array
    {
        // Input shape for tri_angle/airbnb-rooms-urls-scraper. The Actor accepts
        // either `startUrls` (array of {url}) or `propertyUrls` (array of strings)
        // depending on its current revision; sending both keeps us forward-compatible.
        return [
            'startUrls'      => [['url' => $sourceUrl]],
            'propertyUrls'   => [$sourceUrl],
            'currency'       => 'USD',
            'maxItems'       => 1,
            'maxReviews'     => $maxReviews,
            'calendarMonths' => 0,           // skip calendar — booking calendar is managed via iCal sync
            'includeReviews' => $maxReviews > 0,
        ];
    }

    public function mapToProperty(array $item): array
    {
        // ---- title / name ----
        $name = (string) (
            $this->dig($item, ['name', 'title', 'propertyTitle', 'listingName', 'roomTitle']) ?? ''
        );
        $name = $name !== '' ? Str::limit($name, 240, '') : 'Imported listing ' . substr(md5(json_encode($item)), 0, 8);

        // ---- description ----
        // The CMS Blades render description fields as plain text (they use
        // `{{ }}`, sometimes wrapped in nl2br). We therefore output plain text
        // with paragraph breaks, NOT the raw HTML Airbnb returns — otherwise
        // `<br />` tags show as visible characters on the rendered page.
        $descPlain = (string) ($this->dig($item, ['description', 'summary', 'about', 'space']) ?? '');
        $descHtml  = (string) ($this->dig($item, [
            'htmlDescription.htmlText', 'descriptionHtml', 'descriptionOriginalLanguage',
        ]) ?? '');
        if ($descPlain === '' && $descHtml !== '') $descPlain = $this->htmlToPlainText($descHtml);
        if ($descHtml === '')  $descHtml  = $descPlain;

        // ---- price ----
        // The new actor only returns numeric rates when check-in/out dates are
        // supplied; otherwise `price` is a `{label: "..."}` placeholder. Skip it
        // unless we actually got a number.
        $rate = $this->dig($item, [
            'price.rate', 'pricing.rate', 'pricing.nightly', 'pricing.basePrice',
            'price.amount', 'priceRate', 'nightlyPrice', 'rate', 'baseRate',
        ]);
        if (is_array($rate)) {
            $rate = $rate['amount'] ?? $rate['rate'] ?? null;
        }
        if ($rate !== null && !is_numeric($rate)) {
            $rate = preg_replace('/[^\d.]/', '', (string) $rate);
            $rate = $rate === '' ? null : $rate;
        }

        // ---- address ----
        // locationSubtitle is the most specific (city + region + country); falls
        // back to plain `address` (older actor) or a hierarchy of others.
        $address = (string) (
            $this->dig($item, [
                'locationSubtitle', 'address', 'publicAddress', 'location.address',
                'location.publicAddress', 'location.city', 'fullAddress',
                'addressInfo.address', 'location', 'city',
            ]) ?? ''
        );
        if (is_array($address)) $address = '';

        // ---- images ----
        $images = $this->extractImages($item);

        // ---- amenities (both flat list AND structured groups) ----
        $amenitiesRaw  = $this->extractAmenities($item);
        $amenityGroups = $this->extractAmenityGroups($item);

        // ---- highlights (Airbnb's "What this place offers" tiles) ----
        $highlights = [];
        foreach ((array) ($item['highlights'] ?? []) as $h) {
            if (!is_array($h)) continue;
            $title = (string) ($h['title'] ?? '');
            if ($title === '') continue;
            $highlights[] = [
                'title'    => $title,
                'subtitle' => (string) ($h['subtitle'] ?? ''),
                'icon'     => (string) ($h['icon'] ?? ''),
            ];
        }

        // ---- check-in / checkout times + booleans, all parsed from houseRules ----
        $checkin = $checkout = null;
        $petsAllowed = $smokingAllowed = $wheelchairAccessible = null;
        foreach ((array) ($item['houseRules']['general'] ?? []) as $section) {
            foreach ((array) ($section['values'] ?? []) as $v) {
                $title = (string) ($v['title'] ?? '');
                if ($title === '') continue;
                if ($checkin === null  && preg_match('/check-?in\s+(?:after\s+)?(.+)/i', $title, $m))   $checkin  = trim($m[1]);
                if ($checkout === null && preg_match('/check-?out\s+(?:before\s+)?(.+)/i', $title, $m)) $checkout = trim($m[1]);
                if (stripos($title, 'pets allowed')        !== false) $petsAllowed = 1;
                if (stripos($title, 'smoking is allowed')  !== false || stripos($title, 'smoking allowed') !== false) $smokingAllowed = 1;
                if (stripos($title, 'wheelchair')          !== false) $wheelchairAccessible = 1;
            }
        }

        // ---- map: just the embed URL, NOT iframe HTML.
        // Front-end Blade does `<iframe src="{{ $mapSrc }}" ...>` — it wraps the value
        // itself, so we store the bare URL. Wrapping our own iframe broke that.
        $map = null;
        $lat = $this->dig($item, ['coordinates.latitude',  'lat']);
        $lng = $this->dig($item, ['coordinates.longitude', 'lng', 'lon']);
        if (is_numeric($lat) && is_numeric($lng)) {
            $map = sprintf('https://maps.google.com/maps?q=%s,%s&z=15&output=embed', (float) $lat, (float) $lng);
        }

        // ---- cancellation policy (map Airbnb's name to our enum values) ----
        $cancelType = null;
        $rawPolicy = strtolower((string) ($item['cancellationPolicies'][0]['policyName'] ?? ''));
        if ($rawPolicy !== '') {
            $cancelType = match (true) {
                str_contains($rawPolicy, 'flexible')  => 'flexible',
                str_contains($rawPolicy, 'moderate')  => 'moderate',
                str_contains($rawPolicy, 'super')     => 'super_strict',
                str_contains($rawPolicy, 'strict')    => 'strict',
                str_contains($rawPolicy, 'firm')      => 'firm',
                default                                => 'custom',
            };
        }

        // ---- booking_policy: synthesize from houseRules + additional rules text ----
        $bookingPolicyHtml = $this->buildBookingPolicyHtml($item);

        // ---- thumbnail (Group 5): prepend so it becomes feature_image after gallery download ----
        $thumb = (string) ($item['thumbnail'] ?? '');
        if ($thumb !== '' && str_starts_with($thumb, 'http')) {
            array_unshift($images, $thumb);
            $images = array_values(array_unique($images));
        }

        // ---- counts ----
        $bedrooms  = $this->intOrNull($this->dig($item, ['bedrooms', 'numberOfBedrooms', 'roomCount.bedrooms', 'bedroomCount']));
        $bathrooms = $this->intOrNull($this->dig($item, ['bathrooms', 'numberOfBathrooms', 'bathroomCount']));
        $beds      = $this->intOrNull($this->dig($item, ['beds', 'numberOfBeds', 'bedCount']));
        $sleeps    = $this->intOrNull($this->dig($item, [
            'personCapacity', 'guests', 'maxGuests', 'accommodates', 'guestCapacity', 'numberOfGuests',
        ]));

        // Parse `subDescription.items` ("16+ guests", "12 bedrooms", "15 beds", "12 baths")
        // for both numeric counts (when direct fields are missing) AND the verbatim
        // text strings (sleeps_text / bedroom_text / etc. — used on detail page).
        $sleepsText = $bedroomText = $bedsText = $bathroomText = null;
        foreach ((array) ($item['subDescription']['items'] ?? []) as $line) {
            if (!is_string($line)) continue;
            if (stripos($line, 'guest')   !== false) $sleepsText   = $line;
            if (stripos($line, 'bedroom') !== false) $bedroomText  = $line;
            if (stripos($line, 'bed')     !== false && stripos($line, 'bedroom') === false) $bedsText = $line;
            if (stripos($line, 'bath')    !== false) $bathroomText = $line;

            if ($bedrooms === null  && preg_match('/(\d+)\+?\s*bedrooms?/i', $line, $m))                   $bedrooms = (int) $m[1];
            if ($bathrooms === null && preg_match('/(\d+(?:\.\d+)?)\+?\s*(?:private\s+|shared\s+)?baths?\b/i', $line, $m)) $bathrooms = (int) round((float) $m[1]);
            if ($beds === null      && preg_match('/(\d+)\+?\s*beds?\b/i', $line, $m))                     $beds = (int) $m[1];
            if ($sleeps === null    && preg_match('/(\d+)\+?\s*guests?/i', $line, $m))                     $sleeps = (int) $m[1];
        }

        // ---- type (villa/house/cabin → 'house'; everything else → 'condo') ----
        $type = null;
        $rawType = strtolower((string) ($this->dig($item, ['propertyType', 'roomType']) ?? ''));
        if ($rawType !== '') {
            $type = (str_contains($rawType, 'villa') || str_contains($rawType, 'house')
                  || str_contains($rawType, 'home')  || str_contains($rawType, 'cabin')
                  || str_contains($rawType, 'cottage'))
                ? 'house'
                : 'condo';
        }

        // ---- meta + extras ----
        $metaTitle       = Str::limit((string) ($this->dig($item, ['seoTitle', 'metaTitle']) ?? ''), 200, '');
        $metaDescription = Str::limit((string) ($this->dig($item, ['metaDescription', 'seoDescription']) ?? ''), 500, '');
        $subtitle        = Str::limit((string) ($this->dig($item, ['sharingConfigTitle', 'subtitle']) ?? ''), 240, '');
        $rating          = $this->dig($item, ['rating.guestSatisfaction', 'rating.value', 'rating']);
        $rating          = (is_numeric($rating)) ? (float) $rating : null;
        $area            = $this->dig($item, ['size', 'squareFeet', 'area', 'sqft']);

        // ---- detail_check_in_instructions + checkout_notes (parsed from houseRules) ----
        $checkinInstructions = null;
        $checkoutNotes = null;
        foreach ((array) ($item['houseRules']['general'] ?? []) as $section) {
            $sectionTitle = strtolower((string) ($section['title'] ?? ''));
            $values = (array) ($section['values'] ?? []);
            if (str_contains($sectionTitle, 'checking in') || str_contains($sectionTitle, 'check-in')) {
                $bits = [];
                foreach ($values as $v) {
                    $t = (string) ($v['title'] ?? '');
                    if ($t !== '' && stripos($t, 'self check-in') !== false) $bits[] = $t;
                    if ($t !== '' && stripos($t, 'keypad') !== false && !in_array($t, $bits, true)) $bits[] = $t;
                }
                if (!empty($bits)) $checkinInstructions = implode('. ', $bits) . '.';
            }
            if (str_contains($sectionTitle, 'before you leave') || str_contains($sectionTitle, 'leaving') || str_contains($sectionTitle, 'check-out')) {
                $bits = [];
                foreach ($values as $v) {
                    $t = (string) ($v['title'] ?? '');
                    if ($t !== '') $bits[] = $t;
                }
                if (!empty($bits)) $checkoutNotes = implode('. ', $bits) . '.';
            }
        }

        // ---- property_view: first scenic-view amenity (e.g. "Bay view", "Ocean view") ----
        $propertyView = null;
        foreach ($amenityGroups as $group) {
            if (stripos((string) ($group['name'] ?? ''), 'scenic') !== false) {
                $propertyView = (string) (($group['items'][0]['name']) ?? '');
                break;
            }
        }

        // ---- cancellation_policy (longText, in addition to the enum cancellation_policy_type) ----
        $cancellationPolicyText = null;
        if (!empty($item['cancellationPolicies'][0]['policyName'])) {
            $cancellationPolicyText = (string) $item['cancellationPolicies'][0]['policyName'] . ' cancellation policy. See host\'s policy details for full terms.';
        }

        // ---- review_text: depends on $rating + reviewsCount ----
        $reviewText = null;
        $rcount = $item['rating']['reviewsCount'] ?? null;
        if ($rating !== null && is_numeric($rcount) && $rcount > 0) {
            $reviewText = sprintf('Rated ★ %s from %d guest review%s.', number_format((float) $rating, 1), (int) $rcount, $rcount == 1 ? '' : 's');
        }

        // ---- detail_host_meta + notes (location descriptions) + meta_keywords ----
        $hostMetaHtml             = $this->buildHostMetaHtml($item['host'] ?? []);
        $locationDescriptionsHtml = $this->buildLocationDescriptionsHtml($item['locationDescriptions'] ?? []);
        $metaKeywords             = $this->buildMetaKeywords($item, $bedrooms);

        $property = array_filter([
            'name'                          => $name,
            'description'                   => $descPlain,
            'long_description'              => $descPlain,
            'short_description'             => Str::limit($descPlain, 500, '…'),
            'address'                       => Str::limit((string) $address, 1000, ''),
            'bedroom'                       => $bedrooms ?? 0,
            'bathroom'                      => $bathrooms,
            'beds'                          => $beds,
            'sleeps'                        => $sleeps,
            'no_of_guest'                   => $sleeps,
            'standard_rate'                 => $rate !== null ? (float) $rate : null,
            'area'                          => $area !== null ? (string) $area : null,
            'meta_title'                    => $metaTitle,
            'meta_description'              => $metaDescription,
            'meta_keywords'                 => $metaKeywords,
            'subtitle'                      => $subtitle,
            'rating'                        => $rating,
            'type'                          => $type,
            'airbnb_link'                   => (string) ($this->dig($item, ['url', 'sourceUrl', 'listingUrl']) ?? ''),
            'map'                           => $map,
            'checkin'                       => $checkin,
            'checkout'                      => $checkout,
            'accepts_pets'                  => $petsAllowed,
            'smoking_allowed'               => $smokingAllowed,
            'wheelchair_accessible'         => $wheelchairAccessible,
            'cancellation_policy_type'      => $cancelType,
            'cancellation_policy'           => $cancellationPolicyText,
            'booking_policy'                => $bookingPolicyHtml,
            // Group 1 — direct column matches I missed
            'api_id'                        => (string) ($item['id'] ?? ''),
            'category'                      => Str::limit((string) ($item['roomType'] ?? ''), 50, ''),
            'checkin_host_name'             => Str::limit((string) ($item['host']['name'] ?? ''), 250, ''),
            'sleeps_text'                   => $sleepsText,
            'bedroom_text'                  => $bedroomText,
            'beds_text'                     => $bedsText,
            'bathroom_text'                 => $bathroomText,
            'property_view'                 => $propertyView,
            'detail_about_text'             => $descPlain,
            // Group 2 — synthesized HTML / text
            'detail_check_in_instructions'  => $checkinInstructions,
            'checkout_notes'                => $checkoutNotes,
            'detail_host_meta'              => $hostMetaHtml,
            'notes'                         => $locationDescriptionsHtml,
            'review_text'                   => $reviewText,
        ], fn($v) => $v !== null && $v !== '' && $v !== false);

        // bedroom defaults to 0 (NOT NULL on schema) — array_filter drops 0s.
        // Re-add it explicitly so a fresh insert satisfies the column.
        if (!isset($property['bedroom'])) {
            $property['bedroom'] = $bedrooms ?? 0;
        }

        return [
            'property'       => $property,
            'images'         => $images,
            'amenities_raw'  => array_values(array_unique($amenitiesRaw)),
            'amenity_groups' => $amenityGroups,
            'highlights'     => $highlights,
        ];
    }

    /**
     * Extract amenities preserving Airbnb's group structure: Bathroom →
     * [Bathtub, Hair dryer, ...]. Drops "Not included" group + any
     * `available: false` entries. Items keep their Airbnb icon constant
     * for the admin to remap later (most CMS icon catalogs differ).
     *
     * @return list<array{name: string, items: list<array{name: string, icon: string}>}>
     */
    private function extractAmenityGroups(array $item): array
    {
        $out = [];
        $root = $this->dig($item, ['amenities', 'amenityList', 'listingAmenities']) ?? [];
        if (!is_array($root)) return $out;

        foreach ($root as $entry) {
            if (!is_array($entry)) continue;
            $groupName = (string) ($entry['title'] ?? '');
            // Skip the synthetic "Not included" / "Not available" group — those
            // are unavailable amenities Airbnb shows as crossed-out.
            if ($groupName === '' || stripos($groupName, 'not included') !== false || stripos($groupName, 'not available') !== false) {
                continue;
            }
            $values = (array) ($entry['values'] ?? []);
            $items  = [];
            foreach ($values as $v) {
                if (!is_array($v)) continue;
                $title = (string) ($v['title'] ?? '');
                if ($title === '') continue;
                if (isset($v['available']) && $v['available'] === false) continue;
                $items[] = ['name' => $title, 'icon' => (string) ($v['icon'] ?? '')];
            }
            if (!empty($items)) {
                $out[] = ['name' => $groupName, 'items' => $items];
            }
        }
        return $out;
    }

    /**
     * Extract reviews from a dataset item. Defensive across the common shapes
     * different Airbnb Actor revisions return: top-level `reviews`, nested
     * `reviewsData.reviews`, or wrapped `{reviews: {data: [...]}}`.
     */
    public function mapReviews(array $item): array
    {
        $sources = [
            $item['reviews'] ?? null,
            $item['reviewsData']['reviews'] ?? null,
            $item['reviews']['data'] ?? null,
            $item['reviewsList'] ?? null,
        ];

        $rawReviews = [];
        foreach ($sources as $candidate) {
            if (is_array($candidate) && !empty($candidate)) {
                $rawReviews = $candidate;
                break;
            }
        }
        if (empty($rawReviews)) return [];

        $out = [];
        foreach ($rawReviews as $r) {
            if (!is_array($r)) continue;

            $reviewer = (array) ($r['reviewer'] ?? $r['author'] ?? []);
            $name = (string) (
                $r['reviewerName']
                ?? $reviewer['firstName']
                ?? $reviewer['first_name']
                ?? $reviewer['name']
                ?? ''
            );

            $photo = (string) (
                $reviewer['pictureUrl']
                ?? $reviewer['picture_url']
                ?? $reviewer['profileImage']
                ?? $r['reviewerPictureUrl']
                ?? ''
            );

            $location = (string) (
                $reviewer['location']
                ?? $reviewer['city']
                ?? $r['reviewerLocation']
                ?? ''
            );

            $comment = (string) (
                $r['comments']
                ?? $r['comment']
                ?? $r['text']
                ?? $r['localizedReview']['comments']
                ?? ''
            );
            $translated = (string) (
                $r['commentsTranslated']
                ?? $r['commentTranslated']
                ?? $r['localizedReview']['commentsTranslated']
                ?? ''
            );
            // Strip Airbnb's "Show more" Unicode markers that sometimes appear.
            $comment = trim(preg_replace('/\.\.\.\s*Show more\s*$/u', '', $comment) ?? '');

            $rating = $r['rating'] ?? $r['stars'] ?? null;
            $rating = (is_numeric($rating) && $rating >= 1 && $rating <= 5) ? (int) round($rating) : null;

            $reviewDate = (string) (
                $r['createdAt']
                ?? $r['submittedAt']
                ?? $r['localizedDate']
                ?? $r['date']
                ?? ''
            );

            $responseText = (string) ($r['response'] ?? $r['hostResponse']['comments'] ?? '');
            $responseDate = (string) ($r['responseCreatedAt'] ?? $r['hostResponse']['createdAt'] ?? '');

            $extId = (string) ($r['id'] ?? $r['reviewId'] ?? '');

            // Skip empty entries — must have at least a comment OR rating.
            if ($comment === '' && $rating === null) continue;

            $out[] = array_filter([
                'external_id'        => $extId !== '' ? $extId : null,
                'reviewer_name'      => $name !== '' ? $name : null,
                'reviewer_photo'     => str_starts_with($photo, 'http') ? $photo : null,
                'reviewer_location'  => $location !== '' ? $location : null,
                'rating'             => $rating,
                'comment'            => $comment !== '' ? $comment : null,
                'comment_translated' => $translated !== '' ? $translated : null,
                'language'           => isset($r['language']) && is_string($r['language']) ? substr($r['language'], 0, 8) : null,
                'response_text'      => $responseText !== '' ? $responseText : null,
                'response_date'      => $responseDate !== '' ? $responseDate : null,
                'review_date'        => $reviewDate !== '' ? $reviewDate : null,
            ], fn($v) => $v !== null && $v !== '');
        }

        return $out;
    }

    /**
     * Convert Airbnb's HTML (heavy with <br/> and the occasional <b>) to plain
     * text with paragraph + line breaks preserved. Used everywhere the CMS
     * Blade renders with `{{ }}` rather than `{!! !!}` — most scraped fields.
     */
    private function htmlToPlainText(string $html): string
    {
        if ($html === '') return '';
        // Double <br/> = paragraph break.
        $s = preg_replace('#(?:<br\s*/?>\s*){2,}#i', "\n\n", $html);
        // Single <br/> = line break.
        $s = preg_replace('#<br\s*/?>#i', "\n", (string) $s);
        // Block-level closes ⇒ paragraph break.
        $s = preg_replace('#</(?:p|div|h[1-6]|li|tr|section|article)>#i', "\n\n", (string) $s);
        // Strip remaining tags.
        $s = strip_tags((string) $s);
        // Decode entities (&amp; etc.).
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Collapse runs of blank lines.
        $s = preg_replace("#\r\n#", "\n", $s);
        $s = preg_replace("#[ \t]+\n#", "\n", (string) $s);
        $s = preg_replace("#\n{3,}#", "\n\n", (string) $s);
        return trim((string) $s);
    }

    /** Plain-text host summary stored in detail_host_meta. No profile image (per spec). */
    private function buildHostMetaHtml(array $host): ?string
    {
        if (empty($host) || empty($host['name'])) return null;

        $badges = [];
        if (!empty($host['isSuperHost'])) $badges[] = 'Superhost';
        if (!empty($host['isVerified']))  $badges[] = 'Verified';
        $tah = $host['timeAsHost'] ?? null;
        if (is_array($tah)) {
            $years  = (int) ($tah['years'] ?? 0);
            $months = (int) ($tah['months'] ?? 0);
            if ($years > 0)        $badges[] = "$years " . ($years  === 1 ? 'year hosting' : 'years hosting');
            elseif ($months > 0)   $badges[] = "$months " . ($months === 1 ? 'month hosting' : 'months hosting');
        }

        $lines = [];
        $headline = (string) $host['name'];
        if (!empty($badges)) $headline .= ' · ' . implode(' · ', $badges);
        $lines[] = $headline;
        $lines[] = ''; // blank line

        if (!empty($host['about']) && is_string($host['about'])) {
            $lines[] = trim($this->htmlToPlainText($host['about']));
            $lines[] = '';
        }

        foreach ((array) ($host['highlights'] ?? []) as $h) {
            if (is_string($h) && trim($h) !== '') $lines[] = '• ' . trim($h);
        }

        $details = (array) ($host['hostDetails'] ?? []);
        if (!empty($details)) {
            $bits = array_filter(array_map('strval', $details), fn($s) => trim($s) !== '');
            if (!empty($bits)) {
                $lines[] = '';
                $lines[] = implode(' · ', $bits);
            }
        }

        $out = trim(implode("\n", $lines));
        return $out !== '' ? $out : null;
    }

    /** Plain-text version of locationDescriptions for `notes`. */
    private function buildLocationDescriptionsHtml(array $entries): ?string
    {
        if (empty($entries)) return null;
        $blocks = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) continue;
            $title = trim((string) ($entry['title'] ?? ''));
            $content = trim($this->htmlToPlainText((string) ($entry['content'] ?? '')));
            if ($title === '' && $content === '') continue;
            $block = '';
            if ($title !== '')   $block .= strtoupper($title) . "\n";
            if ($content !== '') $block .= $content;
            $blocks[] = trim($block);
        }
        $out = trim(implode("\n\n", array_filter($blocks)));
        return $out !== '' ? $out : null;
    }

    /** Synthesise meta_keywords from propertyType + location + top scenic views + bedroom count. */
    private function buildMetaKeywords(array $item, ?int $bedrooms): string
    {
        $kw = [];
        if (!empty($item['propertyType']) && is_string($item['propertyType'])) $kw[] = $item['propertyType'];
        $location = $item['location'] ?? $item['locationSubtitle'] ?? '';
        if (is_string($location) && $location !== '') $kw[] = $location;

        // Top 3 scenic views, if a Scenic views amenity group is present.
        foreach ((array) ($item['amenities'] ?? []) as $group) {
            if (!is_array($group)) continue;
            if (stripos((string) ($group['title'] ?? ''), 'scenic') === false) continue;
            $count = 0;
            foreach ((array) ($group['values'] ?? []) as $v) {
                if ($count >= 3) break;
                $title = (string) ($v['title'] ?? '');
                if ($title !== '' && (!isset($v['available']) || $v['available'])) {
                    $kw[] = $title;
                    $count++;
                }
            }
            break;
        }

        if ($bedrooms !== null && $bedrooms > 0) $kw[] = "$bedrooms bedrooms";

        return Str::limit(implode(', ', array_unique(array_filter($kw))), 240, '');
    }

    /** Plain-text booking_policy synthesized from houseRules + additional rules. */
    private function buildBookingPolicyHtml(array $item): ?string
    {
        $sections = (array) ($item['houseRules']['general'] ?? []);
        $additional = trim($this->htmlToPlainText((string) ($item['houseRules']['additional'] ?? '')));
        if (empty($sections) && $additional === '') return null;

        $blocks = [];
        foreach ($sections as $section) {
            $title = trim((string) ($section['title'] ?? ''));
            $values = (array) ($section['values'] ?? []);
            if ($title === '' && empty($values)) continue;

            $lines = [];
            if ($title !== '') $lines[] = strtoupper($title);
            foreach ($values as $v) {
                $t = trim((string) ($v['title'] ?? ''));
                if ($t === '') continue;
                $info = trim((string) ($v['additionalInfo'] ?? ''));
                $lines[] = '• ' . ($info !== '' ? "$t — $info" : $t);
            }
            if (!empty($lines)) $blocks[] = implode("\n", $lines);
        }
        if ($additional !== '') {
            $blocks[] = "ADDITIONAL RULES\n" . $additional;
        }
        $out = trim(implode("\n\n", $blocks));
        return $out !== '' ? $out : null;
    }

    /** Handle both flat amenities (older actors) and grouped {title, values:[...]} (rooms-urls-scraper). */
    private function extractAmenities(array $item): array
    {
        $out = [];
        $root = $this->dig($item, ['amenities', 'amenityList', 'listingAmenities']) ?? [];
        if (!is_array($root)) return $out;

        foreach ($root as $entry) {
            // Grouped shape: {title: "Bathroom", values: [{title: "Bathtub", available: true}, ...]}
            if (is_array($entry) && isset($entry['values']) && is_array($entry['values'])) {
                foreach ($entry['values'] as $v) {
                    if (!is_array($v) || empty($v['title'])) continue;
                    if (isset($v['available']) && $v['available'] === false) continue; // skip explicit "Not included"
                    $out[] = (string) $v['title'];
                }
                continue;
            }
            // Flat string
            if (is_string($entry)) {
                $out[] = $entry;
                continue;
            }
            // Flat object with title/name/etc.
            if (is_array($entry)) {
                $title = $entry['title'] ?? $entry['name'] ?? $entry['amenity'] ?? $entry['displayName'] ?? null;
                if (is_string($title)) $out[] = $title;
            }
        }
        return $out;
    }

    /** @return list<string> */
    private function extractImages(array $item): array
    {
        $images = [];
        $sources = $this->dig($item, ['photos', 'images', 'pictures', 'photoUrls', 'imageUrls']) ?? [];

        foreach ((array) $sources as $photo) {
            if (is_string($photo) && str_starts_with($photo, 'http')) {
                $images[] = $photo;
                continue;
            }
            if (is_array($photo)) {
                $url = $photo['large'] ?? $photo['xlPicture'] ?? $photo['picture']
                    ?? $photo['imageUrl'] ?? $photo['url'] ?? $photo['src']
                    ?? $photo['original'] ?? null;
                if (is_string($url) && str_starts_with($url, 'http')) {
                    $images[] = $url;
                }
            }
        }
        return array_values(array_unique($images));
    }

    /**
     * Walk an array of dotted paths against $data, returning the first
     * non-null, non-empty value. Used to absorb actor-version drift.
     */
    private function dig(array $data, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = $data;
            foreach (explode('.', $path) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    $value = null;
                    break;
                }
                $value = $value[$segment];
            }
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return null;
    }

    private function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (int) $v;
        return null;
    }
}
