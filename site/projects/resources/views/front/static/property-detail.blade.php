@extends('front.layouts.brad')

@php
    $gallery = \App\Models\PropertyGallery::where('property_id', $data->id)
        ->where('status', 'true')
        ->orderBy('sorting')
        ->orderBy('id')
        ->get();
    $amenityGroups = \App\Models\PropertyAmenityGroup::where('property_id', $data->id)
        ->where('status', 'true')
        ->orderBy('sorting')
        ->orderBy('id')
        ->get();
    $photoUrl = function ($path) {
        $path = trim((string) $path);
        if ($path === '') {
            return asset('assets/image-06-d8a97bf200a4.webp');
        }
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }
        return asset(ltrim($path, '/'));
    };
    $heroSrc = $data->feature_image
        ? $photoUrl($data->feature_image)
        : ($gallery->isNotEmpty() ? $photoUrl($gallery->first()->image) : asset('assets/image-06-d8a97bf200a4.webp'));
    $about = $data->long_description ?: $data->description ?: $data->detail_about_text;
    $aboutIsHtml = is_string($about) && $about !== strip_tags($about);
    $rate = (float) ($data->standard_rate ?? 0);
    $rateLabel = $rate > 0
        ? '$' . number_format($rate, $rate == floor($rate) ? 0 : 2)
        : null;
    $specParts = array_values(array_filter([
        (int) $data->sleeps > 0 ? 'Sleeps ' . (int) $data->sleeps : ($data->sleeps_text ?: null),
        (int) $data->bedroom > 0 ? (int) $data->bedroom . ' bedroom' . ((int) $data->bedroom === 1 ? '' : 's') : ($data->bedroom_text ?: null),
        (int) $data->beds > 0 ? (int) $data->beds . ' bed' . ((int) $data->beds === 1 ? '' : 's') : ($data->beds_text ?: null),
        (int) $data->bathroom > 0 ? (int) $data->bathroom . ' bath' . ((int) $data->bathroom === 1 ? '' : 's') : ($data->bathroom_text ?: null),
        $data->area ?: null,
    ]));
    $galleryPayload = [
        'name' => $data->name,
        'pill' => $data->subtitle ?: 'La Vida Belize',
        'desc' => strip_tags((string) ($data->short_description ?: '')),
        'photos' => $gallery->map(fn ($g) => $photoUrl($g->image))->values()->all(),
    ];
    if ($galleryPayload['photos'] === [] && $data->feature_image) {
        $galleryPayload['photos'][] = $photoUrl($data->feature_image);
    }
    $mosaicPhotos = array_slice($galleryPayload['photos'], 0, 3);
    if ($mosaicPhotos === [] && $heroSrc) {
        $mosaicPhotos = [$heroSrc];
    }

    $displayName = $data->heading ?: $data->name;
    $locationLabel = $data->detail_meta_location ?: ($data->address ?: 'Placencia');
    $ratingValue = $data->detail_meta_rating ?: $data->rating;
    $ratingValue = is_numeric($ratingValue) ? (float) $ratingValue : null;
    $reviewCount = isset($reviews) ? $reviews->count() : 0;

    $sleepCards = \App\Models\PropertySleepCard::where('property_id', $data->id)
        ->where(function ($q) {
            $q->where('status', 'true')->orWhere('status', 1)->orWhereNull('status');
        })
        ->orderBy('sorting')
        ->orderBy('id')
        ->get();
    if ($sleepCards->isEmpty() && !empty($data->detail_sleep_cards)) {
        $decodedSleep = json_decode($data->detail_sleep_cards, true);
        if (is_array($decodedSleep) && $decodedSleep !== []) {
            $sleepCards = collect($decodedSleep)->map(function ($card) {
                $o = new \stdClass();
                $o->title = $card['title'] ?? $card['name'] ?? 'Bedroom';
                $o->text = $card['text'] ?? $card['description'] ?? '';
                return $o;
            });
        }
    }
    if ($sleepCards->isEmpty()) {
        $bedroomCount = max((int) $data->bedroom, 1);
        $bedCount = max((int) $data->beds, $bedroomCount);
        $kings = (int) ($data->king_beds ?? 0);
        $queens = (int) ($data->queen_beds ?? 0);
        $remaining = $bedCount;
        $sleepCards = collect();
        for ($i = 1; $i <= $bedroomCount; $i++) {
            $card = new \stdClass();
            $card->title = $bedroomCount === 1 ? 'Bedroom' : 'Bedroom ' . $i;
            if ($i <= $kings) {
                $card->text = '1 king bed';
                $remaining = max(0, $remaining - 1);
            } elseif ($i <= $kings + $queens) {
                $card->text = '1 queen bed';
                $remaining = max(0, $remaining - 1);
            } else {
                $here = ($i === $bedroomCount && $remaining > 1) ? $remaining : 1;
                $card->text = $here . ' bed' . ($here === 1 ? '' : 's');
                $remaining = max(0, $remaining - $here);
            }
            $sleepCards->push($card);
        }
        if ($remaining > 0 || (int) ($data->extra_bed ?? 0) > 0) {
            $extra = max($remaining, (int) ($data->extra_bed ?? 0), 1);
            $card = new \stdClass();
            $card->title = 'Common area';
            $card->text = $extra . ' extra bed' . ($extra === 1 ? '' : 's');
            $sleepCards->push($card);
        }
    }

    $amenityRows = collect();
    foreach ($amenityGroups as $group) {
        $items = \App\Models\PropertyAmenity::where('property_amenity_id', $group->id)
            ->where('status', 'true')
            ->orderBy('sorting')
            ->orderBy('id')
            ->get();
        foreach ($items as $item) {
            $item->group_name = $group->name;
            $amenityRows->push($item);
        }
    }

    $amenityIcon = function ($name, $iconKey = '') {
        $hay = strtolower(trim($iconKey . ' ' . $name));
        $paths = [
            'wifi' => 'M2 8.5c3.2-3.4 8.8-3.4 12 0M4.2 11c2-2.1 5.6-2.1 7.6 0M8 14.2h.01',
            'pool' => 'M3 12c1.2 1 2.4 1 3.6 0s2.4-1 3.6 0 2.4 1 3.6 0M4 8h8M6 4.5v4M10 4.5v4',
            'kitchen' => 'M4 3v6M6.5 3v6M9 3v6M4 9h5v5H4zM12 4v10',
            'ac' => 'M8 3v10M3.5 8h9M5 5l6 6M11 5l-6 6',
            'washer' => 'M4 3h8v11H4zM6 5h4M8 10.5a2 2 0 1 0 .01 0',
            'tv' => 'M3 4h10v8H3zM5.5 14h5M8 12v2',
            'car' => 'M3 10l1.5-4h7L13 10M3 10v3h1.5V10m7 0v3H13V10M4.5 12.5h7',
            'beach' => 'M8 3c3 1.5 4 5 0 8-4-3-3-6.5 0-8zM4 14h8M8 11v3',
            'bed' => 'M2 12V8.5A2.5 2.5 0 0 1 4.5 6H14v6M2 12h12M2 14v-2',
            'shower' => 'M8 3v2M5 7h6M6 9v3M8 9v4M10 9v3',
            'fan' => 'M8 8h.01M8 4v4m0 0 3-2M8 8l3 2M8 8l-3-2M8 8l-3 2',
            'mug' => 'M4 5h7v6a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V5zm7 1.5h1.5A1.5 1.5 0 0 1 14 8v0a1.5 1.5 0 0 1-1.5 1.5H11',
            'fire' => 'M8 14c2.5 0 4-1.8 4-4.2C12 7 8 3 8 3S4 7 4 9.8C4 12.2 5.5 14 8 14z',
            'tree' => 'M8 14V8M5 11l3-6 3 6H5z',
            'water' => 'M4 10c1.5 1.2 2.5 1.2 4 0s2.5-1.2 4 0M4 13c1.5 1.2 2.5 1.2 4 0s2.5-1.2 4 0',
            'bell' => 'M8 14a1.5 1.5 0 0 0 1.5-1.5h-3A1.5 1.5 0 0 0 8 14zM4.5 11.5h7S12 8 12 7a4 4 0 1 0-8 0c0 1 .5 4.5.5 4.5z',
            'check' => 'M3.5 8.5l3 3 6-6',
        ];
        $key = 'check';
        $map = [
            'wifi' => 'wifi', 'internet' => 'wifi', 'wireless' => 'wifi',
            'pool' => 'pool', 'swim' => 'pool',
            'kitchen' => 'kitchen', 'fridge' => 'kitchen', 'oven' => 'kitchen', 'stove' => 'kitchen',
            'air conditioning' => 'ac', 'snowflake' => 'ac', 'cooling' => 'ac',
            'washer' => 'washer', 'washing' => 'washer', 'dryer' => 'washer', 'laundry' => 'washer',
            'tv' => 'tv', 'television' => 'tv',
            'parking' => 'car', 'car' => 'car',
            'beach' => 'beach', 'ocean' => 'beach', 'waterfront' => 'beach',
            'bed' => 'bed', 'king' => 'bed', 'queen' => 'bed',
            'shower' => 'shower', 'bath' => 'shower', 'shampoo' => 'shower',
            'fan' => 'fan',
            'coffee' => 'mug', 'mug' => 'mug',
            'grill' => 'fire', 'bbq' => 'fire', 'fire' => 'fire',
            'hammock' => 'tree', 'garden' => 'tree', 'tree' => 'tree',
            'kayak' => 'water', 'snorkel' => 'water', 'water' => 'water',
            'smoke' => 'bell', 'alarm' => 'bell', 'detector' => 'bell', 'carbon' => 'bell',
        ];
        foreach ($map as $needle => $icon) {
            if (str_contains($hay, $needle)) {
                $key = $icon;
                break;
            }
        }
        $d = $paths[$key];
        return '<svg class="stay-ico" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="' . $d . '"/></svg>';
    };

    $mapSrc = null;
    if (!empty($data->latitude) && !empty($data->longitude)) {
        $mapSrc = 'https://maps.google.com/maps?q=' . rawurlencode($data->latitude . ',' . $data->longitude) . '&z=15&output=embed';
    } elseif (!empty($data->detail_map_iframe_src) && preg_match('#^https://#i', $data->detail_map_iframe_src)) {
        $mapSrc = $data->detail_map_iframe_src;
    } elseif (!empty($data->map) && preg_match('#maps\.google#i', (string) $data->map)) {
        $mapSrc = preg_match('#^https://#i', $data->map) ? $data->map : null;
        if ($mapSrc && !str_contains($mapSrc, 'output=embed') && !str_contains($mapSrc, '/embed')) {
            $mapSrc .= (str_contains($mapSrc, '?') ? '&' : '?') . 'output=embed';
        }
    } else {
        $query = $data->address ?: $locationLabel;
        if ($query === '' || stripos($query, 'placencia') !== false) {
            $query = 'La Vida Belize, 204 Plantation, Placencia Road, Placencia, Belize';
        }
        $mapSrc = 'https://maps.google.com/maps?q=' . rawurlencode($query) . '&z=14&output=embed';
    }

    $aboutPlain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $about)));
    $aboutNeedsToggle = mb_strlen($aboutPlain) > 420;
    $amenityPreviewCount = 10;
    $reviewPreviewCount = 4;
    $amenityHighlightBuckets = [
        'pool' => ['swimming pool', 'outdoor pool', 'private pool', 'pool'],
        'tub' => ['hot tub', 'jacuzzi'],
        'ocean' => ['ocean', 'beach', 'waterfront'],
        'wifi' => ['wireless', 'wifi', 'internet'],
        'parking' => ['parking'],
        'kitchen' => ['kitchen'],
        'washer' => ['washing machine', 'washer'],
        'patio' => ['patio', 'balcony'],
        'ac' => ['air conditioning'],
        'grill' => ['bbq', 'grill'],
        'kayak' => ['kayak'],
        'bike' => ['bicycle', 'bike'],
    ];
    $amenityPreview = collect();
    $usedIds = [];
    foreach ($amenityHighlightBuckets as $needles) {
        $match = $amenityRows->first(function ($item) use ($needles, $usedIds) {
            if (in_array($item->id, $usedIds, true)) {
                return false;
            }
            $hay = strtolower((string) $item->name);
            foreach ($needles as $needle) {
                if (str_contains($hay, $needle)) {
                    return true;
                }
            }
            return false;
        });
        if ($match) {
            $amenityPreview->push($match);
            $usedIds[] = $match->id;
        }
        if ($amenityPreview->count() >= $amenityPreviewCount) {
            break;
        }
    }
    foreach ($amenityRows as $item) {
        if ($amenityPreview->count() >= $amenityPreviewCount) {
            break;
        }
        if (!in_array($item->id, $usedIds, true)) {
            $amenityPreview->push($item);
            $usedIds[] = $item->id;
        }
    }
    $amenityGroupsForDrawer = $amenityRows->groupBy(function ($item) {
        $name = trim((string) ($item->group_name ?? ''));
        return $name !== '' ? $name : 'What this place offers';
    });

    $collectionSlugs = ['la-vida-belize-casa', 'la-vida-belize-casita', 'la-vida-belize-studio'];
    $moreImageFallback = [
        'la-vida-belize-casa' => 'assets/image-06-d8a97bf200a4.webp',
        'la-vida-belize-casita' => 'assets/image-07-b7515f3cf8d1.webp',
        'la-vida-belize-studio' => 'assets/image-08-4dfe2a0731af.webp',
    ];
    $moreProperties = \App\Models\Property::query()
        ->where('status', 'true')
        ->where('id', '!=', $data->id)
        ->whereIn('seo_url', $collectionSlugs)
        ->get()
        ->sortBy(function ($property) use ($collectionSlugs) {
            $idx = array_search($property->seo_url, $collectionSlugs, true);
            return $idx === false ? 99 : $idx;
        })
        ->values();
    $moreAddress = '204 Plantation, MM 11, Placencia, Belize';
@endphp

@section('title', $data->meta_title ?? ($data->name . ' — La Vida Belize'))
@section('description', $data->meta_description ?? \Illuminate\Support\Str::limit(strip_tags($data->short_description ?? $data->description ?? ''), 160))
@section('body-class', 'page stay-detail')

@section('content')
<main>
<section class="stay-detail-page">
<div class="wrap">

<div class="stay-title-row">
<div>
<h1>{{ $displayName }}</h1>
<p class="stay-location">{{ $locationLabel }}</p>
@if($ratingValue)
<p class="stay-rating"><span aria-hidden="true">★</span> {{ number_format($ratingValue, $ratingValue == floor($ratingValue) ? 0 : 2) }}@if($reviewCount) <span class="stay-rating-count">· {{ $reviewCount }} review{{ $reviewCount === 1 ? '' : 's' }}</span>@endif</p>
@endif
</div>
<button type="button" class="stay-share" id="stayShare" data-share-title="{{ $displayName }}">Share</button>
</div>

@if($mosaicPhotos !== [])
<div class="stay-mosaic" id="propertyGallery">
@foreach($mosaicPhotos as $index => $src)
<button type="button" class="stay-mosaic-item" data-property-gallery="{{ $index }}" aria-label="View photo {{ $index + 1 }} of {{ $data->name }}">
<img alt="{{ $data->name }} photo {{ $index + 1 }}" src="{{ $src }}" width="{{ $index === 0 ? 1200 : 600 }}" height="{{ $index === 0 ? 900 : 450 }}" @if($index === 0) fetchpriority="high" @endif>
</button>
@endforeach
@if(count($galleryPayload['photos']) > 0)
<button type="button" class="stay-see-all" data-property-gallery="0">See all{{ count($galleryPayload['photos']) > 3 ? ' ' . count($galleryPayload['photos']) . ' photos' : '' }}</button>
@endif
</div>
<script type="application/json" id="propertyGalleryData">@json($galleryPayload)</script>
@endif

<div class="stay-title-repeat">
<p class="stay-title-repeat-name">{{ $displayName }}</p>
<p class="stay-location">{{ $locationLabel }}</p>
@if($specParts)
<div class="stay-facts">@foreach($specParts as $part)<span>{{ $part }}</span>@endforeach</div>
@endif
</div>

<div class="stay-detail-layout">
<div class="stay-detail-main">
@if($about)
<div class="stay-about prose">
<h2>{{ ($data->detail_about_heading && $data->detail_about_heading !== 'About this stay') ? $data->detail_about_heading : 'About this home' }}</h2>
<div class="stay-about-body{{ $aboutNeedsToggle ? ' is-collapsed' : '' }}" id="stayAboutBody">
@if($aboutIsHtml)
{!! $about !!}
@else
@foreach(preg_split("/\n\s*\n/", trim((string) $about)) as $para)
@if(trim($para) !== '')
<p>{{ $para }}</p>
@endif
@endforeach
@endif
</div>
@if($aboutNeedsToggle)
<button type="button" class="stay-more-btn" id="stayAboutMore" aria-expanded="false" aria-controls="stayAboutBody">Read more</button>
@endif
</div>
@endif
</div>

<aside class="stay-detail-aside">
@php
    $context = \App\Helpers\BookingContext::fromRequest(request());
    $pmsLimits = $data->pmsRoom
        ? \App\Helper\BookingPartyValidator::limits($data->pmsRoom)
        : null;
    $maxGuests = $pmsLimits && $pmsLimits['max_guests'] > 0
        ? $pmsLimits['max_guests']
        : max((int) $data->sleeps, (int) $data->no_of_guest, 1);
    $widgetBlocked = $data->pmsRoom
        ? ['blocked' => [], 'checkin' => [], 'checkout' => []]
        : (new \App\Helper\LiveCart())->iCalDataCheckInCheckOutCheckinCheckout($data->id);
    $petsAllowed = $pmsLimits
        ? $pmsLimits['pets_allowed'] === true
        : ($data->accepts_pets && (int) $data->max_pet > 0);
    $petsKnown = $pmsLimits
        ? $pmsLimits['pets_allowed'] !== null
        : true;
    $maxPets = $pmsLimits
        ? (int) $pmsLimits['max_pets']
        : (int) $data->max_pet;
    $minStayHint = (int) ($data->min_stay ?? 0);
    $maxStayHint = (int) ($data->max_stay ?? 0);
    $stayHintParts = [];
    if ($minStayHint > 0) $stayHintParts[] = 'Min ' . $minStayHint . ' night' . ($minStayHint === 1 ? '' : 's');
    if ($maxStayHint > 0) $stayHintParts[] = 'Max ' . $maxStayHint . ' night' . ($maxStayHint === 1 ? '' : 's');
@endphp

<div class="stay-quote booking-card-pro" id="booking-widget" data-property-id="{{ $data->id }}" data-property-slug="{{ $data->seo_url }}" data-property-type="{{ $data->type }}">
<div class="stay-quote-price price-row" id="widget-price-display">{{ $rateLabel ?: '$0' }} <span>/ night</span></div>

<div class="stay-quote-fields booking-selectors">
<div class="stay-quote-dates date-inputs">
<div class="stay-quote-date date-box">Check-in<br>
<input type="text" id="widget-check-in" name="check_in" readonly
       placeholder="Add date"
       data-vr-datepicker="range-blocked"
       data-end-input="#widget-check-out"
       data-blocked='@json($widgetBlocked["blocked"] ?? [])'
       data-no-checkin='@json($widgetBlocked["checkin"] ?? [])'
       data-no-checkout='@json($widgetBlocked["checkout"] ?? [])'
       data-min-nights="{{ (int) ($data->min_stay ?? 0) }}"
       data-max-nights="{{ $data->max_stay ?? '' }}"
       value="{{ $context->check_in }}">
</div>
<div class="stay-quote-date date-box">Checkout<br>
<input type="text" id="widget-check-out" name="check_out" readonly
       placeholder="Add date"
       value="{{ $context->check_out }}">
</div>
</div>

<div class="stay-quote-guests guests-selector" id="widget-guests-selector"
     data-max-guests="{{ $maxGuests }}"
     data-max-pets="{{ $maxPets }}">
<button type="button" class="guests-trigger" id="widget-guests-trigger"
        aria-haspopup="true" aria-expanded="false"
        aria-controls="widget-guests-popover">
<span class="guests-trigger-label">Guests</span>
<span class="guests-trigger-value" id="widget-guests-trigger-value">{{ max(1, (int) $context->adults) }} guest{{ max(1, (int) $context->adults) === 1 ? '' : 's' }}</span>
</button>
<div class="guests-popover" id="widget-guests-popover" role="dialog" hidden>
<div class="guests-row" data-target="adults">
<div class="guests-row__label">
<strong>Adults</strong>
<small>Ages 13+</small>
</div>
<div class="stepper">
<button type="button" class="stepper-btn" data-action="dec" aria-label="Decrease adults">–</button>
<span class="stepper-value" id="widget-adults-value">{{ max(1, (int) $context->adults) }}</span>
<button type="button" class="stepper-btn" data-action="inc" aria-label="Increase adults">+</button>
</div>
</div>
<div class="guests-row" data-target="children">
<div class="guests-row__label">
<strong>Children</strong>
<small>Ages 2–12</small>
</div>
<div class="stepper">
<button type="button" class="stepper-btn" data-action="dec" aria-label="Decrease children">–</button>
<span class="stepper-value" id="widget-children-value">{{ (int) ($context->children ?? 0) }}</span>
<button type="button" class="stepper-btn" data-action="inc" aria-label="Increase children">+</button>
</div>
</div>
@if($petsAllowed)
<div class="guests-row" data-target="pets">
<div class="guests-row__label">
<strong>Pets</strong>
<small>Up to {{ $maxPets }}</small>
</div>
<div class="stepper">
<button type="button" class="stepper-btn" data-action="dec" aria-label="Decrease pets">–</button>
<span class="stepper-value" id="widget-pets-value">{{ (int) $context->pets }}</span>
<button type="button" class="stepper-btn" data-action="inc" aria-label="Increase pets">+</button>
</div>
</div>
@elseif($petsKnown)
<div class="guests-row guests-row--policy">
<div class="guests-row__label">
<strong>Pets</strong>
<small>Not allowed at this property</small>
</div>
<span class="pet-policy-state">No pets</span>
</div>
@else
<div class="guests-row guests-row--policy">
<div class="guests-row__label">
<strong>Pets</strong>
<small>Ask the host before booking</small>
</div>
<span class="pet-policy-state">Ask host</span>
</div>
@endif
<button type="button" class="guests-popover-close" id="widget-guests-popover-close">Close</button>
</div>
<input type="hidden" id="widget-adults" name="adults" value="{{ max(1, (int) $context->adults) }}">
<input type="hidden" id="widget-children" name="children" value="{{ (int) ($context->children ?? 0) }}">
@if($petsAllowed)
<input type="hidden" id="widget-pets" name="pets" value="{{ (int) $context->pets }}">
@endif
</div>
</div>

@if(!empty($stayHintParts))
<p class="stay-limits-hint">{{ implode(' · ', $stayHintParts) }}</p>
@endif

<div id="widget-breakdown" class="stay-quote-breakdown">
<p class="stay-quote-note">Pick dates to see your total.</p>
</div>
<p class="stay-quote-note">Total includes nights, taxes, and mandatory fees. Verified optional services appear after dates are selected.</p>
<div id="widget-error" class="stay-quote-error" hidden></div>

<button type="button" id="widget-next-btn" class="button primary reserve-btn" disabled>Next</button>

@if($data->type === 'house' && $data->detail_booking_disclaimer)
<p class="stay-quote-note">{{ $data->detail_booking_disclaimer }}</p>
@endif
</div>

<script>
(function () {
    const widget = document.getElementById('booking-widget');
    if (!widget) return;
    const pmsConfig = window.PMS_WIDGET || null;
    const propertyId = pmsConfig
        ? parseInt(pmsConfig.roomId)
        : parseInt(widget.dataset.propertyId);
    const propertyType = widget.dataset.propertyType;
    const propertySlug = widget.dataset.propertySlug;

    const inputs = {
        check_in: document.getElementById('widget-check-in'),
        check_out: document.getElementById('widget-check-out'),
        adults: document.getElementById('widget-adults'),
        children: document.getElementById('widget-children'),
        pets: document.getElementById('widget-pets'),
    };

    const breakdownEl = document.getElementById('widget-breakdown');
    const errorEl = document.getElementById('widget-error');
    const nextBtn = document.getElementById('widget-next-btn');
    let lastQuote = null;
    let timer = null;
    let optionalServices = {};

    function gatherPayload() {
        if (pmsConfig) {
            return {
                room_id: propertyId,
                checkin: inputs.check_in.value,
                checkout: inputs.check_out.value,
                adults: parseInt(inputs.adults.value) || 1,
                childs: inputs.children ? parseInt(inputs.children.value) || 0 : 0,
                pets: inputs.pets ? parseInt(inputs.pets.value) || 0 : 0,
                optional_services: optionalServices,
            };
        }

        return {
            property_id: propertyId,
            check_in: inputs.check_in.value,
            check_out: inputs.check_out.value,
            adults: parseInt(inputs.adults.value) || 1,
            children: inputs.children ? parseInt(inputs.children.value) || 0 : 0,
            pets: inputs.pets ? parseInt(inputs.pets.value) || 0 : 0,
            addons: { fees: [] },
        };
    }

    const guestsRoot = document.getElementById('widget-guests-selector');
    if (guestsRoot) {
        const trigger    = document.getElementById('widget-guests-trigger');
        const popover    = document.getElementById('widget-guests-popover');
        const closeBtn   = document.getElementById('widget-guests-popover-close');
        const triggerVal = document.getElementById('widget-guests-trigger-value');
        const maxGuests  = parseInt(guestsRoot.dataset.maxGuests || '8', 10);
        const maxPets    = parseInt(guestsRoot.dataset.maxPets || '0', 10);

        function summary() {
            const a = parseInt(inputs.adults.value, 10) || 0;
            const c = inputs.children ? (parseInt(inputs.children.value, 10) || 0) : 0;
            const p = inputs.pets ? (parseInt(inputs.pets.value, 10) || 0) : 0;
            const guests = a + c;
            const parts = [];
            parts.push(guests + ' guest' + (guests === 1 ? '' : 's'));
            if (p > 0) parts.push(p + ' pet' + (p === 1 ? '' : 's'));
            triggerVal.textContent = parts.join(', ');
        }

        function update(target, delta) {
            const hidden = inputs[target];
            if (!hidden) return;
            const valueEl = document.getElementById('widget-' + target + '-value');
            let next = (parseInt(hidden.value, 10) || 0) + delta;

            if (target === 'adults') {
                const c = inputs.children ? (parseInt(inputs.children.value, 10) || 0) : 0;
                next = Math.max(1, Math.min(maxGuests - c, next));
            } else if (target === 'children') {
                const a = parseInt(inputs.adults.value, 10) || 1;
                next = Math.max(0, Math.min(maxGuests - a, next));
            } else if (target === 'pets') {
                next = Math.max(0, Math.min(maxPets, next));
            }

            hidden.value = String(next);
            if (valueEl) valueEl.textContent = String(next);

            syncDisabled();
            summary();
            debouncedRefresh();
        }

        function syncDisabled() {
            const a = parseInt(inputs.adults.value, 10) || 1;
            const c = inputs.children ? (parseInt(inputs.children.value, 10) || 0) : 0;
            const p = inputs.pets ? (parseInt(inputs.pets.value, 10) || 0) : 0;

            setRow('adults',   a <= 1,                a >= maxGuests - c);
            setRow('children', c <= 0,                c >= maxGuests - a);
            if (inputs.pets) setRow('pets', p <= 0, p >= maxPets);
        }

        function setRow(target, decDisabled, incDisabled) {
            const row = popover.querySelector('.guests-row[data-target="' + target + '"]');
            if (!row) return;
            row.querySelector('.stepper-btn[data-action="dec"]').disabled = decDisabled;
            row.querySelector('.stepper-btn[data-action="inc"]').disabled = incDisabled;
        }

        trigger.addEventListener('click', function () {
            const open = !popover.hasAttribute('hidden');
            if (open) { popover.setAttribute('hidden', ''); trigger.setAttribute('aria-expanded', 'false'); }
            else      { popover.removeAttribute('hidden'); trigger.setAttribute('aria-expanded', 'true'); syncDisabled(); }
        });
        closeBtn.addEventListener('click', function () {
            popover.setAttribute('hidden', '');
            trigger.setAttribute('aria-expanded', 'false');
        });
        document.addEventListener('click', function (e) {
            if (!guestsRoot.contains(e.target)) {
                popover.setAttribute('hidden', '');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
        popover.addEventListener('click', function (e) {
            const btn = e.target.closest('.stepper-btn');
            if (!btn) return;
            const row = btn.closest('.guests-row');
            if (!row) return;
            update(row.dataset.target, btn.dataset.action === 'inc' ? 1 : -1);
        });

        summary();
        syncDisabled();
    }

    const clearBtn = document.getElementById('widget-clear-dates');
    function syncClearVisibility() {
        if (!clearBtn) return;
        if (inputs.check_in.value || inputs.check_out.value) {
            clearBtn.removeAttribute('hidden');
        } else {
            clearBtn.setAttribute('hidden', '');
        }
    }

    function resetBreakdown() {
        breakdownEl.innerHTML = '<p class="stay-quote-note">Pick dates to see your total.</p>';
        errorEl.hidden = true;
        errorEl.style.display = 'none';
        nextBtn.disabled = true;
        lastQuote = null;
        optionalServices = {};
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value == null ? '' : value);
        return div.innerHTML;
    }

    function money(amount, currency) {
        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency || 'USD',
            }).format(parseFloat(amount || 0));
        } catch (_) {
            return (currency || '$') + ' ' + parseFloat(amount || 0).toFixed(2);
        }
    }

    function refresh() {
        const payload = gatherPayload();
        const checkIn = pmsConfig ? payload.checkin : payload.check_in;
        const checkOut = pmsConfig ? payload.checkout : payload.check_out;
        if (!checkIn || !checkOut) {
            resetBreakdown();
            syncClearVisibility();
            return;
        }

        fetch(pmsConfig ? pmsConfig.quoteUrl : '{{ url("api/booking/quote") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(data => {
            const valid = pmsConfig ? data.ok : data.valid;
            if (!valid) {
                const errors = pmsConfig
                    ? [data.error || 'Unable to compute quote']
                    : (data.errors || ['Unable to compute quote']);
                errorEl.textContent = errors.join('. ');
                errorEl.hidden = false;
                errorEl.style.display = 'block';
                breakdownEl.innerHTML = '';
                nextBtn.disabled = true;
                lastQuote = null;
                return;
            }
            errorEl.hidden = true;
            errorEl.style.display = 'none';
            const q = pmsConfig ? data : data.quote;
            const currency = q.currency || (pmsConfig && pmsConfig.currency) || 'USD';
            let html = '<ul class="widget-price-list">';
            if (pmsConfig) {
                for (const line of (q.line_items || [])) {
                    html += `<li><span>${escapeHtml(line.label)}</span><strong>${money(line.amount, currency)}</strong></li>`;
                }
            } else {
                for (const line of q.breakdown) {
                    if (line.category === 'los_discount') continue;
                    if (line.category === 'damage_deposit') continue;
                    html += `<li><span>${escapeHtml(line.label)}</span><strong>${money(line.amount, currency)}</strong></li>`;
                }
                if (q.los_discount && q.los_discount.amount > 0) {
                    html += `<li class="los-discount"><span>${escapeHtml(q.los_discount.label)} (${q.los_discount.percent}%)</span><strong>-${money(q.los_discount.amount, currency)}</strong></li>`;
                }
                html += `<li class="widget-price-list__divider"><strong>Tax</strong><strong>${money(q.tax, currency)}</strong></li>`;
                if (q.damage_deposit > 0) {
                    html += `<li><strong>Refundable damage deposit</strong><strong>${money(q.damage_deposit, currency)}</strong></li>`;
                }
            }
            html += `<li class="widget-price-list__total"><strong>Total</strong><strong>${money(q.total, currency)}</strong></li>`;
            html += '</ul>';
            const optionalItems = q.optional_items || [];
            if (optionalItems.length) {
                html += '<div class="widget-options"><strong>Optional services</strong>';
                for (const item of optionalItems) {
                    const id = String(item.id || '');
                    const checked = (optionalServices[id] || 0) > 0;
                    html += `<label class="widget-option"><input type="checkbox" data-option-id="${escapeHtml(id)}" ${checked ? 'checked' : ''}>`
                        + `<span><b>${escapeHtml(item.label || item.name || 'Optional service')}</b>`
                        + `<small>${item.requires_approval ? 'Request only; host approval required' : escapeHtml(item.per_unit || 'Added to quote')}</small></span>`
                        + `<em>${money(item.amount || item.unit_amount || 0, currency)}</em></label>`;
                }
                html += '</div>';
            } else {
                html += '<p class="widget-options-empty">No optional add-ons are configured for online selection. Mandatory fees are already included above.</p>';
            }
            breakdownEl.innerHTML = html;
            nextBtn.disabled = false;
            lastQuote = payload;
        })
        .catch(err => {
            errorEl.textContent = 'Could not fetch quote. Please try again.';
            errorEl.hidden = false;
            errorEl.style.display = 'block';
            nextBtn.disabled = true;
        });
    }

    breakdownEl.addEventListener('change', function (event) {
        const checkbox = event.target.closest('[data-option-id]');
        if (!checkbox) return;
        const id = checkbox.dataset.optionId;
        if (checkbox.checked) optionalServices[id] = 1;
        else delete optionalServices[id];
        debouncedRefresh();
    });

    function debouncedRefresh() {
        clearTimeout(timer);
        timer = setTimeout(refresh, 300);
    }

    Object.values(inputs).forEach(el => {
        if (el) el.addEventListener('change', debouncedRefresh);
    });
    inputs.check_in.addEventListener('change', syncClearVisibility);
    inputs.check_out.addEventListener('change', syncClearVisibility);

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (window.VR_DATEPICKER && window.VR_DATEPICKER.clear) {
                window.VR_DATEPICKER.clear(inputs.check_in);
            } else {
                inputs.check_in.value = '';
                inputs.check_out.value = '';
                inputs.check_in.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }
    syncClearVisibility();

    nextBtn.addEventListener('click', function () {
        if (!lastQuote) return;
        const params = new URLSearchParams();
        if (pmsConfig) {
            params.set('property_id', propertyId);
            params.set('start_date', lastQuote.checkin);
            params.set('end_date', lastQuote.checkout);
            params.set('adults', lastQuote.adults);
            if (lastQuote.childs > 0) params.set('childs', lastQuote.childs);
            if (lastQuote.pets > 0) params.set('no_of_pets', lastQuote.pets);
            if (Object.keys(optionalServices).length) {
                params.set('optional_services', JSON.stringify(optionalServices));
            }
            window.location.href = pmsConfig.funnelUrl + '?' + params.toString();
            return;
        }

        params.set('check_in', lastQuote.check_in);
        params.set('check_out', lastQuote.check_out);
        params.set('adults', lastQuote.adults);
        if (lastQuote.children > 0) params.set('children', lastQuote.children);
        if (lastQuote.pets > 0) params.set('pets', lastQuote.pets);
        window.location.href = '{{ url("booking/checkout") }}/' + propertySlug + '?' + params.toString();
    });

    if (inputs.check_in.value && inputs.check_out.value) {
        refresh();
    }

    try {
        var qs = new URLSearchParams(window.location.search);
        if (qs.get('highlight') === 'widget') {
            widget.classList.add('booking-widget--pulse');
            setTimeout(function () { widget.classList.remove('booking-widget--pulse'); }, 3200);
            qs.delete('highlight');
            var clean = window.location.pathname + (qs.toString() ? ('?' + qs.toString()) : '') + window.location.hash;
            window.history.replaceState({}, '', clean);
        }
    } catch (_) {}
})();
</script>
</aside>
</div>

@if($sleepCards->isNotEmpty())
<section class="stay-sleep" aria-labelledby="staySleepHeading">
<h2 id="staySleepHeading">{{ $data->detail_sleep_heading ?: 'Sleeping' }}</h2>
<div class="stay-sleep-grid">
@foreach($sleepCards as $card)
<article class="stay-sleep-card">
<span class="stay-sleep-icon">{!! $amenityIcon($card->text . ' bed', 'bed') !!}</span>
<h3>{{ $card->title }}</h3>
<p>{{ $card->text }}</p>
</article>
@endforeach
</div>
</section>
@endif

@if($amenityRows->isNotEmpty())
<section class="stay-amenities" aria-labelledby="stayAmenityHeading">
<h2 id="stayAmenityHeading">{{ $data->detail_amenities_heading ?: 'What this place offers' }}</h2>
<ul class="stay-offer-grid" id="stayAmenityGrid">
@foreach($amenityPreview as $item)
<li class="stay-offer-item">{!! $amenityIcon($item->name, $item->icon) !!}<span>{{ $item->name }}</span></li>
@endforeach
</ul>
<button type="button" class="stay-more-btn" id="stayAmenityMore" command="show-modal" commandfor="stayAmenityDrawer" aria-haspopup="dialog" aria-controls="stayAmenityDrawer">Show all {{ $amenityRows->count() }} amenities</button>
</section>
@endif

<section class="stay-availability" id="stayAvailability" aria-labelledby="stayAvailHeading">
<h2 id="stayAvailHeading">Availability</h2>
<div class="stay-cal">
<div class="calendar-head">
<div aria-live="polite" class="calendar-message" id="stayCalPrompt">Choose your check-in date</div>
<div class="calendar-arrows">
<button aria-label="Previous month" id="stayCalPrev" type="button">‹</button>
<button aria-label="Next month" id="stayCalNext" type="button">›</button>
</div>
</div>
<div class="calendar-months stay-cal-months" id="stayCalMonths"></div>
<p class="stay-cal-hint">Unavailable nights are dimmed. Dates also update the booking card.</p>
</div>
</section>

@if(isset($reviews) && $reviews->isNotEmpty())
<section class="stay-reviews" aria-labelledby="stayReviewHeading">
<h2 id="stayReviewHeading">{{ $data->detail_offcanvas_reviews_heading ?: 'Guest reviews' }}</h2>
<div class="stay-review-grid" id="stayReviewGrid">
@foreach($reviews->take($reviewPreviewCount) as $review)
<article class="stay-review-card">
@if(!empty($review->rating))
<div class="stars" aria-label="{{ (int) $review->rating }} stars">{{ str_repeat('★', max(1, min(5, (int) $review->rating))) }}</div>
@endif
<blockquote>{{ $review->message }}</blockquote>
<div class="stay-review-meta">
<strong>{{ $review->name ?: 'Guest' }}</strong>
<small>{{ trim(implode(' · ', array_filter([$review->stay_date ?? null, $review->location ?? null]))) }}</small>
</div>
</article>
@endforeach
</div>
@if($reviews->count() > $reviewPreviewCount)
<button type="button" class="stay-more-btn stay-more-btn-light" id="stayReviewMore" command="show-modal" commandfor="stayReviewDrawer" aria-haspopup="dialog" aria-controls="stayReviewDrawer">Show more reviews</button>
@endif
</section>
@endif

@if($mapSrc)
<section class="stay-map" aria-labelledby="stayMapHeading">
<h2 id="stayMapHeading">{{ $data->detail_map_heading ?: 'Where you will stay' }}</h2>
<p class="stay-location">{{ $locationLabel }}</p>
<div class="stay-map-frame">
<iframe title="{{ $displayName }} on Google Maps" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="{{ $mapSrc }}"></iframe>
</div>
</section>
@endif

@if($moreProperties->isNotEmpty())
<section class="stay-more-homes" aria-labelledby="stayMoreHeading">
<h2 id="stayMoreHeading">More properties from La Vida Belize</h2>
<div class="stay-more-grid">
@foreach($moreProperties as $home)
@php
    $homeName = $home->heading ?: $home->name;
    $homeRate = (float) ($home->standard_rate ?? 0);
    $homeRateLabel = $homeRate > 0
        ? '$' . number_format($homeRate, $homeRate == floor($homeRate) ? 0 : 2) . ' / Night'
        : null;
    $homeSrc = $home->feature_image
        ? $photoUrl($home->feature_image)
        : asset($moreImageFallback[$home->seo_url] ?? 'assets/image-06-d8a97bf200a4.webp');
    $homeGuests = (int) ($home->sleeps ?: $home->no_of_guest);
    $homeBeds = (int) $home->beds;
    $homeBaths = (int) $home->bathroom;
    $homeHref = url('stay/' . ltrim((string) $home->seo_url, '/'));
@endphp
<article class="stay-more-card">
<a class="stay-more-photo" href="{{ $homeHref }}">
<img alt="{{ $homeName }}" src="{{ $homeSrc }}" width="720" height="450" loading="lazy">
@if($homeRateLabel)
<span class="stay-more-rate">{{ $homeRateLabel }}</span>
@endif
</a>
<div class="stay-more-body">
<p class="stay-more-address">
<svg class="stay-more-pin" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5z"/></svg>
<span>{{ $moreAddress }}</span>
</p>
<h3>{{ $homeName }}</h3>
<ul class="stay-more-meta">
@if($homeGuests > 0)
<li>
<svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><circle cx="6" cy="5" r="2.2"/><path d="M2.5 13c.4-2.4 1.8-3.6 3.5-3.6S9.1 10.6 9.5 13"/><circle cx="11" cy="6" r="1.7"/><path d="M10.2 13c.2-1.5 1-2.4 2.2-2.4 1.1 0 1.8.7 2.1 1.8"/></svg>
{{ $homeGuests }} {{ $homeGuests === 1 ? 'Guest' : 'Guests' }}
</li>
@endif
@if($homeBeds > 0)
<li>{!! $amenityIcon('bed', 'bed') !!}{{ $homeBeds }} {{ $homeBeds === 1 ? 'Bed' : 'Beds' }}</li>
@endif
@if($homeBaths > 0)
<li>{!! $amenityIcon('bath', 'bath') !!}{{ $homeBaths }} {{ $homeBaths === 1 ? 'Bath' : 'Baths' }}</li>
@endif
</ul>
<a class="stay-more-cta" href="{{ $homeHref }}">Check Availability +</a>
</div>
</article>
@endforeach
</div>
</section>
@endif

</div>
</section>
</main>

<script>
(function () {
    const ci = document.getElementById('widget-check-in');
    const co = document.getElementById('widget-check-out');
    const monthsEl = document.getElementById('stayCalMonths');
    if (ci && co && monthsEl) {
        const dateToday = new Date();
        dateToday.setHours(12, 0, 0, 0);
        const pad = (n) => String(n).padStart(2, '0');
        const iso = (y, m, d) => y + '-' + pad(m + 1) + '-' + pad(d);
        const todayIso = iso(dateToday.getFullYear(), dateToday.getMonth(), dateToday.getDate());
        let month = new Date(dateToday.getFullYear(), dateToday.getMonth(), 1, 12);
        let mode = 'checkin';
        const blocked = new Set();
        try {
            JSON.parse(ci.getAttribute('data-blocked') || '[]').forEach(function (d) {
                blocked.add(String(d).slice(0, 10));
            });
        } catch (_) {}
        function emit() {
            ci.dispatchEvent(new Event('change', { bubbles: true }));
            co.dispatchEvent(new Event('change', { bubbles: true }));
        }
        function render() {
            monthsEl.textContent = '';
            const names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            const monthsToShow = innerWidth < 700 ? 1 : 2;
            for (let shift = 0; shift < monthsToShow; shift += 1) {
                const m = new Date(month.getFullYear(), month.getMonth() + shift, 1, 12);
                const y = m.getFullYear();
                const mon = m.getMonth();
                const block = document.createElement('div');
                block.className = 'calendar-month';
                const title = document.createElement('div');
                title.className = 'calendar-month-title';
                title.textContent = m.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                block.appendChild(title);
                const weekdays = document.createElement('div');
                weekdays.className = 'calendar-weekdays';
                names.forEach(function (name) {
                    const e = document.createElement('span');
                    e.textContent = name;
                    weekdays.appendChild(e);
                });
                block.appendChild(weekdays);
                const grid = document.createElement('div');
                grid.className = 'calendar-days';
                const leading = (m.getDay() + 6) % 7;
                const days = new Date(y, mon + 1, 0).getDate();
                const cells = Math.ceil((leading + days) / 7) * 7;
                for (let i = 0; i < cells; i += 1) {
                    if (i < leading || i >= leading + days) {
                        const empty = document.createElement('span');
                        empty.className = 'calendar-blank';
                        grid.appendChild(empty);
                        continue;
                    }
                    const day = i - leading + 1;
                    const s = iso(y, mon, day);
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'calendar-day';
                    b.textContent = String(day);
                    b.dataset.date = s;
                    if (s < todayIso || blocked.has(s)) b.disabled = true;
                    if (blocked.has(s)) b.classList.add('is-blocked');
                    if (s === todayIso) b.classList.add('today');
                    if (s === ci.value || s === co.value) b.classList.add('selected');
                    if (ci.value && co.value && s > ci.value && s < co.value) b.classList.add('in-range');
                    b.addEventListener('click', function () { pick(s); });
                    grid.appendChild(b);
                }
                block.appendChild(grid);
                monthsEl.appendChild(block);
            }
            const prev = document.getElementById('stayCalPrev');
            if (prev) prev.disabled = month.getFullYear() === dateToday.getFullYear() && month.getMonth() === dateToday.getMonth();
            const prompt = document.getElementById('stayCalPrompt');
            if (prompt) prompt.textContent = mode === 'checkin' ? 'Choose your check-in date' : 'Choose your check-out date';
        }
        function pick(s) {
            if (mode === 'checkin' || !ci.value || s <= ci.value) {
                ci.value = s;
                co.value = '';
                mode = 'checkout';
                emit();
                render();
                return;
            }
            co.value = s;
            mode = 'checkin';
            emit();
            render();
        }
        document.getElementById('stayCalPrev')?.addEventListener('click', function () {
            month = new Date(month.getFullYear(), month.getMonth() - 1, 1, 12);
            render();
        });
        document.getElementById('stayCalNext')?.addEventListener('click', function () {
            month = new Date(month.getFullYear(), month.getMonth() + 1, 1, 12);
            render();
        });
        ci.addEventListener('change', render);
        co.addEventListener('change', render);
        render();
    }

    const aboutMore = document.getElementById('stayAboutMore');
    const aboutBody = document.getElementById('stayAboutBody');
    if (aboutMore && aboutBody) {
        aboutMore.addEventListener('click', function () {
            const open = aboutMore.getAttribute('aria-expanded') === 'true';
            aboutBody.classList.toggle('is-collapsed', open);
            aboutMore.setAttribute('aria-expanded', open ? 'false' : 'true');
            aboutMore.textContent = open ? 'Read more' : 'Show less';
        });
    }

    const supportsInvokerCommands = 'commandForElement' in HTMLButtonElement.prototype;
    function bindDrawerTrigger(triggerId, dialogId) {
        const trigger = document.getElementById(triggerId);
        const dialog = document.getElementById(dialogId);
        if (!trigger || !dialog || typeof dialog.showModal !== 'function') return;
        if (!supportsInvokerCommands) {
            trigger.addEventListener('click', function () {
                if (!dialog.open) dialog.showModal();
            });
        }
    }
    bindDrawerTrigger('stayAmenityMore', 'stayAmenityDrawer');
    bindDrawerTrigger('stayReviewMore', 'stayReviewDrawer');
    document.querySelectorAll('dialog.stay-drawer').forEach(function (dialog) {
        dialog.querySelector('.stay-drawer-close')?.addEventListener('click', function (event) {
            event.preventDefault();
            if (dialog.open) dialog.close();
        });
    });

    if (!('closedBy' in HTMLDialogElement.prototype)) {
        const outside = function (e, dialog) {
            const r = dialog.getBoundingClientRect();
            return e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
        };
        document.querySelectorAll('dialog.stay-drawer[closedby="any"]').forEach(function (dialog) {
            let pressedOutside = false;
            dialog.addEventListener('pointerdown', function (e) {
                pressedOutside = outside(e, dialog);
            });
            dialog.addEventListener('pointerup', function (e) {
                if (pressedOutside && outside(e, dialog)) dialog.close();
            });
        });
    }

    const share = document.getElementById('stayShare');
    if (share) {
        share.addEventListener('click', async function () {
            const payload = { title: share.dataset.shareTitle || document.title, url: window.location.href };
            try {
                if (navigator.share) {
                    await navigator.share(payload);
                    return;
                }
            } catch (err) {
                if (err && err.name === 'AbortError') return;
            }
            try {
                await navigator.clipboard.writeText(payload.url);
                share.textContent = 'Copied';
                setTimeout(function () { share.textContent = 'Share'; }, 1600);
            } catch (_) {}
        });
    }
})();
</script>
@endsection

@section('modals')
@include('front.partials.gallery-modal')

@if($amenityRows->isNotEmpty())
<dialog class="stay-drawer" id="stayAmenityDrawer" closedby="any" aria-labelledby="stayAmenityDrawerTitle">
<form method="dialog" class="stay-drawer-head">
<button type="submit" class="stay-drawer-close" aria-label="Close amenities" autofocus>
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
</button>
</form>
<div class="stay-drawer-body">
<h2 id="stayAmenityDrawerTitle">What this place offers</h2>
@foreach($amenityGroupsForDrawer as $groupName => $groupItems)
@if($amenityGroupsForDrawer->count() > 1)
<h3 class="stay-drawer-group">{{ $groupName }}</h3>
@endif
<ul class="stay-drawer-list">
@foreach($groupItems as $item)
<li>{!! $amenityIcon($item->name, $item->icon) !!}<span>{{ $item->name }}</span></li>
@endforeach
</ul>
@endforeach
</div>
</dialog>
@endif

@if(isset($reviews) && $reviews->isNotEmpty())
<dialog class="stay-drawer" id="stayReviewDrawer" closedby="any" aria-labelledby="stayReviewDrawerTitle">
<form method="dialog" class="stay-drawer-head">
<button type="submit" class="stay-drawer-close" aria-label="Close reviews" autofocus>
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
</button>
</form>
<div class="stay-drawer-body">
<h2 id="stayReviewDrawerTitle" class="visually-hidden">Guest reviews</h2>
@foreach($reviews as $review)
<article class="stay-drawer-review">
<div class="stay-drawer-review-top">
@if(!empty($review->photo))
<img class="stay-drawer-avatar" alt="" src="{{ $photoUrl($review->photo) }}" width="44" height="44">
@else
<span class="stay-drawer-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($review->name ?: 'G', 0, 1)) }}</span>
@endif
<div>
<strong>{{ $review->name ?: 'Guest' }}</strong>
@if(!empty($review->stay_date))
<small>{{ $review->stay_date }}</small>
@endif
</div>
</div>
@if(!empty($review->rating))
<div class="stars" aria-label="{{ (int) $review->rating }} stars">{{ str_repeat('★', max(1, min(5, (int) $review->rating))) }}</div>
@endif
<p>{{ $review->message }}</p>
</article>
@endforeach
</div>
</dialog>
@endif
@endsection
