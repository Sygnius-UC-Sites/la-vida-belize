<?php

namespace App\Modules\MultiPms\Pms\Adapters;

use App\Modules\MultiPms\Pms\PmsAdapter;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class LodgifyAdapter implements PmsAdapter
{
    protected Client $client;
    protected ?string $apiKey = null;

    public function __construct(?Client $client = null)
    {
        try {
            $this->apiKey = class_exists(\ModelHelper::class) 
                ? (\ModelHelper::getDataFromSetting('lodgify_api_key') ?: config('services.lodgify.api_key', '')) 
                : config('services.lodgify.api_key', '');
        } catch (\Throwable $e) {
            $this->apiKey = config('services.lodgify.api_key', '');
        }

        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.lodgify.com/',
            'headers' => [
                'X-ApiKey' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function getName(): string
    {
        return 'lodgify';
    }

    public function parseWebhook(array $payload): array
    {
        // Existing webhook parsing logic
        $results = $payload;

        if (isset($results[0]) && isset($results[0]['booking'])) {
            $data = $results[0]['booking'];
        } else {
            throw new \InvalidArgumentException('Booking key missing in payload');
        }

        $result = [];
        $result['arrival'] = $data['date_arrival'] ?? null;
        $result['departure'] = $data['date_departure'] ?? null;
        $result['property_id'] = $data['property_id'] ?? null;
        $result['pms_booking_id'] = $data['id'] ?? null;
        $result['type'] = $data['type'] ?? null;
        $result['booking_type'] = '';
        $result['status'] = $data['status'] ?? null;
        $result['source'] = $data['source'] ?? null;
        $result['source_text'] = $data['source_text'] ?? null;
        $result['property_name'] = $data['property_name'] ?? null;

        if (isset($results[0]['guest'])) {
            $result['guest'] = json_encode($results[0]['guest']);
        }

        if (isset($data['room_types']) && count($data['room_types']) > 0) {
            $room_ids = [];
            foreach ($data['room_types'] as $c) {
                $room_ids[] = (string) $c['room_type_id'];
            }
            $result['rooms'] = json_encode($data['room_types']);
            $result['room_ids'] = json_encode($room_ids);
            $result['people'] = $data['room_types'][0]['people'] ?? 0;
        } else {
            $result['people'] = 0;
        }

        $result['latest_webhook_event_id'] = md5(json_encode($payload));
        $result['is_replied'] = '';
        $result['is_deleted'] = '';
        $result['date_deleted'] = '';
        
        $bookingTotal = isset($results[0]['booking_total_amount']) ? (float)$results[0]['booking_total_amount'] : 0;
        $balanceDue = isset($results[0]['balance_due']) ? (float)$results[0]['balance_due'] : 0;

        $result['total_amount'] = $bookingTotal;
        $result['total_paid'] = $bookingTotal - $balanceDue;
        $result['amount_to_pay'] = $balanceDue;
        $result['currency'] = $results[0]['booking_currency_code'] ?? null;

        return $result;
    }

    protected function request(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $uri, $options);
            return [
                'status' => 200, // Normalized success
                'data' => json_decode($response->getBody()->getContents(), true)
            ];
        } catch (RequestException $e) {
            $errorResponse = $e->getResponse();
            $statusCode = $errorResponse ? $errorResponse->getStatusCode() : 500;
            $body = $errorResponse ? json_decode($errorResponse->getBody()->getContents(), true) : $e->getMessage();
            Log::channel('lodgify')->error("Lodgify API Error: {$method} {$uri}", ['error' => $body]);
            return [
                'status' => $statusCode,
                'error' => $body
            ];
        }
    }

    /** Fetch a single reservation by PMS booking ID. */
    public function getReservation(string $reservationId): array
    {
        // Lodgify booking IDs from v1/reservation/booking are integers.
        // Retrieve via v1/reservation list filtered to that property, then find by id.
        // Simpler: use the reservation list endpoint with the booking id as filter.
        return $this->request('GET', "v1/reservation/booking/{$reservationId}");
    }

public function healthCheck(): array
    {
        $res = $this->request('GET', 'v1/properties');
        return [
            'ok' => $res['status'] === 200,
            'status' => $res['status']
        ];
    }

    public function listProperties(): array
    {
        return $this->request('GET', 'v1/properties');
    }

    public function getProperty(string $propertyId): array
    {
        return $this->request('GET', "v1/properties/{$propertyId}");
    }

    public function listUnits(?string $propertyId = null): array
    {
        // Lodgify returns rooms inside property details, but also lists them via property endpoint
        if (!$propertyId) return ['status' => 400, 'error' => 'Property ID required'];
        return $this->request('GET', "v1/properties/{$propertyId}/rooms");
    }

    public function getAvailability(array $input): array
    {
        $propertyId = $input['property_id'] ?? '';
        $checkin    = $input['checkin']     ?? '';
        $checkout   = $input['checkout']    ?? '';

        // v1/availability returns period-based availability (not per-day rates).
        // Each period: {period_start, period_end, is_available, available, ...}
        // AvailabilityController::fromLodgify expands these into individual dates.
        // Params: periodStart and periodEnd (YYYY-MM-DD, no time component needed).
        return $this->request('GET', "v1/availability/{$propertyId}", [
            'query' => [
                'periodStart' => $checkin,
                'periodEnd'   => $checkout,
            ],
        ]);
    }

    /**
     * Returns the full raw Lodgify v2/quote response for use by QuoteNormalizer.
     * Lodgify returns an array; index 0 contains amount_gross, room_types, add_ons, total_vat.
     */
    public function getRawQuote(array $input): array
    {
        $propertyId = $input['property_id'] ?? '';
        $roomTypeId = $input['room_id'] ?? $input['room_type_id'] ?? '';
        $people     = max(1, (int)($input['adults'] ?? 1) + (int)($input['children'] ?? 0));

        $query = [
            'arrival'                                => ($input['checkin'] ?? '') . ' 12:00:00',
            'departure'                              => ($input['checkout'] ?? '') . ' 12:00:00',
            'roomTypes[0].Id'                        => $roomTypeId,
            'roomTypes[0].People'                    => $people,
            'roomTypes[0].guest_breakdown.adults'    => (int)($input['adults'] ?? 1),
            'roomTypes[0].guest_breakdown.children'  => (int)($input['children'] ?? 0),
            'roomTypes[0].guest_breakdown.pets'      => (int)($input['pets'] ?? 0),
        ];

        return $this->request('GET', "v2/quote/{$propertyId}", ['query' => $query]);
    }

    public function getQuote(array $input): array
    {
        // Support for V2 quote based on LodgifyApi.php mapping
        $propertyId = $input['property_id'] ?? '';
        $roomTypeId = $input['room_type_id'] ?? '';
        $checkin = ($input['checkin'] ?? '') . ' 12:00:00';
        $checkout = ($input['checkout'] ?? '') . ' 12:00:00';
        $people = $input['people'] ?? 1;
        $adults = $input['adults'] ?? $people;
        $children = $input['children'] ?? 0;
        $pets = $input['pets'] ?? 0;
        
        $query = [
            'arrival' => $checkin,
            'departure' => $checkout,
            'roomTypes[0].Id' => $roomTypeId,
            'roomTypes[0].People' => $people,
            'roomTypes[0].guest_breakdown.adults' => $adults,
            'roomTypes[0].guest_breakdown.children' => $children,
            'roomTypes[0].guest_breakdown.pets' => $pets,
        ];
        
        if (isset($input['coupon'])) {
            $query['promotionCode'] = $input['coupon'];
        }

        if (isset($input['add_ons']) && is_array($input['add_ons'])) {
            foreach ($input['add_ons'] as $index => $addon) {
                $query["addOns[{$index}].Id"] = $addon['id'];
                $query["addOns[{$index}].Units"] = $addon['units'];
            }
        }

        return $this->request('GET', "v2/quote/{$propertyId}", ['query' => $query]);
    }

    public function createReservation(array $input): array
    {
        // Mapping as found in saveBookingLodgifyNew
        $payload = [
            "source_text" => $input['source'] ?? "Website",
            "arrival" => ($input['checkin'] ?? '') . "T12:00:00Z",
            "departure" => ($input['checkout'] ?? '') . "T12:00:00Z",
            "property_id" => (int) ($input['property_id'] ?? 0),
            "status" => $input['status'] ?? "Open",
            "bookability" => "InstantBooking",
            "ip_created" => $input['ip'] ?? "127.0.0.1",
            "total" => $input['total'] ?? 0,
            "currency_code" => $input['currency'] ?? "USD",
            "origin" => "Website",
            "rooms" => [
                [
                    "room_type_id" => (int) ($input['room_type_id'] ?? $input['room_id'] ?? 0),
                    "guest_breakdown" => [
                        "adults"   => (int) ($input['adults'] ?? 1),
                        "children" => (int) ($input['children'] ?? 0),
                        "infants"  => (int) ($input['infants'] ?? 0),
                        "pets"     => (int) ($input['pets'] ?? 0),
                    ]
                ]
            ],
            "guest" => [
                "guest_name" => [
                    "first_name" => $input['first_name'] ?? '',
                    "last_name"  => $input['last_name'] ?? '',
                ],
                "email" => $input['email'] ?? '',
                "phone" => $input['phone'] ?? '',
                "locale" => "en"
            ],
            "messages" => [
                [
                    "subject" => "New booking",
                    "message" => "Booking created from website",
                    "type" => "Owner",
                    "send_notification" => true
                ]
            ]
        ];

        return $this->request('POST', 'v1/reservation/booking', ['json' => $payload]);
    }

    public function updateReservation(array $input): array
    {
        return ['status' => 501, 'error' => 'Not implemented'];
    }

    public function cancelReservation(array $input): array
    {
        $id = $input['reservation_id'] ?? '';
        if (!$id) return ['status' => 400, 'error' => 'Reservation ID required'];
        
        $action = $input['action'] ?? 'decline'; // decline, delete, expire
        
        if ($action === 'delete') {
            return $this->request('DELETE', "v1/reservation/booking/{$id}");
        } elseif ($action === 'expire') {
            return $this->request('POST', "v1/reservation/booking/{$id}/expire");
        }
        
        return $this->request('PUT', "v1/reservation/booking/{$id}/decline");
    }

    public function listReservations(array $input = []): array
    {
        $offset = $input['offset'] ?? 0;
        $limit = $input['limit'] ?? 50;
        return $this->request('GET', 'v1/reservation', [
            'query' => [
                'offset' => $offset,
                'limit' => $limit
            ]
        ]);
    }
    
    // Additional helpers from old LodgifyApi
    public function getPaymentLink(string $bookingId): array
    {
        return $this->request('GET', "v1/reservation/booking/{$bookingId}/quote/paymentLink");
    }

    /**
     * Record a collected payment against a Lodgify booking.
     * Endpoint: POST /v2/reservations/{bookingId}/transactions
     * Keeps Lodgify's ledger in sync with what was charged via Stripe.
     */
    public function recordPayment(string $bookingId, float $amount, string $currency): array
    {
        $res = $this->request('POST', "v2/reservations/{$bookingId}/transactions", [
            'json' => [
                'amount'            => $amount,
                'currency_code'     => strtoupper($currency),
                'type'              => 'payment',
                'payment_method_id' => 2, // 2 = Credit card in Lodgify
                'notes'             => 'Scheduled installment charged via website (Stripe)',
            ],
        ]);

        return [
            'ok'    => ($res['status'] ?? 0) === 200 || ($res['status'] ?? 0) === 201,
            'status' => $res['status'] ?? 0,
            'data'  => $res['data'] ?? [],
            'error' => $res['error'] ?? null,
        ];
    }

    public function getPropertyAddons(string $propertyId, array $query = []): array
    {
        return $this->request('GET', "v1/properties/{$propertyId}/addons", ['query' => $query]);
    }

    /**
     * Register a webhook with Lodgify.
     * Lodgify webhooks are typically configured in the dashboard per event type.
     * Their API does support registration via POST /v1/webhook (singular).
     * Event types: booking_new_any_status, booking_change, availability_change,
     *              rate_change, new_property, updated_property
     *
     * NOTE: Lodgify uses token-in-URL auth. The token must be appended to the URL
     * before calling this method.
     */
    public function registerWebhook(string $url, array $events): array
    {
        if (empty($events)) {
            $events = ['booking_new_any_status', 'booking_change', 'availability_change'];
        }

        $results = [];
        $allOk   = true;

        // Lodgify requires one registration per event type
        foreach ($events as $event) {
            $res = $this->request('POST', 'v1/webhook', [
                'json' => [
                    'url'        => $url . '/' . $event, // Lodgify uses event-specific URLs
                    'event_type' => $event,
                ],
            ]);
            $ok = in_array($res['status'] ?? 0, [200, 201]);
            if (!$ok) $allOk = false;
            $results[$event] = [
                'ok'    => $ok,
                'error' => $ok ? null : ($res['error'] ?? 'failed'),
            ];
        }

        return [
            'ok'      => $allOk,
            'results' => $results,
            'error'   => $allOk ? null : 'One or more Lodgify webhook registrations failed. Check dashboard to configure manually.',
        ];
    }

    // Raw Storage & Frontend Mapping
    /**
     * GET /v2/reviews?propertyId={id}  (falls back to v1/properties/{id}/reviews)
     * Lodgify review availability depends on account plan.
     * Returns: [{id, reviewer_name, comment, rating, date}] or {items:[...]}
     * Rating: 1–10 scale → normalize to 1-5.
     */
    public function getReviews(string $propertyId): array
    {
        // Lodgify does not expose a public reviews API on standard plans.
        // Both v2/reviews and v1/properties/{id}/reviews return 404.
        // Return empty silently — no log spam on every sync.
        return [];

        $data    = $res['data'] ?? [];
        $reviews = $data['items'] ?? $data['reviews'] ?? (is_array($data) && isset($data[0]) ? $data : []);
        if (!is_array($reviews)) return [];

        $out = [];
        foreach ($reviews as $r) {
            $rawScore = (float)($r['rating'] ?? $r['score'] ?? 5);
            // Lodgify uses 1-10; normalize to 1-5
            $score = (int)round($rawScore > 5 ? $rawScore / 2 : $rawScore);
            $score = max(1, min(5, $score));

            $stayDate = isset($r['date']) ? substr($r['date'], 0, 10) : ($r['check_in'] ?? null);

            $out[] = [
                'id'        => (string)($r['id'] ?? uniqid()),
                'name'      => $r['reviewer_name'] ?? $r['name'] ?? $r['author'] ?? 'Guest',
                'score'     => $score,
                'message'   => $r['comment'] ?? $r['review'] ?? $r['text'] ?? '',
                'stay_date' => $stayDate,
                'end_date'  => $r['check_out'] ?? null,
                'profile'   => $r['reviewer_location'] ?? $r['location'] ?? null,
            ];
        }
        return $out;
    }

    public function fetchAndSaveRawData(string $propertyId): void
    {
        // v2 property for location, pricing, name
        $v2Res       = $this->request('GET', "v2/properties/{$propertyId}");
        $propertyRaw = ($v2Res['status'] ?? 0) === 200 ? ($v2Res['data'] ?? null) : null;

        // Fall back to v1 property if v2 unavailable
        if (!$propertyRaw) {
            $v1Res       = $this->request('GET', "v1/properties/{$propertyId}");
            $propertyRaw = ($v1Res['status'] ?? 0) === 200 ? ($v1Res['data'] ?? null) : null;
        }

        // Lodgify room stubs (id only) come from the v1 property listing.
        // Full room detail — images, amenities, description — requires a separate call:
        // GET /v1/properties/{propertyId}/rooms/{roomId}
        $v1PropRes  = $this->request('GET', "v1/properties/{$propertyId}");
        $roomStubs  = ($v1PropRes['status'] ?? 0) === 200
                        ? ($v1PropRes['data']['rooms'] ?? [])
                        : [];

        $unitsRaw = [];
        foreach ($roomStubs as $stub) {
            $roomId = $stub['id'] ?? null;
            if (!$roomId) continue;
            $roomRes = $this->request('GET', "v1/properties/{$propertyId}/rooms/{$roomId}");
            if (($roomRes['status'] ?? 0) === 200 && is_array($roomRes['data'])) {
                $unitsRaw[] = $roomRes['data'];
            }
        }

        \App\Modules\MultiPms\Models\LodgifyData::updateOrCreate(
            ['pms_property_id' => $propertyId],
            [
                'raw_property_json' => $propertyRaw        ? json_encode($propertyRaw) : null,
                'raw_units_json'    => !empty($unitsRaw)   ? json_encode($unitsRaw)   : null,
            ]
        );
    }

    public function getFrontendData(string $propertyId): array
    {
        $data = \App\Modules\MultiPms\Models\LodgifyData::where('pms_property_id', $propertyId)->first();
        if (!$data || !$data->raw_property_json) {
            return [
                'title' => 'Property Not Found',
                'description' => '',
                'images' => [],
                'bedrooms' => 1,
                'bathrooms' => 1,
                'guests' => 1,
                'amenities' => [],
                'address' => [],
                'pricing' => [],
                'availability' => [],
                'fees' => [],
                'house_rules' => '',
                'cancellation_policy' => '',
                'booking_rules' => []
            ];
        }
        
        $listing = json_decode($data->raw_property_json, true);
        $units = $data->raw_units_json ? json_decode($data->raw_units_json, true) : [];
        // Lodgify: when rooms endpoint returned nothing, fall back to listing['rooms'] array
        $firstRoom = $units[0] ?? $listing['rooms'][0] ?? [];

        // ── Images: v2 returns photos[], v1 returns image_url + images[] ──────
        $images = [];
        $collect = function ($node) use (&$images) {
            if (!is_array($node)) return;
            // v1 single image
            if (!empty($node['image_url'])) $images[] = $node['image_url'];
            // v2 photos array: [{url, ...}]
            if (!empty($node['photos']) && is_array($node['photos'])) {
                foreach ($node['photos'] as $ph) {
                    $url = is_array($ph) ? ($ph['url'] ?? $ph['image_url'] ?? $ph['original'] ?? null) : $ph;
                    if ($url) $images[] = $url;
                }
            }
            // v1 images array
            if (!empty($node['images']) && is_array($node['images'])) {
                foreach ($node['images'] as $im) {
                    $url = is_array($im) ? ($im['url'] ?? $im['image_url'] ?? $im['original'] ?? null) : $im;
                    if ($url) $images[] = $url;
                }
            }
        };
        $collect($listing);
        foreach ($units as $u) { $collect($u); }
        $images = array_values(array_unique(array_filter($images)));

        // ── Amenities: Lodgify can return amenities in multiple shapes ────────
        // 1. Grouped:  [{name:"Group", items:[{name:"WiFi",...},...]}]
        // 2. Flat:     [{name:"WiFi", id:1, ...}]
        // 3. Strings:  ["WiFi", "Pool", ...]
        // Also check alternative keys the API sometimes uses.
        $amenities = [];
        $amenSources = array_filter([
            $listing['amenities']          ?? null, // v2
            $listing['property_amenities'] ?? null,
            $listing['amenity_groups']     ?? null, // v2 grouped
            $listing['facilities']         ?? null, // v2 alternate key
            $firstRoom['amenities']        ?? null,
            $firstRoom['amenity_groups']   ?? null,
        ], fn($v) => is_array($v) && !empty($v));

        foreach ($amenSources as $source) {
            foreach ($source as $am) {
                if (is_string($am) && $am !== '') {
                    $amenities[] = $am;
                } elseif (is_array($am)) {
                    if (!empty($am['items']) && is_array($am['items'])) {
                        // Generic grouped format: {items: [{name, text}, ...]}
                        foreach ($am['items'] as $it) {
                            $label = is_array($it) ? ($it['name'] ?? $it['text'] ?? '') : (string)$it;
                            if ($label !== '') $amenities[] = $label;
                        }
                    } elseif (isset($am[0]) && is_array($am[0])) {
                        // Lodgify grouped format: category => [{name, text, prefix}, ...]
                        // Each $am here is one category's array of amenity objects
                        foreach ($am as $it) {
                            $label = $it['text'] ?? $it['name'] ?? '';
                            if ($label !== '') $amenities[] = $label;
                        }
                    } else {
                        // Flat format — the array IS the amenity object
                        $label = $am['name'] ?? $am['text'] ?? $am['title'] ?? '';
                        if ($label !== '') $amenities[] = $label;
                    }
                }
            }
        }
        $amenities = array_values(array_unique(array_filter($amenities)));

        // Property type — Lodgify returns property_type or type field
        $rawType      = $listing['property_type'] ?? $listing['type'] ?? null;
        $propertyType = $rawType ? strtolower(str_replace(['_','-'], ' ', (string)$rawType)) : null;

        // Bed configuration — Lodgify rooms can have a bedrooms count; full bed detail rarely available
        $bedConfig = [];
        $brCount = (int)($firstRoom['bedrooms'] ?? 0);
        for ($i = 1; $i <= $brCount; $i++) {
            $bedConfig[] = ['name' => "Bedroom {$i}", 'beds' => []];
        }

        return [
            'title'         => $listing['name'] ?? 'Main Unit',
            'description'   => $firstRoom['description'] ?? $listing['description'] ?? '',
            'images'        => $images,
            'bedrooms'      => $firstRoom['bedrooms'] ?? 1,
            'bathrooms'     => $firstRoom['bathrooms'] ?? 1,
            'guests'        => $firstRoom['max_people'] ?? 2,
            'room_type_id'  => $firstRoom['id'] ?? null,
            'amenities'     => $amenities,
            'property_type' => $propertyType,
            'bed_config'    => $bedConfig ?: null,
            'address' => [
                'address' => $listing['address']  ?? '',
                'city'    => $listing['city']     ?? '',
                'state'   => $listing['state']    ?? '',
                'zip'     => $listing['zip']      ?? $listing['zipcode'] ?? '',
                'country' => $listing['country']  ?? $listing['country_code'] ?? '',
                'lat'     => $listing['latitude'] ?? null,
                'lng'     => $listing['longitude'] ?? null,
            ],
            'pricing' => [
                'currency' => is_array($listing['currency'] ?? null) ? ($listing['currency']['code'] ?? 'USD') : ($listing['currency'] ?? 'USD'),
                'basePrice' => $listing['min_price'] ?? 0,
            ],
            'availability' => [],
            'fees' => [],
            'house_rules' => '',
            'cancellation_policy' => '',
            'booking_rules' => [],
            'key_facts' => \App\Modules\MultiPms\Helper\ListingFacts::build([
                'check_in'  => $listing['checkin'] ?? $listing['check_in'] ?? null,
                'check_out' => $listing['checkout'] ?? $listing['check_out'] ?? null,
                'min_stay'  => $firstRoom['min_stay'] ?? $listing['min_stay'] ?? null,
                'max_stay'  => $firstRoom['max_stay'] ?? $listing['max_stay'] ?? null,
            ], $amenities, ''),
            'pricing_normalized' => \App\Modules\MultiPms\Helper\ListingPricing::normalize(
                [
                    'currency'  => is_array($listing['currency'] ?? null)
                                    ? ($listing['currency']['code'] ?? 'USD')
                                    : ($listing['currency'] ?? 'USD'),
                    'basePrice' => $listing['min_price'] ?? 0,
                ],
                [],
                'lodgify'
            ),
        ];
    }

    public function createQuoteFromBooking(string $bookingId, array $payload): array
    {
        return $this->request('POST', "v1/reservation/booking/{$bookingId}/quote", ['json' => $payload]);
    }
}
