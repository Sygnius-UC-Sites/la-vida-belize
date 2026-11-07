@extends("front.layouts.shared-master")
@section("title", $data->meta_title)
@section("keywords", $data->meta_keywords)
@section("description", $data->meta_description)

@section("header-section")
{!! $data->header_section !!}
@stop

@section("footer-section")
{!! $data->footer_section !!}
@stop

@section("container")

{{-- [PMS-MODULE] scoped PMS property styles only --}}
<link rel="stylesheet" href="{{ asset('front/design_css/pms-scope.css') }}?v=1">
<div class="pms-scope">
@php
    $currency = $setting_data['payment_currency'];
    $name = $data->name;
    $bannerImage = asset('front/images/internal-banner.webp');
    if ($data->banner_image) {
        $bannerImage = asset($data->banner_image);
    }

    $room = $data;
    $pms_room_id = null;
    if (isset($data->pms_room_id) && $data->pms_room_id) {
        $pms_room_id = $data->pms_room_id;
    } elseif (isset($data->pms_property_id) && $data->pms_property_id) {
        $tmpRoom = App\Models\Pms\PmsRoom::where('pms_property_id', $data->pms_property_id)->first();
        if ($tmpRoom) {
            $pms_room_id = $tmpRoom->pms_room_id;
            if (empty($room->pms_room_id))
                $room = $tmpRoom;
        }
    }

    $gallery = [];
    if ($pms_room_id) {
        $gallery = App\Models\Pms\PmsImage::where("pms_room_id", $pms_room_id)->orderBy("id", "asc")->get();
    }
    
    // Fallback if PmsImage is empty but images_json is populated
    if (count($gallery) === 0 && $room && !empty($room->images_json)) {
        $imgUrls = json_decode($room->images_json, true);
        if (is_array($imgUrls)) {
            $gallery = collect($imgUrls)->map(function($url, $index) use ($pms_room_id) {
                return (object)[
                    'id' => $index,
                    'pms_room_id' => $pms_room_id,
                    'url' => $url,
                    'text' => 'Property Image ' . ($index + 1)
                ];
            });
        }
    }

    $amenities = [];
    if ($room && $room->amenities_json) {
        $decoded = json_decode($room->amenities_json, true);
        if (is_array($decoded)) {
            foreach ($decoded as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $amenities[] = $item['text'] ?? ($item['name'] ?? '');
                        } else {
                            $amenities[] = $item;
                        }
                    }
                } else {
                    $amenities[] = $value;
                }
            }
        }
        $amenities = array_filter(array_unique($amenities));
    }

    // Reviews: match by local DB id (manual) OR pms_property_id (synced from PMS).
    // Do NOT fall back to pms_source alone — that would show all 1000+ reviews from
    // the same PMS account on every listing regardless of property.
    $testimonials = App\Models\Testimonial::where('status', 'true')
        ->where(function ($q) use ($data) {
            $q->where('property_id', (string)$data->id);
            if (!empty($data->pms_property_id)) {
                $q->orWhere('property_id', (string)$data->pms_property_id);
            }
        })
        ->orderBy('id', 'desc')
        ->get();

    $attractions = App\Models\Attraction::orderBy('ordering', 'asc')->get();

    // ── DB-FIRST RENDERING (works for ANY pms_source) ──────────────────────────
    // Everything below prefers the synced database values. $frontendData (a live
    // PMS API call) is used ONLY to fill a field the database is missing, so a
    // slow or failing API can never blank out a page.
    $fd = (isset($frontendData) && is_array($frontendData)
            && !empty($frontendData['title']) && $frontendData['title'] !== 'Property Not Found')
        ? $frontendData : [];

    // Name
    if (empty(trim((string)$name)) && !empty($fd['title'])) {
        $name = $fd['title'];
    }

    // Gallery — fall back to live images only if the DB produced none
    if (count($gallery) === 0 && !empty($fd['images'])) {
        $gallery = collect($fd['images'])->map(function ($url, $index) {
            return (object)[
                'url'  => is_array($url) ? ($url['url'] ?? $url['original'] ?? '') : $url,
                'text' => 'Property Image ' . ($index + 1),
            ];
        });
    }

    // Amenities
    if (count($amenities) === 0 && !empty($fd['amenities']) && is_array($fd['amenities'])) {
        $amenities = array_values(array_filter($fd['amenities']));
    }

    // Description
    $description = $room->description ?? $data->description ?? '';
    if (empty(trim(strip_tags((string)$description))) && !empty($fd['description'])) {
        $description = $fd['description'];
    }
    // If the description is plain text (no HTML tags), convert newline-separated
    // paragraphs into <p> blocks so it renders with proper spacing.
    if (!empty($description) && strip_tags((string)$description) === (string)$description) {
        $paras = array_filter(array_map('trim', preg_split('/\n{1,}/', (string)$description)));
        $description = '<p>' . implode('</p><p>', $paras) . '</p>';
    }

    // Capacity (DB → live → null; never use hardcoded defaults)
    $bedrooms  = $room->bedrooms  ?: ($fd['bedrooms']  ?? null) ?: null;
    $bathrooms = $room->bathrooms ?: ($fd['bathrooms'] ?? null) ?: null;
    $guests    = $room->max_people ?: ($fd['guests']   ?? null) ?: null;

    // Address (DB → live → placeholder)
    $addressStr = !empty($data->address)
        ? implode(', ', array_filter([$data->address, $data->city ?? '', $data->country ?? '']))
        : '';
    if (empty($addressStr) && !empty($fd['address'])) {
        $addressStr = implode(', ', array_filter([
            $fd['address']['address'] ?? '',
            $fd['address']['city'] ?? '',
            $fd['address']['country'] ?? '',
        ]));
    }
    if (empty($addressStr)) $addressStr = 'Address not provided';

    // Standardize amenities into one categorized taxonomy with icons (uniform across all PMS).
    $amCat = \App\Helper\AmenityTaxonomy::map(is_array($amenities) ? $amenities : []);

    // Pre-load the synced calendar snapshot so availability, restrictions, and
    // nightly rates render immediately. A live refresh still runs in the background.
    $preloadedBlocked = [];
    $preloadedNoCheckin = [];
    $preloadedNoCheckout = [];
    $preloadedRates = [];
    $preloadedMinStayByDate = [];
    $hasPreloadedCalendar = false;
    $pmsProperty = \App\Models\Pms\PmsProperty::where('pms_property_id', $data->pms_property_id)
        ->where('pms_source', $data->pms_source)
        ->first();
    if ($pmsProperty) {
        $preloadedBlocked = \App\Models\Pms\PmsUnavailableDate::where('property_id', $pmsProperty->id)
            ->where('un_date', '>=', now()->toDateString())
            ->where('un_date', '<=', now()->addYear()->toDateString())
            ->pluck('un_date')
            ->toArray();
    }
    if (
        \Illuminate\Support\Facades\Schema::hasTable('pms_calendar')
        && !empty($room->pms_source)
        && !empty($room->pms_room_id)
    ) {
        $calendarRows = \App\Models\Pms\PmsCalendar::forRoom($room->pms_source, (string)$room->pms_room_id)
            ->inRange(now()->toDateString(), now()->addYear()->toDateString())
            ->orderBy('date')
            ->get();

        $hasPreloadedCalendar = $calendarRows->isNotEmpty();
        foreach ($calendarRows as $calendarRow) {
            $calendarDate = $calendarRow->date instanceof \DateTimeInterface
                ? $calendarRow->date->format('Y-m-d')
                : (string)$calendarRow->date;
            $isUnavailable = $calendarRow->state !== \App\Models\Pms\PmsCalendar::STATE_AVAILABLE
                || (bool)$calendarRow->stop_sell
                || ($calendarRow->inventory !== null && (int)$calendarRow->inventory < 1);

            if ($isUnavailable) {
                $preloadedBlocked[] = $calendarDate;
                continue;
            }

            if ($calendarRow->closed_to_arrival) {
                $preloadedNoCheckin[] = $calendarDate;
            }
            if ($calendarRow->closed_to_departure) {
                $preloadedNoCheckout[] = $calendarDate;
            }
            if ($calendarRow->nightly_rate !== null && (float)$calendarRow->nightly_rate > 0) {
                $preloadedRates[$calendarDate] = (float)$calendarRow->nightly_rate;
            }
            if ((int)$calendarRow->min_stay > 1) {
                $preloadedMinStayByDate[$calendarDate] = (int)$calendarRow->min_stay;
            }
        }
    }
    $preloadedBlocked = array_values(array_unique($preloadedBlocked));

    // Standardized key facts (check-in/out, min/max stay, house-rule flags) — DB-first, API fallback.
    $keyFacts = [];
    if (!empty($room->key_facts_json)) {
        $keyFacts = json_decode($room->key_facts_json, true) ?: [];
    }
    if (empty(array_filter($keyFacts, fn($v) => $v !== null)) && !empty($fd['key_facts'])) {
        $keyFacts = $fd['key_facts'];
    }
    $hasKeyFacts = !empty(array_filter($keyFacts, fn($v) => $v !== null && $v !== ''));

    // Pricing breakdown — DB-first, API fallback.
    $pricing = [];
    if (!empty($room->pricing_json)) {
        $pricing = json_decode($room->pricing_json, true) ?: [];
    }
    if (empty($pricing) && !empty($fd['pricing_normalized'])) {
        $pricing = $fd['pricing_normalized'];
    }

    // ── Pet policy ────────────────────────────────────────────────────────────
    // pets_allowed is read from key_facts_json (PMS-authoritative, written by sync).
    // FALLBACK CHAIN:
    //   1. key_facts_json.pets_allowed  — set by sync from PMS data (preferred)
    //   2. pms_rooms.pets_allowed       — direct column (legacy admin-set value)
    //   3. null                         — unknown; show "Contact us about pets"
    //
    // STATE MEANINGS:
    //   true  → pets allowed; show selector
    //   false → pets not allowed; hide selector, show "No pets"
    //   null  → policy unknown; hide selector, show "Contact us about pets"
    $petPolicyRaw = $keyFacts['pets_allowed'] ?? null;
    if ($petPolicyRaw === null && $room->pets_allowed !== null) {
        $petPolicyRaw = (bool)(int)$room->pets_allowed; // legacy column (0/1)
    }
    // A real PMS maximum wins. When pets are allowed but no maximum is supplied,
    // use the conservative configurable website limit.
    $partyLimits = \App\Helper\BookingPartyValidator::limits($room);
    $petPolicyRaw = $partyLimits['pets_allowed'];
    $petsAllowed = ($petPolicyRaw === true);
    $petsNotAllowed = ($petPolicyRaw === false);
    $petsUnknown = ($petPolicyRaw === null);
    $maxPets = (int)$partyLimits['max_pets'];

    // Optional fee definitions — for the JS sidebar
    $optionalFeesJson = '[]';
    if (!empty($room->optional_fees_json)) {
        $optionalFeesJson = $room->optional_fees_json;
    }

    // Boolean rules render with allowed/not-allowed styling; nulls are simply not shown.
    $ruleRows = [
        ['key' => 'pets_allowed',          'icon' => 'fa-paw',            'yes' => 'Pets allowed',         'no' => 'No pets'],
        ['key' => 'smoking_allowed',       'icon' => 'fa-smoking',        'yes' => 'Smoking allowed',      'no' => 'No smoking'],
        ['key' => 'events_allowed',        'icon' => 'fa-champagne-glasses','yes' => 'Events allowed',      'no' => 'No parties or events'],
        ['key' => 'suitable_for_children', 'icon' => 'fa-children',       'yes' => 'Family friendly',      'no' => 'Not suitable for children'],
    ];
@endphp

<!-- HERO BANNER -->
<section class="property-hero" style="background-image: url('{{ $bannerImage }}');">
    <div class="hero-overlay">
        <div class="hero-content">
            <h1>Property</h1>
            <p class="hero-subtitle">{{ $name }}</p>
        </div>
    </div>
</section>

<!-- HERO GALLERY -->
<section class="gallery-section" aria-label="Property photos">
    <div class="gallery-grid">
        @if(count($gallery) > 0)
            <!-- Main large image -->
            <div class="gallery-main gallery-item" data-index="0">
                <img src="{{ $gallery[0]->url }}" loading="eager" alt="{{ $gallery[0]->text }}" />
                <div class="overlay">
                    <div class="overlay-icon">&#128247;</div>
                </div>
                <button class="gallery-show-all" aria-label="Show all photos">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" />
                        <rect x="14" y="3" width="7" height="7" />
                        <rect x="14" y="14" width="7" height="7" />
                        <rect x="3" y="14" width="7" height="7" />
                    </svg>
                    Show all photos
                </button>
            </div>

            <!-- Right 4-grid -->
            <div class="gallery-right">
                @for($i = 1; $i <= 4; $i++)
                    @if(isset($gallery[$i]))
                        <div class="gallery-item" data-index="{{ $i }}">
                            <img src="{{ $gallery[$i]->url }}" loading="lazy" alt="{{ $gallery[$i]->text }}" />
                            <div class="overlay">
                                <div class="overlay-icon">+</div>
                            </div>
                        </div>
                    @endif
                @endfor
            </div>
        @endif
    </div>
</section>

<!-- MAIN LISTING LAYOUT -->
<div class="listing-layout">

    <!-- LEFT COLUMN -->
    <main class="listing-main" id="overview">

        <h1 class="property-title">{{ $name }}</h1>
        <p class="property-location">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                aria-hidden="true">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                <circle cx="12" cy="10" r="3" />
            </svg>
            {{ $addressStr }}
        </p>

        <div class="property-meta" role="list">
            @if($guests !== null)
            <div class="meta-item" role="listitem">
                <i class="fa-solid fa-user-group me-2"></i>
                <strong>{{ $guests }}</strong> Guests
            </div>
            @endif
            @if($bedrooms !== null)
            <div class="meta-item" role="listitem">
                <i class="fa-solid fa-bed me-2"></i>
                <strong>{{ $bedrooms }}</strong> Bedrooms
            </div>
            @endif
            @if($bathrooms !== null)
            <div class="meta-item" role="listitem">
                <i class="fa-solid fa-bath me-2"></i>
                <strong>{{ $bathrooms }}</strong> Baths
            </div>
            @endif
            @php $decks = $room->decks ?? $data->decks ?? null; @endphp
            @if($decks !== null && $decks !== '0' && $decks != 0)
                <div class="meta-item" role="listitem">
                    <i class="fa-solid fa-house-chimney-window me-2"></i>
                    <strong>{{ $decks }}</strong> Decks
                </div>
            @endif
        </div>

        <!-- OVERVIEW -->
        @if(!empty(trim(strip_tags($description))))
        <section aria-labelledby="overview-title">
            <h2 class="section-title" id="overview-title">Overview</h2>
            <div class="overview-text collapsed" id="overview-text">
                {!! $description !!}
            </div>
            <button class="btn-show-more" id="overview-toggle" aria-expanded="false" aria-controls="overview-text">
                Show more
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="6 9 12 15 18 9" />
                </svg>
            </button>
        </section>
        <hr class="section-divider" />
        @endif

        <!-- AMENITIES (standardized taxonomy + icons, uniform across all PMS) -->
        @if($amCat['count'] > 0)
        <section aria-labelledby="amenities-title">
            <h2 class="section-title" id="amenities-title">What this place offers</h2>
            <div class="amenities-grid">
                @foreach(array_slice($amCat['flat'], 0, 10) as $amenity)
                    <div class="amenity-item">
                        <i class="fa-solid {{ $amenity['icon'] }} me-2" style="color: var(--gold); width:20px; text-align:center;"></i>
                        {{ $amenity['label'] }}
                    </div>
                @endforeach
            </div>
            @if($amCat['count'] > 10)
                <button class="btn-outline" id="showAmenitiesBtn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                    Show all {{ $amCat['count'] }} amenities
                </button>
            @endif
        </section>
        <hr class="section-divider" />
        @endif




        <section aria-labelledby="availability-title">
            <h2 class="section-title" id="availability-title">Availability</h2>
            <div id="avail-cal-wrap">
                <div id="avail-cal-loading" style="text-align:center;padding:40px;color:#999;font-size:14px;">Loading calendar…</div>
            </div>
        </section>

        <!-- REVIEWS -->
        @if(count($testimonials) > 0)
            <section aria-labelledby="reviews-title">
                <div class="reviews-header">
                    <div class="rating-badge">
                        <div class="stars" aria-label="5 out of 5 stars">
                            @php $avg = $testimonials->avg('score') ?: 5; @endphp
                            @for($i = 0; $i < 5; $i++)
                                <span class="star-icon {{ $i < $avg ? 'active' : '' }}" aria-hidden="true">&#9733;</span>
                            @endfor
                        </div>
                    </div>
                    <span class="rating-number">{{ number_format($avg, 1) }}</span>
                    <h2 class="section-title" id="reviews-title" style="margin-bottom:0;">
                        &nbsp;&middot;&nbsp; {{ count($testimonials) }} Reviews
                    </h2>
                </div>

                <div class="reviews-grid">
                    @foreach($testimonials->take(4) as $review)
                        <article class="review-card">
                            <div class="review-top">
                                <div class="reviewer-avatar" aria-hidden="true">{{ substr($review->name, 0, 1) }}</div>
                                <div class="reviewer-info">
                                    <p class="reviewer-name">{{ $review->name }}</p>
                                    <div class="review-meta">
                                        <div class="review-stars" aria-label="{{ $review->score }} stars">
                                            @for($i = 0; $i < 5; $i++)
                                                <span class="review-star">{{ $i < $review->score ? '★' : '☆' }}</span>
                                            @endfor
                                        </div>
                                        {{ $review->stay_date ? date('M Y', strtotime($review->stay_date)) : '' }}
                                        @if($review->profile) &middot; {{ $review->profile }} @endif
                                    </div>
                                </div>
                            </div>
                            <p class="review-text">{{ Str::limit($review->message, 200) }}</p>
                        </article>
                    @endforeach
                </div>

                @if(count($testimonials) > 4)
                    <button class="btn-outline" id="showReviewsBtn">
                        Show all {{ count($testimonials) }} reviews
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                @endif
            </section>
            <hr class="section-divider" />
        @endif

        <!-- THINGS TO KNOW (standardized key facts, uniform across all PMS) -->
        @if($hasKeyFacts)
        <section aria-labelledby="keyfacts-title" id="key-facts">
            <h2 class="section-title" id="keyfacts-title">Things to know</h2>
            <div class="amenities-grid">
                @if(!empty($keyFacts['check_in']))
                    <div class="amenity-item"><i class="fa-solid fa-right-to-bracket me-2" style="color: var(--gold); width:20px; text-align:center;"></i>Check-in: <strong class="ms-1">{{ $keyFacts['check_in'] }}</strong></div>
                @endif
                @if(!empty($keyFacts['check_out']))
                    <div class="amenity-item"><i class="fa-solid fa-right-from-bracket me-2" style="color: var(--gold); width:20px; text-align:center;"></i>Check-out: <strong class="ms-1">{{ $keyFacts['check_out'] }}</strong></div>
                @endif
                @if(!empty($keyFacts['min_stay']))
                    <div class="amenity-item"><i class="fa-solid fa-calendar-day me-2" style="color: var(--gold); width:20px; text-align:center;"></i>Minimum stay: <strong class="ms-1">{{ $keyFacts['min_stay'] }} night{{ $keyFacts['min_stay'] > 1 ? 's' : '' }}</strong></div>
                @endif
                @if(!empty($keyFacts['max_stay']))
                    <div class="amenity-item"><i class="fa-solid fa-calendar-week me-2" style="color: var(--gold); width:20px; text-align:center;"></i>Maximum stay: <strong class="ms-1">{{ $keyFacts['max_stay'] }} nights</strong></div>
                @endif
                @foreach($ruleRows as $rule)
                    @if(array_key_exists($rule['key'], $keyFacts) && $keyFacts[$rule['key']] !== null)
                        @php $allowed = (bool) $keyFacts[$rule['key']]; @endphp
                        <div class="amenity-item" style="{{ $allowed ? '' : 'opacity:.65;' }}">
                            <i class="fa-solid {{ $rule['icon'] }} me-2" style="color: {{ $allowed ? 'var(--gold)' : '#b04' }}; width:20px; text-align:center;"></i>
                            {{ $allowed ? $rule['yes'] : $rule['no'] }}
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
        <hr class="section-divider" />
        @endif

        @php
            $houseRulesHtml      = \App\Helper\PolicyFormatter::format($room->house_rules      ?? '');
            $bookingPolicyHtml   = \App\Helper\PolicyFormatter::format($room->booking_policy   ?? '');
            $cancellationHtml    = \App\Helper\PolicyFormatter::format($room->cancellation_policy ?? '');
        @endphp
        @if($houseRulesHtml || $bookingPolicyHtml || $cancellationHtml)
            <!-- POLICIES -->
            <section aria-labelledby="policies-title" id="policies">
                <h2 class="section-title" id="policies-title">Policies & Rules</h2>

                @if($houseRulesHtml)
                    <div class="policy-block mb-4">
                        <h4 class="fw-bold"><i class="fa-solid fa-house-user me-2"></i>House Rules</h4>
                        <div class="policy-content">{!! $houseRulesHtml !!}</div>
                    </div>
                @endif

                @if($bookingPolicyHtml)
                    <div class="policy-block mb-4">
                        <h4 class="fw-bold"><i class="fa-solid fa-calendar-check me-2"></i>Booking Policy</h4>
                        <div class="policy-content">{!! $bookingPolicyHtml !!}</div>
                    </div>
                @endif

                @if($cancellationHtml)
                    <div class="policy-block mb-4">
                        <h4 class="fw-bold"><i class="fa-solid fa-ban me-2"></i>Cancellation Policy</h4>
                        <div class="policy-content">{!! $cancellationHtml !!}</div>
                    </div>
                @endif
            </section>
            <hr class="section-divider" />
        @endif

        <!-- LOCATION -->
        <section aria-labelledby="map-title" id="location">
            <h2 class="section-title" id="map-title">Where you'll be</h2>
            <div class="map-container">
                @if(!empty($data->latitude) && !empty($data->longitude))
                    <iframe src="https://maps.google.com/maps?q={{ $data->latitude }},{{ $data->longitude }}&z=15&output=embed" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                @else
                    <iframe src="{!! $setting_data['map'] !!}" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                @endif
            </div>
            <p class="mt-3 text-muted">
                {{ $data->address ? implode(', ', array_filter([$data->address, $data->city, $data->country])) : 'Address not provided' }}
            </p>
        </section>

        <hr class="section-divider" />

        <!-- EXPLORE NEARBY -->
       

    </main>

    <!-- RIGHT COLUMN – BOOKING SIDEBAR -->
    <aside class="booking-sidebar">
        <div class="booking-card">
            <div class="booking-price">
                @php
                    $displayRate = $pricing['nightly_rate'] ?? $data->min_price ?? $room->min_price ?? null;
                    $displayCurrency = $pricing['currency'] ?? 'USD';
                    $currencySymbol = $displayCurrency === 'USD' ? '$' : $displayCurrency . ' ';
                @endphp
                @if($displayRate)
                    <span class="price-amount">{{ $currencySymbol }}{{ number_format($displayRate, 0) }}</span> <span class="fs-6 text-muted fw-normal">/ night</span>
                @else
                    <span class="price-amount">Add dates for prices</span>
                @endif
            </div>

            <div class="date-picker-wrap">
                <div class="date-picker-premium" id="dateTrigger" style="position: relative; cursor: pointer;">
                    <div class="date-picker-row">
                        <div class="date-field">
                            <label>Check-in</label>
                            <input type="text" id="checkin-display" value="{{ request('start_date') }}" placeholder="Add date" readonly style="cursor: pointer;">
                        </div>
                        <div class="date-field">
                            <label>Check-out</label>
                            <input type="text" id="checkout-display" value="{{ request('end_date') }}" placeholder="Add date" readonly style="cursor: pointer;">
                        </div>
                    </div>
                    <!-- Hidden real input for Datepicker -->
                    <input type="text" id="demo17" style="position: absolute; top: 0; left: 0; width: 1px; height: 1px; opacity: 0; border: none; padding: 0;">
                </div>
                <input type="hidden" name="start_date" id="start_date" value="{{ request('start_date') }}">
                <input type="hidden" name="end_date" id="end_date" value="{{ request('end_date') }}">
                <div id="calendar-rate-status" class="calendar-rate-status" aria-live="polite">
                    @if(!$hasPreloadedCalendar)
                        Loading latest rates and availability…
                    @endif
                </div>

                <div class="guests-field mt-3">
                    <label class="d-block mb-1 small text-uppercase fw-bold">
                        Guests
                        @if($guests)
                            <span class="party-limit-copy">Maximum {{ (int)$guests }}</span>
                        @endif
                    </label>
                    <div class="guest-selector-custom">
                        <div class="guest-input-wrap" id="guestToggle">
                            <input type="text" id="total-guests" class="form-control" value="1 Guest" readonly />
                        </div>
                        <div class="guest-dropdown" id="guestDropdown">
                            <div class="guest-row">
                                <span>Adults</span>
                                <div class="counter">
                                    <button type="button" class="party-counter-btn party-adults-minus"
                                        aria-label="Remove one adult"
                                        onclick="functiondec('#adults-data','#total-guests','#child-data')">-</button>
                                    <input type="number" id="adults-data" value="{{ request('adults', 1) }}" readonly>
                                    <button type="button" class="party-counter-btn party-guest-plus"
                                        aria-label="Add one adult"
                                        onclick="functioninc('#adults-data','#total-guests','#child-data')">+</button>
                                </div>
                            </div>
                            <div class="guest-row">
                                <span>Children</span>
                                <div class="counter">
                                    <button type="button" class="party-counter-btn party-children-minus"
                                        aria-label="Remove one child"
                                        onclick="functiondec('#child-data','#total-guests','#adults-data')">-</button>
                                    <input type="number" id="child-data" value="{{ request('childs', 0) }}" readonly>
                                    <button type="button" class="party-counter-btn party-guest-plus"
                                        aria-label="Add one child"
                                        onclick="functioninc('#child-data','#total-guests','#adults-data')">+</button>
                                </div>
                            </div>
                            {{-- Pet selector: 3 states driven by PMS-authoritative key_facts_json --}}
                            @if($petsAllowed)
                            <div class="guest-row pet-presence-row">
                                <span>
                                    Pets
                                    <small class="pet-limit-copy">Maximum {{ $maxPets }}</small>
                                </span>
                                <div class="pet-choice" role="group" aria-label="Are you bringing pets?">
                                    <button type="button" class="pet-choice-btn" data-pets="0">No</button>
                                    <button type="button" class="pet-choice-btn" data-pets="1">Yes</button>
                                </div>
                            </div>
                            <div class="guest-row pet-count-row" id="pet-count-row">
                                <span>Number of pets</span>
                                <div class="counter">
                                    <button type="button" class="party-counter-btn party-pets-minus"
                                        aria-label="Remove one pet"
                                        onclick="functiondec('#pet-data','#total-guests','')">-</button>
                                    <input type="number" id="pet-data" value="{{ request('pets', 0) }}" readonly>
                                    <button type="button" class="party-counter-btn party-pets-plus"
                                        aria-label="Add one pet"
                                        onclick="functioninc('#pet-data','#total-guests','')">+</button>
                                </div>
                            </div>
                            @elseif($petsNotAllowed)
                            {{-- State 2: Pets not allowed — show info row, no stepper --}}
                            <div class="guest-row" style="opacity:.6;">
                                <span><i class="fa-solid fa-paw me-1" style="color:#b04;"></i> No pets</span>
                                <small class="text-muted">Not allowed</small>
                            </div>
                            @elseif($petsUnknown)
                            {{-- State 3: Policy unknown — prompt contact, no stepper --}}
                            <div class="guest-row" style="opacity:.7;">
                                <span><i class="fa-solid fa-paw me-1" style="color:#888;"></i> Pets</span>
                                <small class="text-muted">Contact us</small>
                            </div>
                            @endif
                            <div id="party-limit-message" class="party-limit-message" aria-live="polite"></div>
                            <div class="text-end mt-3">
                                <button type="button" class="btn btn-sm btn-dark" id="applyGuests">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="gaurav-new-data-area" class="mt-4"></div>

            {{-- Promo code field — shown for all PMS after a valid quote loads.
                 Hidden when the Coupons feature is off for this customer. --}}
            @if(!empty($data->pms_source) && \App\Helper\FeatureFlags::enabled('coupons'))
            <div id="promo-code-area" class="mt-3" style="display:none;">
                <div class="d-flex gap-2 align-items-center">
                    <input type="text" id="promo-code-input" placeholder="Promo / coupon code"
                           class="form-control form-control-sm" style="max-width:160px; text-transform:uppercase;">
                    <button type="button" id="promo-code-apply" class="btn btn-sm btn-outline-secondary">Apply</button>
                    <button type="button" id="promo-code-clear" class="btn btn-sm btn-link text-danger p-0" style="display:none;">Remove</button>
                </div>
                <div id="promo-code-msg" class="small mt-1"></div>
            </div>
            @endif

            <form action="{{url('pms/get-quote')}}" method="get" class="mt-3">
                <input type="hidden" name="property_id" value="{{ $data->id }}">
                <input type="hidden" name="start_date" class="start_date_form">
                <input type="hidden" name="end_date" class="end_date_form">
                <input type="hidden" name="adults" class="adults_form">
                <input type="hidden" name="childs" class="childs_form">
                <input type="hidden" name="no_of_pets" class="pets_form" value="{{ request('pets', 0) }}">
                <input type="hidden" name="discount_coupon" class="promo_code_form" value="">
                {{-- Confirmed optional add-ons: {"hf_xxx": 1, ...} — sent to PMS quote and charged --}}
                <input type="hidden" name="optional_services" id="optional_services_input" value="">
                {{-- Approval-required service requests: stored separately, never charged until approved --}}
                <input type="hidden" name="requested_services" id="requested_services_input" value="{}">
                <button type="submit" class="btn-book" id="submit-button-gaurav-data" style="display:none;">
                    Reserve Now
                </button>
            </form>

            <p class="booking-note mt-3">You won't be charged yet</p>
        </div>
    </aside>
</div>

 @if(count($attractions) > 0)
            <section aria-labelledby="attractions-title" id="experiences">
                <div class="experiences-header d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title mb-0" id="attractions-title">Explore Nearby</h2>
                    <div class="scroll-controls">
                        <button class="scroll-btn" id="att-prev" aria-label="Previous attractions">←</button>
                        <button class="scroll-btn" id="att-next" aria-label="Next attractions">→</button>
                    </div>
                </div>

                <div class="attractions-scroll" id="attractions-scroll">
                    @foreach($attractions as $att)
                        @php
                            $isInternal = isset($att->type) && $att->type == 'internal';
                            $attractionUrl = $isInternal ? url('attractions/detail/' . $att->seo_url) : $att->seo_url;
                        @endphp
                        <article class="attraction-card">
                            <div class="attraction-img">
                                <img src="{{ asset($att->image) }}" loading="lazy" alt="{{ $att->name }}">
                            </div>
                            <div class="attraction-info">
                               <a href="{{ $attractionUrl }}" @if(!$isInternal) target="_blank" @endif > <h3 class="attraction-name">{{ $att->name }}</h3></a>
                                <p class="attraction-desc">{{ Str::limit(strip_tags($att->longDescription), 150) }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

<!-- MARQUEE BAND — built from real property data, no hardcoded values -->
@php
    $marqueeItems = [];

    if ($bedrooms)  $marqueeItems[] = $bedrooms . ' Bedroom' . ($bedrooms > 1 ? 's' : '');
    if ($bathrooms) $marqueeItems[] = $bathrooms . ' Bath' . ($bathrooms > 1 ? 's' : '');
    if ($guests)    $marqueeItems[] = 'Sleeps ' . $guests;
    if ($decks ?? null) $marqueeItems[] = $decks . ' Deck' . ($decks > 1 ? 's' : '');

    // Key facts
    if (!empty($keyFacts['check_in']))  $marqueeItems[] = 'Check-in ' . $keyFacts['check_in'];
    if (!empty($keyFacts['check_out'])) $marqueeItems[] = 'Check-out ' . $keyFacts['check_out'];
    if (!empty($keyFacts['min_stay']) && $keyFacts['min_stay'] > 1)
        $marqueeItems[] = $keyFacts['min_stay'] . '-night minimum';
    if (!empty($keyFacts['pets_allowed']) && $keyFacts['pets_allowed'])
        $marqueeItems[] = 'Pets welcome';

    // Top amenities (first 4 matched from taxonomy)
    foreach (array_slice($amCat['flat'], 0, 4) as $am) {
        $marqueeItems[] = $am['label'];
    }

    // Location
    if (!empty($data->city))    $marqueeItems[] = $data->city;
    if (!empty($data->country)) $marqueeItems[] = $data->country;

    // Only show the marquee if we have something real to show
    $showMarquee = count($marqueeItems) >= 3;
@endphp
@if($showMarquee)
<div class="marquee-band">
    <div class="marquee-inner">
        {{-- Repeat twice so the scroll looks seamless --}}
        @for($i = 0; $i < 2; $i++)
            @foreach($marqueeItems as $item)
                <span class="marquee-item">{{ $item }}</span><span class="marquee-item marquee-sep">✦</span>
            @endforeach
        @endfor
    </div>
</div>
@endif

<!-- LIGHTBOX -->
<div class="lightbox-overlay" id="lightbox" role="dialog" aria-modal="true">
    <div class="lightbox-inner">
        <span class="lightbox-counter" id="lb-counter">1 / {{ count($gallery) }}</span>
        <button class="lightbox-close" id="lb-close">&times;</button>
        <div class="lightbox-img-wrap">
            <button class="lightbox-prev" id="lb-prev">&larr;</button>
            <img id="lb-img" src="" alt="" />
            <button class="lightbox-next" id="lb-next">&rarr;</button>
        </div>
        <div class="lightbox-thumbnails" id="lb-thumbs"></div>
    </div>
</div>

<!-- AMENITIES PANEL -->
<div class="amenities-side-panel-overlay" id="amenitiesPanel">
    <div class="amenities-side-panel">
        <div class="amenities-panel-header">
            <h2>All Amenities</h2>
            <button class="btn-close-panel" id="closeAmenities">&times;</button>
        </div>
        <div class="amenities-panel-content">
            @foreach($amCat['grouped'] as $category => $items)
                <div class="amenities-category-block" style="margin-bottom:1.5rem;">
                    <h3 class="amenities-category-title" style="font-size:1.05rem; font-weight:700; margin:0 0 .75rem;">{{ $category }}</h3>
                    <div class="amenities-grid-full">
                        @foreach($items as $amenity)
                            <div class="amenity-item">
                                <i class="fa-solid {{ $amenity['icon'] }} me-2" style="color: var(--gold); width:20px; text-align:center;"></i>
                                {{ $amenity['label'] }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- REVIEWS PANEL -->
<div class="reviews-side-panel-overlay" id="reviewsPanel">
    <div class="reviews-side-panel">
        <div class="reviews-panel-header">
            <h2>Guest Reviews</h2>
            <button class="btn-close-panel" id="closeReviews">&times;</button>
        </div>
        <div class="reviews-panel-content">
            <div class="reviews-list-full">
                @foreach($testimonials as $review)
                    <article class="review-card mb-4">
                        <div class="review-top">
                            <div class="reviewer-avatar" aria-hidden="true">{{ substr($review->name, 0, 1) }}</div>
                            <div class="reviewer-info">
                                <p class="reviewer-name">{{ $review->name }}</p>
                                <div class="review-meta">
                                    <div class="review-stars">
                                        @for($i = 0; $i < 5; $i++)
                                            <span class="review-star">{{ $i < $review->score ? '★' : '☆' }}</span>
                                        @endfor
                                    </div>
                                    {{ $review->stay_date ? date('M Y', strtotime($review->stay_date)) : '' }}
                                </div>
                            </div>
                        </div>
                        <p class="review-text">{{ $review->message }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</div>

</div>{{-- /.pms-scope --}}

@stop

@section("css")
@parent
<!-- <link rel="stylesheet" href="{{ asset('front/design_css/legacy-vacation-rentals-listing.css') }}" /> -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css"/>
<style>
/* ── Litepicker Airbnb-style overrides ───────────────────────────── */
.litepicker { --lp-primary-color: #b5935a; font-family: inherit; }
.litepicker .container__months { box-shadow: 0 8px 40px rgba(0,0,0,.18); border-radius: 16px; border: none; padding: 24px; background: #fff; }
.litepicker .month-item-header { font-weight: 700; font-size: 15px; color: #1a1a1a; padding-bottom: 12px; }
.litepicker .month-item-weekdays-row > div { font-size: 12px; font-weight: 600; color: #717171; }
.litepicker .day-item { border-radius: 50%; font-size: 13px; font-weight: 500; height: 40px; width: 40px; line-height: 40px; padding: 0; color: #1a1a1a; }
.litepicker .day-item:hover:not(.is-locked):not(.is-disabled) { background: #f0e6d3 !important; color: #1a1a1a !important; border-radius: 50%; }
.litepicker .day-item.is-start-date, .litepicker .day-item.is-end-date { background: #b5935a !important; color: #fff !important; border-radius: 50% !important; font-weight: 700; }
.litepicker .day-item.is-in-range { background: #f7f0e6 !important; color: #1a1a1a !important; border-radius: 0 !important; }
.litepicker .day-item.is-start-date { border-radius: 50% 0 0 50% !important; }
.litepicker .day-item.is-end-date { border-radius: 0 50% 50% 0 !important; }
.litepicker .day-item.is-start-date.is-end-date { border-radius: 50% !important; }
.litepicker .day-item.is-locked, .litepicker .day-item.is-disabled { color: #ccc !important; text-decoration: line-through; cursor: not-allowed; background: transparent !important; }
.litepicker .button-previous-month, .litepicker .button-next-month { color: #484848; background: #f7f7f7; border-radius: 50%; width: 32px; height: 32px; display:flex; align-items:center; justify-content:center; }
.litepicker .button-previous-month:hover, .litepicker .button-next-month:hover { background: #ebebeb; }
/* Price labels under each day */
.lp-price { display: block; font-size: 10px; color: #b5935a; font-weight: 600; line-height: 1; margin-top: -4px; }
.is-locked .lp-price, .is-disabled .lp-price { color: #ccc; }
.is-start-date .lp-price, .is-end-date .lp-price { color: rgba(255,255,255,.85); }
.is-in-range .lp-price { color: #9e7d47; }
.litepicker .day-item { height: auto !important; padding: 6px 0 4px !important; display: flex; flex-direction: column; align-items: center; }

/* ── Availability display calendar ─────────────────────────────── */
#avail-cal-wrap { font-family: inherit; }
.ac-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
@media(max-width:640px){ .ac-grid { grid-template-columns: 1fr; } }
.ac-month { background:#fff; border-radius:16px; padding:20px; box-shadow:0 2px 12px rgba(0,0,0,.07); }
.ac-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
.ac-header h3 { font-size:15px; font-weight:700; color:#1a1a1a; margin:0; }
.ac-nav { background:#f7f7f7; border:none; border-radius:50%; width:30px; height:30px; cursor:pointer; font-size:14px; color:#484848; display:flex; align-items:center; justify-content:center; }
.ac-nav:hover { background:#ebebeb; }
.ac-nav:disabled { opacity:.3; cursor:default; }
.ac-weekdays { display:grid; grid-template-columns:repeat(7,1fr); text-align:center; margin-bottom:6px; }
.ac-weekdays span { font-size:11px; font-weight:600; color:#717171; padding:4px 0; }
.ac-days { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; }
.ac-day { min-height:40px; display:flex; flex-direction:column; align-items:center; justify-content:center; border-radius:8px; font-size:13px; font-weight:500; color:#1a1a1a; cursor:default; padding:4px 2px; }
.ac-day.ac-blank { visibility:hidden; }
.ac-day.ac-past { color:#ccc; }
.ac-day.ac-today { font-weight:700; color:#b5935a; box-shadow:inset 0 0 0 1.5px #b5935a; }
.ac-day.ac-booked { background:#fee2e2; color:#dc2626; text-decoration:line-through; border-radius:6px; font-weight:400; }
.ac-day.ac-available { }
.ac-day.ac-checkin-only {
    background:linear-gradient(135deg,#eef8f0 50%,#fff4d6 50%);
    box-shadow:inset 0 0 0 1px #c9a75f;
    cursor:help;
}
.ac-day.ac-checkout-only {
    background:linear-gradient(135deg,#fff4d6 50%,#eef8f0 50%);
    box-shadow:inset 0 0 0 1px #c9a75f;
    cursor:help;
}
.ac-price { display:block; font-size:9px; color:#b5935a; font-weight:600; line-height:1; margin-top:2px; }
.ac-day.ac-booked .ac-price, .ac-day.ac-past .ac-price { display:none; }
.ac-day.ac-today .ac-price { color:#b5935a; }
.ac-legend { display:flex; gap:20px; margin-top:16px; }
.ac-legend-item { display:flex; align-items:center; gap:6px; font-size:12px; color:#484848; }
.ac-legend-dot { width:12px; height:12px; border-radius:3px; }
.ac-legend-dot.avail { background:#f7f7f7; border:1px solid #ddd; }
.ac-legend-dot.booked { background:#fee2e2; }
.ac-legend-dot.checkin-only { background:linear-gradient(135deg,#eef8f0 50%,#fff4d6 50%); border:1px solid #c9a75f; }
.ac-legend-dot.checkout-only { background:linear-gradient(135deg,#fff4d6 50%,#eef8f0 50%); border:1px solid #c9a75f; }
</style>
<style>
    .guest-selector-custom {
        position: relative;
    }

    .guest-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        z-index: 10;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .guest-dropdown.active {
        display: block;
    }

    .guest-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .counter {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .counter button {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: 1px solid #ccc;
        background: #fff;
        cursor: pointer;
    }

    .counter button:disabled {
        opacity: .32;
        cursor: not-allowed;
        background: #f4f4f4;
    }

    .counter input {
        width: 40px;
        text-align: center;
        border: none;
        font-weight: bold;
    }

    .party-limit-copy,
    .pet-limit-copy {
        display: inline-block;
        margin-left: 6px;
        color: #777;
        font-size: .68rem;
        font-weight: 500;
        text-transform: none;
    }

    .pet-limit-copy {
        display: block;
        margin: 2px 0 0;
    }

    .pet-choice {
        display: inline-flex;
        padding: 2px;
        border: 1px solid #ddd;
        border-radius: 999px;
        background: #f7f7f7;
    }

    .pet-choice-btn {
        border: 0;
        border-radius: 999px;
        padding: 5px 12px;
        background: transparent;
        color: #666;
        font-size: .78rem;
        cursor: pointer;
    }

    .pet-choice-btn.active {
        background: #1c1208;
        color: #fff;
    }

    .pet-count-row {
        display: none;
        padding-top: 4px;
        border-top: 1px solid #eee;
    }

    .pet-count-row.active {
        display: flex;
    }

    .party-limit-message {
        min-height: 18px;
        color: #9a5b00;
        font-size: .76rem;
        line-height: 1.3;
    }

    .calendar-rate-status {
        min-height: 18px;
        padding-top: 5px;
        color: #777;
        font-size: .72rem;
    }



    /* Datepicker Premium UI */
    .date-picker-premium {
        border: 1px solid rgba(197, 164, 106, .2);
        border-radius: 10px;
        cursor: pointer;
        transition: background .15s;
        position: relative;
    }

    .date-picker-premium:hover {
        background: rgba(197, 164, 106, .06);
    }

    .date-picker-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .date-field {
        padding: 12px 14px;
    }

    .date-field:first-child {
        border-right: 1px solid rgba(197, 164, 106, .15);
        border-radius: 10px 0 0 10px;
    }

    .date-field:last-child {
        border-radius: 0 10px 10px 0;
    }

    .date-field label {
        display: block;
        font-family: Jost, sans-serif;
        font-size: .65rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #1c1208;
        margin-bottom: 2px;
    }

    .date-field input {
        width: 100%;
        border: none;
        background: transparent;
        font-size: .85rem;
        font-family: Jost, sans-serif;
        color: #1c1208;
        outline: none;
        cursor: pointer;
    }

    /* ── PMS quote breakdown panel ── */
    .pms-breakdown {
        border-top: 1px solid #e5e5e5;
        padding-top: 14px;
        font-size: .93rem;
    }
    .pms-fee-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 0;
        color: #333;
    }
    .pms-fee-divider {
        border-top: 1px solid #e5e5e5;
        margin: 8px 0;
    }
    .pms-fee-total {
        font-weight: 700;
        font-size: 1rem;
        padding-top: 8px;
    }
    .pms-fee-categories { display: grid; gap: 6px; }
    .pms-fee-category {
        overflow: hidden;
        border: 1px solid #ece8e1;
        border-radius: 8px;
        background: #fff;
    }
    .pms-fee-category summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 9px 10px;
        color: #29231d;
        cursor: pointer;
        list-style: none;
    }
    .pms-fee-category summary::-webkit-details-marker { display: none; }
    .pms-fee-category summary::before {
        content: '\203A';
        flex: 0 0 auto;
        color: #9b7b43;
        font-size: 1.15rem;
        line-height: 1;
        transition: transform .15s ease;
    }
    .pms-fee-category[open] summary::before { transform: rotate(90deg); }
    .pms-category-label {
        display: flex;
        align-items: baseline;
        gap: 6px;
        flex: 1;
        min-width: 0;
        font-weight: 600;
    }
    .pms-category-label small {
        overflow: hidden;
        color: #888;
        font-size: .72rem;
        font-weight: 400;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .pms-category-amount { flex: 0 0 auto; font-weight: 600; }
    .pms-category-items {
        padding: 2px 10px 8px 28px;
        border-top: 1px solid #f2eee8;
        background: #fbfaf8;
    }
    .pms-fee-detail-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 5px 0;
        color: #5d5751;
        font-size: .82rem;
    }
    .pms-fee-detail-row span:first-child { min-width: 0; }
    .pms-night-detail { padding: 2px 0 3px 8px; }
    .pms-night-row { display: flex; justify-content: space-between; font-size: .8rem; color: #555; padding: 2px 0; }

    /* Payment schedule (split/installment) */
    .pms-payment-schedule { border-top: 1px solid #e5e5e5; margin-top: 10px; padding-top: 10px; }
    .pms-schedule-title { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-bottom: 8px; }
    .pms-schedule-row { font-size: .88rem; color: #555; }
    .pms-schedule-label { flex: 1; }

    /* Optional add-ons stepper */
    .pms-optional-section { border-top: 1px solid #e5e5e5; margin-top: 10px; padding-top: 10px; }
    .pms-opt-title { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-bottom: 8px; }
    .pms-opt-row { display: flex; align-items: center; justify-content: space-between; padding: 6px 0; gap: 8px; }
    .pms-opt-info { flex: 1; min-width: 0; }
    .pms-opt-name { display: block; font-size: .9rem; color: #333; }
    .pms-opt-price { display: block; font-size: .78rem; color: #777; margin-top: 1px; }
    .pms-opt-stepper { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
    .pms-step-btn {
        width: 28px; height: 28px; border-radius: 50%;
        border: 1.5px solid #ccc; background: #fff;
        font-size: 1rem; line-height: 1; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        color: #333; transition: border-color .15s;
    }
    .pms-step-btn:hover:not(:disabled) { border-color: var(--gold); color: var(--gold); }
    .pms-step-btn:disabled { opacity: .35; cursor: default; }
    .pms-step-val { min-width: 18px; text-align: center; font-size: .95rem; font-weight: 600; }
    .pms-opt-active-row { color: #444; font-style: italic; }
    .pms-deposit-note,
    .pms-optional-fees {
        margin: 8px 0 0;
        font-size: .8rem;
        color: #555;
    }
    .pms-approximate-note {
        margin: 6px 0 0;
        font-size: .78rem;
        color: #b45309;
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 4px;
        padding: 5px 8px;
    }

    /* ── Per-date nightly price on calendar cells ── */
    .datepicker__month-day-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
    }
    .day-price-label {
        display: block;
        font-size: 9px;
        line-height: 1;
        color: #666;
        margin-top: 2px;
        font-family: Jost, sans-serif;
        letter-spacing: -.01em;
        pointer-events: none;
    }
    /* Highlight price on hover/selected days */
    .datepicker__month-day--selected .day-price-label,
    .datepicker__month-day--hovering .day-price-label {
        color: rgba(255,255,255,.85);
    }
    /* Hide price on unavailable days (already no-show via JS but belt-and-suspenders) */
    .datepicker__month-day--disabled .day-price-label,
    .datepicker__month-day--invalid .day-price-label {
        display: none;
    }

    .litepicker .day-item.pms-checkin-only {
        background: linear-gradient(135deg, rgba(238,248,240,.95) 50%, rgba(255,244,214,.95) 50%);
        box-shadow: inset 0 0 0 1px #c9a75f;
    }

    .litepicker .day-item.pms-checkout-only {
        background: linear-gradient(135deg, rgba(255,244,214,.95) 50%, rgba(238,248,240,.95) 50%);
        box-shadow: inset 0 0 0 1px #c9a75f;
    }

    .litepicker .day-item.pms-unavailable-night {
        cursor: help;
    }

    .pms-calendar-key {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 14px;
        padding: 9px 12px;
        border-top: 1px solid #eee;
        color: #666;
        font-size: 11px;
    }

    .pms-calendar-key span {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .pms-calendar-key i {
        width: 10px;
        height: 10px;
        border-radius: 2px;
        border: 1px solid #ccc;
    }

    .pms-calendar-key .key-available { background:#fff; }
    .pms-calendar-key .key-checkin { background:linear-gradient(135deg,#eef8f0 50%,#fff4d6 50%); border-color:#c9a75f; }
    .pms-calendar-key .key-checkout { background:linear-gradient(135deg,#fff4d6 50%,#eef8f0 50%); border-color:#c9a75f; }
    .pms-calendar-key .key-unavailable { background:#fee2e2; border-color:#efb8b8; }
</style>
@stop

@section("js")
@parent
<script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js"></script>

{{-- Embed normalized pricing data for client-side quote calculator --}}
<script>
window.pmsPricing = @json(!empty($pricing) ? $pricing : null);
</script>

<script>
    // Gallery Data
    const galleryImages = [
        @foreach($gallery as $img)
            { src: "{{ $img->url }}", alt: "{{ $img->text }}" },
        @endforeach
        ];

    let currentIndex = 0;
    const lightbox = document.getElementById("lightbox");
    const lbImg = document.getElementById("lb-img");
    const lbCounter = document.getElementById("lb-counter");
    const lbThumbsEl = document.getElementById("lb-thumbs");

    function openLightbox(index) {
        currentIndex = index;
        lbImg.src = galleryImages[index].src;
        lbCounter.textContent = `${index + 1} / ${galleryImages.length}`;
        lightbox.classList.add("active");
        document.body.style.overflow = "hidden";
    }

    document.querySelectorAll(".gallery-item").forEach(item => {
        item.addEventListener("click", () => openLightbox(parseInt(item.dataset.index)));
    });

    document.getElementById("lb-close")?.addEventListener("click", () => {
        lightbox?.classList.remove("active");
        document.body.style.overflow = "";
    });

    document.getElementById("lb-prev")?.addEventListener("click", () => {
        currentIndex = (currentIndex > 0) ? currentIndex - 1 : galleryImages.length - 1;
        openLightbox(currentIndex);
    });

    document.getElementById("lb-next")?.addEventListener("click", () => {
        currentIndex = (currentIndex < galleryImages.length - 1) ? currentIndex + 1 : 0;
        openLightbox(currentIndex);
    });

    // Overview Toggle
    const overviewToggle = document.getElementById("overview-toggle");
    if (overviewToggle) {
        overviewToggle.addEventListener("click", function () {
            const text = document.getElementById("overview-text");
            text.classList.toggle("collapsed");
            this.innerHTML = text.classList.contains("collapsed") ? 'Show more <i class="fa-solid fa-chevron-down ms-1"></i>' : 'Show less <i class="fa-solid fa-chevron-up ms-1"></i>';
        });
    }

    // Amenities Panel
    document.getElementById("showAmenitiesBtn")?.addEventListener("click", () => {
        document.getElementById("amenitiesPanel").classList.add("active");
    });
    document.getElementById("closeAmenities")?.addEventListener("click", () => {
        document.getElementById("amenitiesPanel")?.classList.remove("active");
        document.body.style.overflow = "";
    });

    // Reviews Panel
    document.getElementById("showReviewsBtn")?.addEventListener("click", () => {
        document.getElementById("reviewsPanel").classList.add("active");
        document.body.style.overflow = "hidden";
    });
    document.getElementById("closeReviews")?.addEventListener("click", () => {
        document.getElementById("reviewsPanel").classList.remove("active");
        document.body.style.overflow = "";
    });

    // Scroll Controls
    function setupScroll(prevId, nextId, scrollId) {
        const scrollEl = document.getElementById(scrollId);
        if (!scrollEl) return;
        document.getElementById(prevId)?.addEventListener("click", () =>
            scrollEl.scrollBy({ left: -300, behavior: "smooth" })
        );
        document.getElementById(nextId)?.addEventListener("click", () =>
            scrollEl.scrollBy({ left: 300, behavior: "smooth" })
        );
    }
    setupScroll("att-prev", "att-next", "attractions-scroll");

    // Guest Selector
    document.getElementById("guestToggle")?.addEventListener("click", () => {
        document.getElementById("guestDropdown")?.classList.toggle("active");
    });
    document.getElementById("applyGuests")?.addEventListener("click", () => {
        document.getElementById("guestDropdown").classList.remove("active");
    });

    function showPartyLimit(message) {
        const el = document.getElementById('party-limit-message');
        if (!el) return;
        el.textContent = message || '';
    }

    function syncPartyControls() {
        let adults = Math.max(1, parseInt($('#adults-data').val()) || 1);
        let childs = Math.max(0, parseInt($('#child-data').val()) || 0);
        let pets   = Math.max(0, parseInt($('#pet-data').val()) || 0);

        if (PMS_MAX_GUESTS > 0 && adults + childs > PMS_MAX_GUESTS) {
            childs = Math.max(0, PMS_MAX_GUESTS - adults);
            if (adults > PMS_MAX_GUESTS) {
                adults = PMS_MAX_GUESTS;
                childs = 0;
            }
        }
        if (PMS_PETS_ALLOWED !== true) pets = 0;
        if (PMS_MAX_PETS > 0) pets = Math.min(pets, PMS_MAX_PETS);

        $('#adults-data').val(adults).attr('max', PMS_MAX_GUESTS || null);
        $('#child-data').val(childs).attr('max', PMS_MAX_GUESTS || null);
        $('#pet-data').val(pets).attr('max', PMS_MAX_PETS || null);

        const guestLimitReached = PMS_MAX_GUESTS > 0 && adults + childs >= PMS_MAX_GUESTS;
        $('.party-guest-plus').prop('disabled', guestLimitReached);
        $('.party-adults-minus').prop('disabled', adults <= 1);
        $('.party-children-minus').prop('disabled', childs <= 0);
        $('.party-pets-minus').prop('disabled', pets <= 1);
        $('.party-pets-plus').prop('disabled', PMS_MAX_PETS > 0 && pets >= PMS_MAX_PETS);

        $('.pet-choice-btn[data-pets="0"]').toggleClass('active', pets === 0);
        $('.pet-choice-btn[data-pets="1"]').toggleClass('active', pets > 0);
        $('#pet-count-row').toggleClass('active', pets > 0);
    }

    function functiondec(input, show, other) {
        let val = parseInt($(input).val());
        if (val > 0) {
            if (input === '#adults-data' && val === 1) return; // Min 1 adult
            $(input).val(val - 1);
            showPartyLimit('');
            updateGuestTotal();
        }
    }

    function functioninc(input, show, other) {
        let val = parseInt($(input).val()) || 0;
        // Enforce PMS max-guest cap on adults + children (not pets)
        if (PMS_MAX_GUESTS > 0 && (input === '#adults-data' || input === '#child-data')) {
            let adults = parseInt($('#adults-data').val()) || 1;
            let childs = parseInt($('#child-data').val()) || 0;
            if (adults + childs >= PMS_MAX_GUESTS) {
                showPartyLimit('Maximum ' + PMS_MAX_GUESTS + ' guests for this property.');
                return;
            }
        }
        // Enforce the PMS maximum, or the configured fallback when the PMS omits it.
        if (input === '#pet-data' && PMS_MAX_PETS > 0 && val >= PMS_MAX_PETS) {
            showPartyLimit('Maximum ' + PMS_MAX_PETS + ' pet' + (PMS_MAX_PETS !== 1 ? 's' : '') + ' for this property.');
            return;
        }
        $(input).val(val + 1);
        showPartyLimit('');
        updateGuestTotal();
    }

    function updateGuestTotal() {
        syncPartyControls();
        let adults = parseInt($('#adults-data').val()) || 1;
        let childs = parseInt($('#child-data').val()) || 0;
        let pets = parseInt($('#pet-data').val()) || 0;
        let total = adults + childs;
        let guestStr = total + (total > 1 ? ' Guests' : ' Guest');
        if (pets > 0) {
            guestStr += ', ' + pets + (pets > 1 ? ' Pets' : ' Pet');
        }
        $('#total-guests').val(guestStr);
        ajaxCallingData();
    }

    $(document).on('click', '.pet-choice-btn', function() {
        const wantsPets = parseInt($(this).data('pets')) === 1;
        $('#pet-data').val(wantsPets ? Math.max(1, parseInt($('#pet-data').val()) || 0) : 0);
        showPartyLimit('');
        updateGuestTotal();
    });

    // ── PMS real-time quote from API ─────────────────────────────────────────
    const PMS_ROOM_ID   = "{{ $data->id }}";
    const PMS_QUOTE_URL = "{{ route('pms-quote') }}";
    const IS_PMS_ROOM   = {{ !empty($data->pms_source) ? 'true' : 'false' }};
    // Static minimum stay from DB/key_facts — used before availability data loads.
    const PMS_MIN_STAY  = {{ (int)($room->custom_min_stay ?: ($keyFacts['min_stay'] ?? 1)) }};
    // Maximum total guests (adults + children) from the PMS property. 0 = no limit.
    const PMS_MAX_GUESTS = {{ (int)($guests ?? 0) }};
    // Pet policy from PMS (authoritative, written by sync command).
    //   true  = pets allowed (selector visible)
    //   false = pets not allowed (selector hidden)
    //   null  = unknown (selector hidden, "Contact us" shown)
    const PMS_PETS_ALLOWED = {{ $petsAllowed ? 'true' : ($petsNotAllowed ? 'false' : 'null') }};
    // Maximum pets allowed per stay. Uses PMS value or configured fallback.
    const PMS_MAX_PETS = {{ $maxPets }};
    // Canonical fee definitions for this property (from PMS fee API, built on sync).
    // Each item follows CanonicalFee shape. Used to build optional service controls.
    const PMS_OPTIONAL_FEES = @json(json_decode($optionalFeesJson, true) ?? []);

    // Track current dates, last API quote, per-optional quantities, and promo code
    window.pmsCurrentDates = { start: null, end: null };
    window.pmsLastQuote    = null;   // base quote from API (no optional fees)
    window.pmsOptQty       = {};     // { optId: qty }
    window.pmsPromoCode    = '';     // applied promo code (all PMS)

    function fmt(amount, currency) {
        const sym = (currency || 'USD') === 'USD' ? '$' : (currency + ' ');
        return sym + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /**
     * NOTE: computeOptionalAdditions() has been removed.
     *
     * Local JS price calculation for optional fees was wrong — the PMS is the only
     * authoritative source for the total when add-ons are selected.
     *
     * NEW BEHAVIOUR: stepper buttons trigger debouncedReQuote() which calls the
     * server endpoint (POST /pms-quote) with the current pmsOptQty selections
     * serialised as optional_services JSON. The server calls the PMS and returns
     * an authoritative quote. renderPmsBreakdown() renders quote.total directly.
     */

    /** Debounce helper for add-on re-quotes */
    let _reQuoteTimer = null;
    function debouncedReQuote(ms) {
        clearTimeout(_reQuoteTimer);
        _reQuoteTimer = setTimeout(() => {
            const { start, end } = window.pmsCurrentDates;
            if (!start || !end) return;
            const pets = parseInt($('#pet-data').val()) || 0;
            fetchPmsQuote(start, end, pets);
        }, ms || 450);
    }

    function renderPmsBreakdown(quote) {
        const currency   = quote.currency || 'USD';
        // grandTotal is the PMS-authoritative total. When optional fees are selected,
        // fetchPmsQuote was already called with optional_services included, so
        // quote.total already reflects the add-ons. No local arithmetic is performed.
        const grandTotal = quote.total;

        let html = '<div class="pms-breakdown">';

        // Night breakdown toggle (if variable nightly rates)
        const nb = quote.night_breakdown || [];
        const hasVariableRates = nb.length > 1 && nb.some(n => n.amount !== nb[0].amount);

        const escapeHtml = value => $('<div>').text(String(value ?? '')).html();
        const categoryDefinitions = [
            { key: 'accommodation', label: 'Accommodation',  types: ['accommodation'] },
            { key: 'cleaning',      label: 'Cleaning',       types: ['cleaning'] },
            { key: 'fees',          label: 'Additional fees', types: ['fee'] },
            { key: 'taxes',         label: 'Taxes',          types: ['tax'] },
            { key: 'discounts',     label: 'Discounts',      types: ['discount'] },
            { key: 'deposits',      label: 'Security deposit', types: ['deposit'] },
            { key: 'other',         label: 'Other charges',  types: [] }
        ];
        const categorized = Object.fromEntries(
            categoryDefinitions.map(category => [category.key, []])
        );

        // Categorize by canonical PMS type only. The PMS labels remain visible
        // inside each expandable section, but never determine the category.
        (quote.line_items || []).forEach(item => {
            const type = String(item.type || '').toLowerCase();
            const category = categoryDefinitions.find(group => group.types.includes(type));
            categorized[category ? category.key : 'other'].push(item);
        });

        html += '<div class="pms-fee-categories">';
        categoryDefinitions.forEach(category => {
            const items = categorized[category.key];
            if (!items.length) return;

            const categoryTotal = items.reduce(
                (sum, item) => sum + Number(item.amount || 0),
                0
            );
            let categoryHint = items.length > 1 ? `${items.length} items` : (items[0].label || '');
            if (category.key === 'deposits' && quote.deposit_in_total === false) {
                categoryHint = 'Refundable hold, not included in total';
            }

            const detailRows = items.map(item => {
                const itemLabel = escapeHtml(item.label || category.label);
                let detail = `<div class="pms-fee-detail-row">
                    <span>${itemLabel}</span>
                    <span>${fmt(item.amount, currency)}</span>
                </div>`;

                if (item.type === 'accommodation' && hasVariableRates) {
                    const rows = nb.map(n =>
                        `<span class="pms-night-row">
                            <span>${escapeHtml(n.date)}</span>
                            <span>${fmt(n.amount, currency)}</span>
                        </span>`
                    ).join('');
                    detail += `<div class="pms-night-detail">${rows}</div>`;
                }

                return detail;
            }).join('');

            html += `<details class="pms-fee-category">
                <summary>
                    <span class="pms-category-label">
                        ${category.label}
                        <small>${escapeHtml(categoryHint)}</small>
                    </span>
                    <span class="pms-category-amount">${fmt(categoryTotal, currency)}</span>
                </summary>
                <div class="pms-category-items">${detailRows}</div>
            </details>`;
        });
        html += '</div>';

        html += '<div class="pms-fee-divider"></div>';
        html += `<div class="pms-fee-row pms-fee-total"><span>Total</span><span>${fmt(grandTotal, currency)}</span></div>`;

        // Approximate total warning (calendar fallback — taxes not included)
        if (quote.approximate) {
            html += `<p class="pms-approximate-note">Estimated total — taxes not included. Final price confirmed at booking.</p>`;
        }
        // Manual collection warning (Lodgify: part of payment outside Lodgify Payments)
        if (quote.manual_collection) {
            html += `<p class="pms-approximate-note">${fmt(quote.manual_collection, currency)} to be collected separately by the host.</p>`;
        }
        // Identity verification required (Lodgify SCA/PSD2 markets)
        if (quote.requires_verification) {
            html += `<p class="pms-approximate-note">Identity verification required before booking is confirmed.</p>`;
        }

        // ── Payment schedule (split / installment payments) ─────────────────
        const payments = quote.scheduled_payments || [];
        if (payments.length > 1) {
            html += '<div class="pms-payment-schedule">';
            html += '<p class="pms-schedule-title"><i class="fa-solid fa-calendar-days me-1"></i><strong>Payment Schedule</strong></p>';
            payments.forEach(p => {
                const label   = p.label || (p.type === 'deposit' ? 'Deposit' : 'Payment');
                let duePart   = '';
                if (p.date_due) {
                    // Try to parse as a date; fall back to showing the raw string (e.g. "On agreement")
                    const d = new Date(p.date_due + 'T00:00:00');
                    if (!isNaN(d.getTime())) {
                        duePart = ' &mdash; due ' + d.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
                    } else {
                        duePart = ' &mdash; ' + p.date_due;
                    }
                }
                html += `<div class="pms-fee-row pms-schedule-row">
                            <span class="pms-schedule-label">${label}${duePart}</span>
                            <span>${fmt(p.amount, currency)}</span>
                         </div>`;
            });
            html += '</div>';
        }

        // ── Optional add-ons — UI controls driven by canonical fee metadata ──────
        //
        // UI RULES (never use fee name text for logic — use type/metadata):
        //   is_per_pet=true       → connect to pet counter, no duplicate control shown
        //   is_per_guest (mandatory) → auto-included in breakdown; no selector
        //   is_per_guest (optional)  → show stepper with max = current guest count
        //   requires_approval        → clickable "Request this service" button
        //   percentage != null       → toggle only, never show computed amount
        //   is_quantity_selectable   → stepper
        //   else                     → yes/no toggle (0 or 1)
        const opts = quote.optional_items || [];
        const currentPets   = parseInt($('#pet-data').val()) || 0;
        const currentGuests = (parseInt($('#adults-data').val()) || 1)
                            + (parseInt($('#child-data').val()) || 0);
        // window.pmsRequestedServices stores approval-required selections separately
        // so they are never included in the PMS quote or Stripe charge
        window.pmsRequestedServices = window.pmsRequestedServices || {};

        // ALL optional fees are shown; mandatory per-guest fees are auto-shown in
        // the line_items breakdown and excluded from the selector section.
        const visibleOpts = opts.filter(opt => !opt.is_mandatory);

        if (visibleOpts.length) {
            html += '<div class="pms-optional-section"><p class="pms-opt-title">Add to your stay</p>';
            visibleOpts.forEach(opt => {
                const isPerPet    = !!opt.is_per_pet;
                const isPerGuest  = !!opt.is_per_guest;
                const isApproval  = !!opt.requires_approval;
                const isPct       = opt.percentage != null && opt.percentage > 0;
                const isQtySel    = !!opt.is_quantity_selectable;
                const perUnit     = opt.per_unit || '/stay';

                // Max qty: per-pet capped by PMS_MAX_PETS, per-guest by guest count
                const maxQty = isPerPet
                    ? PMS_MAX_PETS
                    : isPerGuest
                        ? Math.min(currentGuests, opt.max_qty || currentGuests)
                        : (opt.max_qty || 1);

                // Current qty: per-pet mirrors pet counter, per-guest mirrors guest count
                const currentQty = isPerPet    ? currentPets
                                 : isPerGuest  ? currentGuests
                                 : (window.pmsOptQty[opt.id] ?? 0);

                // Amount display — NEVER compute JS amount for % fees
                let amtLabel;
                if (isApproval) {
                    amtLabel = 'Price on request';
                } else if (isPct) {
                    amtLabel = opt.percentage + '%' + perUnit;
                } else {
                    amtLabel = fmt(opt.amount || 0, currency) + perUnit;
                    if (opt.tax_rate > 0) amtLabel += ' + ' + opt.tax_rate + '% tax';
                }

                // ── Control rendering ─────────────────────────────────────────
                // 1. Approval-required: clickable "Request this service" button
                //    Stored in window.pmsRequestedServices, NOT in pmsOptQty,
                //    NOT sent to PMS quote, NOT charged until approved.
                // 2. Per-pet: auto-label showing pet count; stepper hidden
                // 3. Per-guest optional: stepper capped at guest count
                // 4. Quantity selectable: stepper
                // 5. Default: yes/no toggle (0 or 1)
                let controlHtml;
                const isRequested = !!window.pmsRequestedServices[opt.id];

                if (isApproval) {
                    controlHtml = `
                        <button type="button"
                                class="pms-request-btn btn btn-sm btn-outline-secondary"
                                data-opt-id="${opt.id}"
                                data-opt-label="${opt.label.replace(/"/g,'&quot;')}"
                                style="font-size:.78rem; white-space:nowrap; ${isRequested ? 'background:#e8f4ec;border-color:#2d8a4e;color:#2d8a4e;' : ''}">
                            ${isRequested ? '✓ Requested' : 'Request this service'}
                        </button>`;
                } else if (isPerPet) {
                    controlHtml = `<span class="pms-opt-auto-label" style="font-size:.85rem;color:#666;">
                        × ${currentPets} pet${currentPets !== 1 ? 's' : ''}</span>`;
                } else if (isPerGuest && !isQtySel) {
                    // Optional per-guest fee: auto-shows guest count, not independently selectable
                    const isSelected = (window.pmsOptQty[opt.id] ?? 0) > 0;
                    controlHtml = `
                        <div class="pms-opt-stepper">
                            <button type="button" class="pms-step-btn pms-step-down"
                                    data-opt-id="${opt.id}" ${!isSelected ? 'disabled' : ''}>−</button>
                            <span class="pms-step-val">${isSelected ? currentGuests : 0}</span>
                            <button type="button" class="pms-step-btn pms-step-up"
                                    data-opt-id="${opt.id}" ${isSelected ? 'disabled' : ''}>+</button>
                        </div>`;
                } else {
                    // Quantity stepper (isQtySel) or yes/no toggle
                    const stepMax = isQtySel ? maxQty : 1;
                    controlHtml = `
                        <div class="pms-opt-stepper">
                            <button type="button" class="pms-step-btn pms-step-down"
                                    data-opt-id="${opt.id}" ${currentQty <= 0 ? 'disabled' : ''}>−</button>
                            <span class="pms-step-val">${currentQty}</span>
                            <button type="button" class="pms-step-btn pms-step-up"
                                    data-opt-id="${opt.id}" ${currentQty >= stepMax ? 'disabled' : ''}>+</button>
                        </div>`;
                }

                html += `<div class="pms-opt-row"
                              data-opt-id="${opt.id}"
                              data-max-qty="${maxQty}"
                              data-is-pet="${isPerPet}"
                              data-is-guest="${isPerGuest}"
                              data-requires-approval="${isApproval}">
                    <div class="pms-opt-info">
                        <span class="pms-opt-name">${opt.label}</span>
                        <span class="pms-opt-price">${amtLabel}</span>
                    </div>
                    ${controlHtml}
                </div>`;
            });
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    // Wire stepper buttons — each click updates pmsOptQty and triggers a debounced
    // server re-quote so the displayed total is always PMS-authoritative.
    function wireOptionalSteppers() {
        $('.pms-step-up').off('click').on('click', function() {
            const optId  = $(this).data('opt-id');
            const row    = $(`.pms-opt-row[data-opt-id="${optId}"]`);
            const maxQty = parseInt(row.data('max-qty')) || 1;
            let qty = (window.pmsOptQty[optId] ?? 0);
            if (qty >= maxQty || !window.pmsLastQuote) return;
            qty++;
            window.pmsOptQty[optId] = qty;
            if (row.data('is-pet') === true || row.data('is-pet') === 'true') $('#pet-data').val(qty);
            // Update stepper display immediately so UI feels responsive
            $(this).siblings('.pms-step-val').text(qty);
            $(this).prop('disabled', qty >= maxQty);
            $(this).siblings('.pms-step-down').prop('disabled', qty <= 0);
            // Debounced server re-quote — PMS calculates the authoritative total
            debouncedReQuote(450);
        });

        $('.pms-step-down').off('click').on('click', function() {
            const optId = $(this).data('opt-id');
            const row   = $(`.pms-opt-row[data-opt-id="${optId}"]`);
            const maxQty = parseInt(row.data('max-qty')) || 1;
            let qty = (window.pmsOptQty[optId] ?? 0);
            if (qty <= 0 || !window.pmsLastQuote) return;
            qty--;
            window.pmsOptQty[optId] = qty;
            if (row.data('is-pet') === true || row.data('is-pet') === 'true') $('#pet-data').val(qty);
            $(this).siblings('.pms-step-val').text(qty);
            $(this).prop('disabled', qty <= 0);
            $(this).siblings('.pms-step-up').prop('disabled', qty >= maxQty);
            debouncedReQuote(450);
        });
    }

    // ── Request button (approval-required services) ───────────────────────────
    // Stores the request in window.pmsRequestedServices.
    // Does NOT add to pmsOptQty — approval-required services are NEVER sent to the
    // PMS quote and NEVER charged until a human approves them.
    $(document).on('click', '.pms-request-btn', function() {
        const optId    = $(this).data('opt-id');
        const optLabel = $(this).data('opt-label');
        const already  = !!window.pmsRequestedServices[optId];

        if (already) {
            delete window.pmsRequestedServices[optId];
            $(this).text('Request this service')
                   .css({'background': '', 'border-color': '', 'color': ''});
        } else {
            window.pmsRequestedServices[optId] = 1;
            $(this).text('✓ Requested')
                   .css({'background': '#e8f4ec', 'border-color': '#2d8a4e', 'color': '#2d8a4e'});
        }
        // Update form field immediately
        $("#requested_services_input").val(JSON.stringify(window.pmsRequestedServices));
    });

    // Fetch quote from server and render breakdown
    // New dates → reset optional qty selections (guest is starting fresh)
    // Show a friendly inline error in the quote area (no page refresh).
    function showQuoteError(msg, type) {
        const isMinStay = (type === 'min_stay');
        const icon      = isMinStay ? 'fa-calendar-xmark' : 'fa-circle-exclamation';
        const cls       = isMinStay ? 'alert-warning'     : 'alert-danger';
        $("#gaurav-new-data-area").html(
            `<div class="alert ${cls} py-2 px-3 mt-2 mb-0" style="font-size:14px;">` +
            `<i class="fa-solid ${icon} me-2"></i>${msg}</div>`
        );
        $("#submit-button-gaurav-data").hide();
    }

    function fetchPmsQuote(start, end, pets, callback) {
        const datesChanged = (window.pmsCurrentDates.start !== start || window.pmsCurrentDates.end !== end);
        if (datesChanged) {
            window.pmsOptQty = {};
            // Reset promo and contact form when dates change
            window.pmsPromoCode = '';
            $(".promo_code_form").val('');
            $("#promo-code-input").val('');
            $("#promo-code-clear").hide();
            $("#promo-code-msg").html('');
        }

        window.pmsCurrentDates = { start, end };

        // ── Minimum stay pre-check (instant, no API call) ─────────────────────
        // Use per-checkin-date override if available, else global min, else static fallback.
        const nights    = Math.round((new Date(end) - new Date(start)) / 86400000);
        const byDate    = pmsAvailData.min_stay_by_date || {};
        const effectiveMin = byDate[start] || pmsAvailData.min_stay || PMS_MIN_STAY || 1;
        if (effectiveMin > 1 && nights < effectiveMin) {
            showQuoteError(
                `Minimum stay is <strong>${effectiveMin} nights</strong>. ` +
                `Your selection is ${nights} night${nights !== 1 ? 's' : ''}. ` +
                `Please adjust your dates.`,
                'min_stay'
            );
            return;
        }

        const adults = parseInt($("#adults-data").val()) || 1;
        const childs = parseInt($("#child-data").val()) || 0;

        $.post(PMS_QUOTE_URL, {
            room_id:           PMS_ROOM_ID,
            checkin:           start,
            checkout:          end,
            adults:            adults,
            children:          childs,
            pets:              pets || 0,
            promo_code:        window.pmsPromoCode || '',
            // Pass selected add-on quantities so the server can include them in the PMS
            // re-quote — the returned total is therefore authoritative for the selected config.
            optional_services: JSON.stringify(window.pmsOptQty),
        }, function(quote) {
            if (!quote.ok) {
                showQuoteError(quote.error || 'Quote unavailable', quote.error_type || '');
                return;
            }
            window.pmsLastQuote = quote;   // store for instant re-renders on stepper clicks
            $("#gaurav-new-data-area").html(renderPmsBreakdown(quote));
            $("#submit-button-gaurav-data").show();
            $("#promo-code-area").show(); // reveal promo field once we have a valid quote
            wireOptionalSteppers();
            if (callback) callback(quote);
        }, 'json');
    }

    // ── Promo code (Hostfully only) ────────────────────────────────────────────
    // Apply: re-fetch quote with the code; show discount line if valid, error if not.
    // Clear: re-fetch without the code and reset state.
    $(document).on("click", "#promo-code-apply", function() {
        const code = $("#promo-code-input").val().trim().toUpperCase();
        if (!code) return;

        const { start, end } = window.pmsCurrentDates;
        if (!start || !end) {
            $("#promo-code-msg").html('<span class="text-danger">Select dates first.</span>');
            return;
        }

        const pets   = parseInt($("#pet-data").val()) || 0;
        const adults = parseInt($("#adults-data").val()) || 1;
        const childs = parseInt($("#child-data").val()) || 0;

        $("#promo-code-msg").html('<span class="text-muted">Checking…</span>');

        $.post(PMS_QUOTE_URL, {
            room_id:    PMS_ROOM_ID,
            checkin:    start,
            checkout:   end,
            adults:     adults,
            children:   childs,
            pets:       pets,
            promo_code: code,
        }, function(quote) {
            if (!quote.ok) {
                $("#promo-code-msg").html(`<span class="text-danger">${quote.error || 'Quote failed.'}</span>`);
                return;
            }

            // Check whether the response actually contains a discount line item
            const hasDiscount = (quote.line_items || []).some(i => i.type === 'discount');
            if (!hasDiscount) {
                $("#promo-code-msg").html('<span class="text-danger">Invalid or expired promo code.</span>');
                return;
            }

            // Success — store code and re-render
            window.pmsPromoCode = code;
            window.pmsLastQuote = quote;
            $(".promo_code_form").val(code);
            $("#promo-code-clear").show();
            $("#promo-code-msg").html('<span class="text-success">✓ Promo code applied!</span>');
            $("#gaurav-new-data-area").html(renderPmsBreakdown(quote));
            wireOptionalSteppers();
        }, 'json');
    });

    $(document).on("click", "#promo-code-clear", function() {
        window.pmsPromoCode = '';
        $(".promo_code_form").val('');
        $("#promo-code-input").val('');
        $("#promo-code-clear").hide();
        $("#promo-code-msg").html('');

        const { start, end } = window.pmsCurrentDates;
        if (start && end) {
            fetchPmsQuote(start, end, parseInt($("#pet-data").val()) || 0);
        }
    });

    // ── Booking Logic ──────────────────────────────────────────────────────────
    function ajaxCallingData() {
        let adults = $("#adults-data").val();
        let childs = $("#child-data").val();
        let pets = parseInt($("#pet-data").val()) || 0;
        let start = $("#start_date").val();
        let end = $("#end_date").val();

        // Sync to form
        $(".adults_form").val(adults);
        $(".childs_form").val(childs);
        $(".pets_form").val(pets);
        $(".start_date_form").val(start);
        $(".end_date_form").val(end);
        // Serialize confirmed optional add-ons (not approval-required services)
        $("#optional_services_input").val(JSON.stringify(window.pmsOptQty || {}));
        // Requested (approval-required) services stored separately — not charged until approved
        $("#requested_services_input").val(JSON.stringify(window.pmsRequestedServices || {}));

        if (start && end) {
            if (IS_PMS_ROOM) {
                // PMS listing → real-time quote from adapter API (100% accurate pricing)
                fetchPmsQuote(start, end, pets);
                return;
            }
            // CRM property → existing AJAX path
            $.post("{{route('checkajax-get-quote')}}", {
                start_date: start,
                end_date: end,
                adults: adults,
                childs: childs,
                pets: pets,
                property_id: "{{ $data->id }}"
            }, function (data) {
                if (data.status == 200) {
                    $("#gaurav-new-data-area").html(data.data_view);
                    $("#submit-button-gaurav-data").show();
                    $("#form_start_date").val(start);
                    $("#form_end_date").val(end);
                } else {
                    $("#gaurav-new-data-area").html('');
                    $("#submit-button-gaurav-data").hide();
                    if (data.message) toastr.error(data.message);
                }
            });
        }
    }

    // ── Real-time availability from PMS ────────────────────────────────────────
    // Replaces the old PHP-rendered iCal snapshot with a live API call.
    // Fetches 3 months at a time; re-fetches when guest navigates months.
    const PMS_AVAIL_URL  = "{{ route('pms-availability') }}";
    const PMS_ROOM_DB_ID = "{{ $data->id }}";    // DB id — controller resolves pms_source

    const HAS_PRELOADED_CALENDAR = {{ $hasPreloadedCalendar ? 'true' : 'false' }};
    const PMS_CAL_CURRENCY = @json(
        (($pricing['currency'] ?? 'USD') === 'USD' || ($pricing['currency'] ?? 'USD') === '$')
            ? '$'
            : ($pricing['currency'] ?? 'USD') . ' '
    );

    // Pre-loaded snapshot from pms_calendar. This keeps the first calendar render
    // stable while the live endpoint refreshes the same date window in the background.
    let pmsAvailData = {
        blocked:          {!! json_encode(array_values($preloadedBlocked)) !!},
        no_checkin:       {!! json_encode(array_values($preloadedNoCheckin)) !!},
        no_checkout:      {!! json_encode(array_values($preloadedNoCheckout)) !!},
        min_stay:         {{ (int)($keyFacts['min_stay'] ?? 1) }},
        min_stay_by_date: {!! json_encode((object)$preloadedMinStayByDate) !!},
        rates:            {!! json_encode((object)$preloadedRates) !!},
        rates_from:       {{ $room->pms_source === 'lodgify' && !empty($preloadedRates) ? 'true' : 'false' }}
    };
    let pmsAvailFetched  = HAS_PRELOADED_CALENDAR;
    let pmsAvailFetching = false;
    let pmsAvailCallbacks = [];
    let pmsAvailFetchedUntil = HAS_PRELOADED_CALENDAR
        ? "{{ now()->addYear()->toDateString() }}"
        : null;

    function replaceDateWindow(existing, incoming, start, end) {
        return [
            ...(existing || []).filter(date => date < start || date > end),
            ...(incoming || [])
        ].filter((date, index, all) => all.indexOf(date) === index);
    }

    function localIsoDate(value) {
        const date = value instanceof Date ? value : new Date(value);
        if (isNaN(date.getTime())) return null;

        return date.getFullYear() + '-'
            + String(date.getMonth() + 1).padStart(2, '0') + '-'
            + String(date.getDate()).padStart(2, '0');
    }

    function fetchPmsAvailability(startDate, callback) {
        if (callback) pmsAvailCallbacks.push(callback);
        if (pmsAvailFetching) return;
        pmsAvailFetching = true;

        const start = startDate || localIsoDate(new Date());
        // Fetch 12 months on initial load so navigation rarely triggers a rebuild;
        // subsequent calls (when navigating beyond the window) fetch 3 months.
        const monthsAhead = startDate ? 3 : 12;
        const endObj = new Date(start + 'T12:00:00');
        endObj.setMonth(endObj.getMonth() + monthsAhead);
        endObj.setDate(0); // last day of that month
        const end = localIsoDate(endObj);

        $.get(PMS_AVAIL_URL, { room_id: PMS_ROOM_DB_ID, start_date: start, end_date: end })
         .done(function(data) {
            if (data && data.ok) {
                // Replace this fetched window instead of unioning it forever. This
                // allows a newly-open PMS date to remove a stale local blocked date.
                pmsAvailData.blocked = replaceDateWindow(
                    pmsAvailData.blocked, data.blocked, start, end
                );
                pmsAvailData.no_checkin = replaceDateWindow(
                    pmsAvailData.no_checkin, data.no_checkin, start, end
                );
                pmsAvailData.no_checkout = replaceDateWindow(
                    pmsAvailData.no_checkout, data.no_checkout, start, end
                );
                Object.keys(pmsAvailData.rates || {}).forEach(function(date) {
                    if (date >= start && date <= end) delete pmsAvailData.rates[date];
                });
                pmsAvailData.rates = Object.assign(pmsAvailData.rates || {}, data.rates || {});
                // rates_from: true means these are lowest-band "From $X" prices (Lodgify multi-band)
                pmsAvailData.rates_from = !!data.rates_from;
                // Store min stay — take the highest seen across fetches (conservative)
                if ((data.min_stay || 1) > (pmsAvailData.min_stay || 1)) {
                    pmsAvailData.min_stay = data.min_stay;
                }
                Object.keys(pmsAvailData.min_stay_by_date || {}).forEach(function(date) {
                    if (date >= start && date <= end) delete pmsAvailData.min_stay_by_date[date];
                });
                pmsAvailData.min_stay_by_date = Object.assign(
                    pmsAvailData.min_stay_by_date || {},
                    data.min_stay_by_date || {}
                );
                pmsAvailFetched = true;
                // Track how far ahead we've fetched
                pmsAvailFetchedUntil = end;
                $('#calendar-rate-status').text('');
            }
         })
         .fail(function() {
            if (!HAS_PRELOADED_CALENDAR) {
                $('#calendar-rate-status').text('Latest rates could not be loaded. Please try again.');
            }
         })
         .always(function() {
            pmsAvailFetching = false;
            const callbacks = pmsAvailCallbacks.splice(0);
            callbacks.forEach(function(done) { done(pmsAvailData); });
            document.dispatchEvent(new CustomEvent('pms:availability-updated', {
                detail: pmsAvailData
            }));
         });
    }

    // ── Litepicker Initialization ─────────────────────────────────────────────
    $(document).ready(function() {
        const triggerEl = document.getElementById('dateTrigger');
        if (!triggerEl) return;

        let picker = null;

        function decorateLitepickerDays() {
            const rates = pmsAvailData.rates || {};
            document.querySelectorAll('.litepicker .day-item[data-time]').forEach(function(day) {
                day.classList.remove('pms-checkin-only', 'pms-checkout-only', 'pms-unavailable-night');
                day.querySelector('.lp-price')?.remove();

                const ts   = parseInt(day.getAttribute('data-time'));
                // Use local date getters — toISOString() returns UTC which shifts the
                // date by one day in non-UTC timezones (e.g. India UTC+5:30 reads the
                // cell's midnight as the previous day in UTC, mapping the wrong rate).
                const _d   = new Date(ts);
                const date = _d.getFullYear() + '-' +
                             String(_d.getMonth() + 1).padStart(2, '0') + '-' +
                             String(_d.getDate()).padStart(2, '0');
                const price = rates[date];
                const isBlocked = (pmsAvailData.blocked || []).includes(date);
                const noCheckin = (pmsAvailData.no_checkin || []).includes(date);
                const noCheckout= (pmsAvailData.no_checkout || []).includes(date);
                let explanation = 'Available for check-in and check-out';

                if (isBlocked) {
                    day.classList.add('pms-unavailable-night');
                    explanation = 'Unavailable night. It may be selectable only as a checkout turnover date.';
                } else if (noCheckin && noCheckout) {
                    explanation = 'No check-in or check-out on this date';
                } else if (noCheckin) {
                    day.classList.add('pms-checkout-only');
                    explanation = 'Checkout only. Check-in is not allowed on this date.';
                } else if (noCheckout) {
                    day.classList.add('pms-checkin-only');
                    explanation = 'Check-in only. Checkout is not allowed on this date.';
                }

                if (price && !isBlocked) {
                    explanation += ' Nightly rate ' + PMS_CAL_CURRENCY + Math.round(price).toLocaleString() + '.';
                }
                day.setAttribute('title', explanation);
                day.setAttribute('aria-label', date + '. ' + explanation);

                if (price && !isBlocked) {
                    const span = document.createElement('span');
                    span.className = 'lp-price';
                    span.textContent = (pmsAvailData.rates_from ? 'From ' : '')
                        + PMS_CAL_CURRENCY + Math.round(price).toLocaleString();
                    day.appendChild(span);
                }
            });

            const pickerRoot = document.querySelector('.litepicker');
            if (pickerRoot && !pickerRoot.querySelector('.pms-calendar-key')) {
                pickerRoot.insertAdjacentHTML('beforeend', `
                    <div class="pms-calendar-key">
                        <span><i class="key-available"></i>Available</span>
                        <span><i class="key-checkin"></i>Check-in only</span>
                        <span><i class="key-checkout"></i>Check-out only</span>
                        <span><i class="key-unavailable"></i>Unavailable</span>
                    </div>
                `);
            }
        }

        function buildDatepicker(blocked, noCheckinDates, noCheckoutDates) {
            // Preserve any dates already selected (user pick or URL params)
            // before destroying — so rebuilding after availability loads doesn't
            // wipe the visual selection.
            var savedStart = $('#start_date').val() || null;
            var savedEnd   = $('#end_date').val()   || null;

            if (picker) { try { picker.destroy(); } catch(e) {} }

            blocked        = blocked        || [];
            noCheckinDates = noCheckinDates || [];
            noCheckoutDates= noCheckoutDates|| [];
            const compactCalendar = window.matchMedia('(max-width: 767px)').matches;

            picker = new Litepicker({
                element:          document.getElementById('demo17'),
                parentEl:         document.body,
                singleMode:       false,
                numberOfMonths:   compactCalendar ? 1 : 2,
                numberOfColumns:  compactCalendar ? 1 : 2,
                minDate:          new Date(),
                autoApply:        true,
                tooltipNumber:    function(totalDays) { return totalDays - 1; },
                tooltipText:      { one: 'night', other: 'nights' },
                lockDays:         [],   // Must be empty — all locking via lockDaysFilter for turnover day support
                lockDaysFilter: function(d1, d2, pickedDates) {
                    if (!d1) return false;
                    // Use local date getters to avoid UTC-offset date-shift
                    const toDateStr = function(d) {
                        var dt = (d && typeof d.getFullYear === 'function') ? d : new Date(d);
                        if (!dt || isNaN(dt.getTime())) return null;
                        return dt.getFullYear() + '-'
                            + String(dt.getMonth() + 1).padStart(2, '0') + '-'
                            + String(dt.getDate()).padStart(2, '0');
                    };
                    const dateStr = toDateStr(d1);
                    if (!dateStr) return false;

                    // Always read from pmsAvailData so navigation fetches are reflected
                    // without needing to rebuild the picker.
                    const blk  = pmsAvailData.blocked     || [];
                    const noCI = pmsAvailData.no_checkin  || [];
                    const noCO = pmsAvailData.no_checkout || [];

                    if (!pickedDates || pickedDates.length === 0) {
                        // Selecting check-in: block fully booked days + no-checkin days
                        return blk.includes(dateStr) || noCI.includes(dateStr);
                    }

                    // Selecting check-out
                    const startStr = toDateStr(pickedDates[0]);

                    // Block if ANY blocked night falls strictly between check-in and this
                    // checkout — the guest would have to pass through a booked period.
                    if (blk.some(b => b > startStr && b < dateStr)) return true;

                    // A checkout date that IS blocked is fine — turnover day.
                    // The departing guest leaves before the arriving guest checks in.

                    // Respect PMS no-checkout restrictions
                    if (noCO.includes(dateStr)) return true;

                    return false;
                },
                setup: function(p) {
                    p.on('selected', function(d1, d2) {
                        // Use local date getters — toISOString() returns UTC which is wrong
                        // for negative-offset timezones (midnight local = prev day UTC).
                        const fmt = function(d) {
                            var dt = (d && typeof d.getFullYear === 'function') ? d : new Date(d);
                            return dt.getFullYear() + '-'
                                + String(dt.getMonth() + 1).padStart(2, '0') + '-'
                                + String(dt.getDate()).padStart(2, '0');
                        };
                        const start = fmt(d1);
                        const end   = fmt(d2);
                        $('#start_date').val(start);
                        $('#end_date').val(end);
                        $('#checkin-display').val(start);
                        $('#checkout-display').val(end);
                        ajaxCallingData();
                    });
                    p.on('render', function() {
                        setTimeout(decorateLitepickerDays, 30);
                        // Fetch more data if navigated beyond fetched window
                        const dayEls = document.querySelectorAll('.litepicker .day-item[data-time]');
                        if (dayEls.length && pmsAvailFetchedUntil) {
                            const lastDay  = dayEls[dayEls.length - 1];
                            const lastDate = localIsoDate(
                                new Date(parseInt(lastDay.getAttribute('data-time')))
                            );
                            if (lastDate > pmsAvailFetchedUntil) {
                                fetchPmsAvailability(lastDate, function() {
                                    // lockDaysFilter reads pmsAvailData directly, so just
                                    // trigger a re-render by setting empty lockDays.
                                    picker.setLockDays([]);
                                    setTimeout(decorateLitepickerDays, 30);
                                });
                            }
                        }
                    });
                }
            });

            // Re-apply any dates already in the hidden inputs (e.g. from URL params).
            if (savedStart && savedEnd) {
                try { picker.setDateRange(savedStart, savedEnd); } catch(e) {}
            }
        }

        // Build once — lockDaysFilter reads pmsAvailData directly so the picker
        // never needs to be destroyed/rebuilt when availability data arrives.
        buildDatepicker([], [], []);

        // Fetch 12 months of availability then trigger a re-render in-place.
        // setLockDays([]) causes Litepicker to re-evaluate lockDaysFilter for
        // every visible day using the now-populated pmsAvailData — no destroy,
        // no close, calendar stays open if the user already has it open.
        fetchPmsAvailability(null, function() {
            if (picker) {
                try { picker.setLockDays([]); } catch(e) {}
                setTimeout(decorateLitepickerDays, 30);
            }
        });

        // Open picker when the date trigger UI is clicked
        triggerEl.addEventListener('click', function() {
            if (!picker) return;
            if (!pmsAvailFetched && pmsAvailFetching) {
                $('#calendar-rate-status').text('Loading latest rates and availability…');
                fetchPmsAvailability(null, function() {
                    picker.show();
                    setTimeout(decorateLitepickerDays, 30);
                });
                return;
            }
            picker.show();
            setTimeout(decorateLitepickerDays, 30);
        });

        // Pre-fill if URL params exist
        @if(request('start_date') && request('end_date'))
            setTimeout(function() {
                const start = "{{ request('start_date') }}";
                const end   = "{{ request('end_date') }}";
                if (picker) picker.setDateRange(start, end);
                ajaxCallingData();
            }, 500);
        @elseif(request('adults') || request('childs'))
            updateGuestDisplay();
            ajaxCallingData();
        @endif
    });

    // ── Availability Display Calendar ─────────────────────────────────────────
    (function() {
        const ROOM_ID   = "{{ $data->id }}";
        const AVAIL_URL = "{{ route('pms-availability') }}";
        const MONTHS    = ['January','February','March','April','May','June',
                           'July','August','September','October','November','December'];
        const DAYS      = ['Su','Mo','Tu','We','Th','Fr','Sa'];

        // Currency symbol for rate labels — derived from PMS property data, default $
        @php
            $availCalCurrencyRaw = $pricing['currency'] ?? 'USD';
            $availCalCurrency = ($availCalCurrencyRaw === 'USD' || $availCalCurrencyRaw === '$') ? '$' : htmlspecialchars($availCalCurrencyRaw) . ' ';
        @endphp
        const CAL_CURRENCY = "{{ $availCalCurrency }}";

        let blockedSet  = new Set();
        let noCheckinSet = new Set();
        let noCheckoutSet = new Set();
        let ratesMap    = {};   // { "YYYY-MM-DD": nightly_price } for calendar display
        let currentDate = new Date();
        currentDate.setDate(1);

        function localDateString(date) {
            return date.getFullYear() + '-'
                + String(date.getMonth() + 1).padStart(2, '0') + '-'
                + String(date.getDate()).padStart(2, '0');
        }

        function fetchAndRender() {
            const start = localDateString(currentDate);
            const endObj = new Date(currentDate);
            endObj.setMonth(endObj.getMonth() + 2);
            const end = localDateString(endObj);

            $.get(AVAIL_URL, { room_id: ROOM_ID, start_date: start, end_date: end })
             .done(function(data) {
                if (data && data.ok) {
                    blockedSet = new Set(data.blocked || []);
                    noCheckinSet = new Set(data.no_checkin || []);
                    noCheckoutSet = new Set(data.no_checkout || []);
                    // Merge rates — keep earlier-fetched rates when navigating forward
                    ratesMap = Object.assign(ratesMap, data.rates || {});
                    // Propagate rates_from flag (Lodgify multi-band "From $X")
                    if (data.rates_from) ratesMap._from = true;
                }
                render();
             })
             .fail(function() { render(); });
        }

        function render() {
            const wrap = document.getElementById('avail-cal-wrap');
            if (!wrap) return;

            const today    = new Date(); today.setHours(0,0,0,0);
            const month1   = new Date(currentDate);
            const month2   = new Date(currentDate); month2.setMonth(month2.getMonth() + 1);

            const canPrev  = new Date(month1.getFullYear(), month1.getMonth(), 1) > new Date(today.getFullYear(), today.getMonth(), 1);

            wrap.innerHTML = `
                <div class="ac-grid">
                    ${buildMonth(month1, today, true, canPrev)}
                    ${buildMonth(month2, today, false, false)}
                </div>
                <div class="ac-legend">
                    <div class="ac-legend-item"><div class="ac-legend-dot avail"></div> Available</div>
                    <div class="ac-legend-item"><div class="ac-legend-dot checkin-only"></div> Check-in only</div>
                    <div class="ac-legend-item"><div class="ac-legend-dot checkout-only"></div> Check-out only</div>
                    <div class="ac-legend-item"><div class="ac-legend-dot booked"></div> Booked / Unavailable</div>
                </div>`;

            // Attach to ALL prev/next buttons — querySelector only returns the first
            // match which is the hidden button in the left month header, leaving the
            // visible button in the right month header without a listener.
            wrap.querySelectorAll('.ac-prev').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    currentDate.setMonth(currentDate.getMonth() - 1);
                    fetchAndRender();
                });
            });
            wrap.querySelectorAll('.ac-next').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    currentDate.setMonth(currentDate.getMonth() + 1);
                    fetchAndRender();
                });
            });
        }

        function buildMonth(date, today, showPrev, canPrev) {
            const y = date.getFullYear(), m = date.getMonth();
            const firstDay = new Date(y, m, 1).getDay();
            const daysInMonth = new Date(y, m + 1, 0).getDate();

            let days = '';
            for (let i = 0; i < firstDay; i++) days += '<div class="ac-day ac-blank"></div>';
            for (let d = 1; d <= daysInMonth; d++) {
                const ds   = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                const dt   = new Date(y, m, d);
                const isPast    = dt < today;
                const isToday   = dt.getTime() === today.getTime();
                const isBooked  = blockedSet.has(ds);
                const noCheckin = noCheckinSet.has(ds);
                const noCheckout= noCheckoutSet.has(ds);
                let cls = 'ac-day';
                if (isPast)   cls += ' ac-past';
                else if (isBooked) cls += ' ac-booked';
                else if (isToday)  cls += ' ac-today';
                else               cls += ' ac-available';
                if (!isPast && !isBooked && noCheckin && !noCheckout) cls += ' ac-checkout-only';
                if (!isPast && !isBooked && noCheckout && !noCheckin) cls += ' ac-checkin-only';

                // Show nightly rate on available/today days if we have it
                const priceVal = (!isPast && !isBooked && ratesMap[ds]) ? ratesMap[ds] : null;
                const priceHtml = priceVal
                    ? `<span class="ac-price">${ratesMap._from ? 'From ' : ''}${CAL_CURRENCY}${Math.round(priceVal).toLocaleString()}</span>`
                    : '';

                let tooltip = 'Available for check-in and check-out';
                if (isPast) tooltip = 'Past date';
                else if (isBooked) tooltip = 'Booked or unavailable';
                else if (noCheckin && noCheckout) tooltip = 'No check-in or check-out on this date';
                else if (noCheckin) tooltip = 'Checkout only. Check-in is not allowed.';
                else if (noCheckout) tooltip = 'Check-in only. Checkout is not allowed.';
                if (priceVal) tooltip += ` Nightly rate ${CAL_CURRENCY}${Math.round(priceVal).toLocaleString()}.`;

                days += `<div class="${cls}" title="${tooltip}" aria-label="${ds}. ${tooltip}">${d}${priceHtml}</div>`;
            }

            const weekdays = DAYS.map(d => `<span>${d}</span>`).join('');
            return `
                <div class="ac-month">
                    <div class="ac-header">
                        <button class="ac-nav ac-prev" ${!showPrev || !canPrev ? 'disabled' : ''}>&#8249;</button>
                        <h3>${MONTHS[m]} ${y}</h3>
                        <button class="ac-nav ac-next" ${showPrev ? 'style="visibility:hidden"' : ''}>&#8250;</button>
                    </div>
                    <div class="ac-weekdays">${weekdays}</div>
                    <div class="ac-days">${days}</div>
                </div>`;
        }

        // Wait for pmsAvailData from the datepicker fetch — reuse it if already loaded
        function init() {
            if (typeof pmsAvailData !== 'undefined') {
                blockedSet = new Set(pmsAvailData.blocked);
                noCheckinSet = new Set(pmsAvailData.no_checkin || []);
                noCheckoutSet = new Set(pmsAvailData.no_checkout || []);
                ratesMap   = Object.assign({}, pmsAvailData.rates || {});
                if (pmsAvailData.rates_from) ratesMap._from = true;
                render();
            } else {
                fetchAndRender();
            }
        }

        document.addEventListener('pms:availability-updated', function(event) {
            const data = event.detail || {};
            blockedSet = new Set(data.blocked || []);
            noCheckinSet = new Set(data.no_checkin || []);
            noCheckoutSet = new Set(data.no_checkout || []);
            ratesMap = Object.assign({}, data.rates || {});
            if (data.rates_from) ratesMap._from = true;
            render();
        });

        init();
    })();

    function updateGuestDisplay() {
        syncPartyControls();
        const adults = parseInt($("#adults-data").val()) || 1;
        const childs = parseInt($("#child-data").val()) || 0;
        const pets = parseInt($("#pet-data").val()) || 0;
        const total = adults + childs;
        let guestStr = total + (total > 1 ? ' Guests' : ' Guest');
        if (pets > 0) {
            guestStr += ', ' + pets + (pets > 1 ? ' Pets' : ' Pet');
        }
        $('#total-guests').val(guestStr);
    }

    $(function() {
        updateGuestDisplay();
    });
</script>
@stop
