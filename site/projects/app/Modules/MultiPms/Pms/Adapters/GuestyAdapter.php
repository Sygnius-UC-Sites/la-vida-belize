<?php

namespace App\Modules\MultiPms\Pms\Adapters;

use App\Modules\MultiPms\Pms\PmsAdapter;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GuestyAdapter implements PmsAdapter
{
    protected Client $client;
    protected ?string $apiKey = null; // Used as client_secret in Open API v1
    protected ?string $clientId = null; // Used as client_id in Open API v1

    public function __construct(?Client $client = null)
    {
        try {
            $helper = class_exists(\App\Helper\ModelHelper::class) ? new \App\Helper\ModelHelper() : null;
            $this->apiKey   = ($helper ? $helper->getDataFromSetting('guesty_api_key')   : null) ?: config('services.guesty.api_key', '');
            $this->clientId = ($helper ? $helper->getDataFromSetting('guesty_client_id') : null) ?: config('services.guesty.client_id', '');
        } catch (\Throwable $e) {
            $this->apiKey = config('services.guesty.api_key', '');
            $this->clientId = config('services.guesty.client_id', '');
        }

        $this->client = $client ?? new Client([
            'base_uri'        => 'https://open-api.guesty.com/',
            'timeout'         => 15,
            'connect_timeout' => 8,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function getName(): string
    {
        return 'guesty';
    }

    /**
     * Retrieve OAuth2 token for Guesty Open API.
     *
     * Uses a mutex lock to prevent thundering herd: when the cache is empty
     * and many requests arrive simultaneously, only ONE hits the token endpoint.
     * The others wait up to 10s then read the cached result. Retries up to 3×
     * with exponential backoff on 429. Caches for the actual expires_in duration
     * (minus a 5-minute buffer) rather than a hardcoded value.
     */
    protected function getAccessToken(): ?string
    {
        if (empty($this->apiKey) || empty($this->clientId)) {
            return null;
        }

        $cacheKey = 'guesty_access_token_' . md5($this->apiKey);

        // Fast path — token already cached.
        $cached = Cache::get($cacheKey);
        if ($cached) return $cached;

        // Slow path — acquire lock so only ONE process fetches the token.
        $lock = Cache::lock('guesty_token_fetch_' . md5($this->apiKey), 30);
        try {
            $lock->block(10);

            // Re-check after acquiring lock — another process may have fetched already.
            $cached = Cache::get($cacheKey);
            if ($cached) return $cached;

            // Retry up to 3× with exponential backoff (2s, 4s, 8s) on 429.
            $token     = null;
            $expiresIn = 86400;
            $attempts  = 3;
            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                try {
                    $response  = $this->client->post('oauth2/token', [
                        'form_params' => [
                            'grant_type'    => 'client_credentials',
                            'client_id'     => $this->clientId,
                            'client_secret' => $this->apiKey,
                            'scope'         => 'open-api',
                        ]
                    ]);
                    $data      = json_decode($response->getBody()->getContents(), true);
                    $token     = $data['access_token'] ?? null;
                    $expiresIn = (int) ($data['expires_in'] ?? 86400);
                    if ($token) break;
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    $status = $e->getResponse()?->getStatusCode();
                    if ($status === 429 && $attempt < $attempts) {
                        $wait = 2 ** $attempt;
                        Log::channel('lodgify')->warning("Guesty token 429 — retry {$attempt}/{$attempts} in {$wait}s");
                        sleep($wait);
                        continue;
                    }
                    throw $e;
                }
            }

            if ($token) {
                $ttl = max($expiresIn - 300, 300);
                Cache::put($cacheKey, $token, $ttl);
                Log::channel('lodgify')->info("Guesty token cached for {$ttl}s (expires_in={$expiresIn}s)");
            }
            return $token;

        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::channel('lodgify')->warning('Guesty token lock timeout — reading cache fallback');
            return Cache::get($cacheKey);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('Guesty Access Token Error: ' . $e->getMessage());
            return null;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Request helper.
     */
    protected function request(string $method, string $uri, array $options = []): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'status' => 401,
                'error'  => 'Missing or invalid Guesty API credentials (Client ID / Client Secret)'
            ];
        }

        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        try {
            $response = $this->client->request($method, $uri, $options);
            return [
                'status' => 200,
                'data'   => json_decode($response->getBody()->getContents(), true)
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorResponse = $e->getResponse();
            $statusCode = $errorResponse ? $errorResponse->getStatusCode() : 500;
            $body = $errorResponse ? json_decode($errorResponse->getBody()->getContents(), true) : $e->getMessage();
            Log::channel('lodgify')->error("Guesty API Error: {$method} {$uri}", ['error' => $body]);
            return [
                'status' => $statusCode,
                'error'  => $body
            ];
        }
    }

    public function healthCheck(): array
    {
        $token = $this->getAccessToken();
        return [
            'ok' => !empty($token),
            'status' => empty($token) ? 401 : 200
        ];
    }

    /** True if a cached OAuth token exists (no API call made). */
    public function hasToken(): bool
    {
        $cacheKey = 'guesty_access_token_' . md5($this->apiKey ?? '');
        return !empty(\Cache::get($cacheKey));
    }

    /** Fetch a single reservation by PMS booking ID. */
    public function getReservation(string $reservationId): array
    {
        return $this->request('GET', "v1/reservations/{$reservationId}");
    }

    public function listProperties(): array
    {
        $limit = 100;
        $skip = 0;
        $allItems = [];
        $hasMore = true;

        while ($hasMore) {
            $res = $this->request('GET', 'v1/listings', [
                'query' => [
                    'limit' => $limit,
                    'skip'  => $skip
                ]
            ]);

            if (($res['status'] ?? 0) !== 200) {
                return $res;
            }

            $items = $res['data']['results'] ?? [];
            $allItems = array_merge($allItems, $items);

            if (count($items) < $limit) {
                $hasMore = false;
            } else {
                $skip += $limit;
            }
        }

        return [
            'status' => 200,
            'data' => ['results' => $allItems]
        ];
    }

    public function getProperty(string $propertyId): array
    {
        $res = $this->request('GET', "v1/listings/{$propertyId}");
        if (($res['status'] ?? 0) === 200 && isset($res['data'])) {
            $guestyListing = $res['data'];
            
            $standardized = [
                'name'               => $guestyListing['title'] ?? ($guestyListing['nickname'] ?? ''),
                'latitude'           => $guestyListing['address']['lat'] ?? null,
                'longitude'          => $guestyListing['address']['lng'] ?? null,
                'address'            => $guestyListing['address']['street'] ?? '',
                'zip'                => $guestyListing['address']['zipcode'] ?? '',
                'city'               => $guestyListing['address']['city'] ?? '',
                'country'            => $guestyListing['address']['country'] ?? '',
                'image_url'          => $guestyListing['picture']['thumbnail'] ?? '',
                'price_unit_in_days' => 'true',
                'min_price'          => $guestyListing['prices']['basePrice'] ?? 0,
                'max_price'          => $guestyListing['prices']['basePrice'] ?? 0,
                'currency'           => $guestyListing['prices']['currency'] ?? 'USD',
                'rooms'              => []
            ];

            return [
                'status' => 200,
                'data'   => $standardized
            ];
        }
        return $res;
    }

    public function listUnits(?string $propertyId = null): array
    {
        if (!$propertyId) return ['status' => 400, 'error' => 'Property ID is required'];
        
        $res = $this->request('GET', "v1/listings/{$propertyId}");
        if (($res['status'] ?? 0) === 200 && isset($res['data'])) {
            $listing = $res['data'];
            
            $standardizedRoom = [
                [
                    'id'               => (string)$propertyId,
                    'name'             => $listing['title'] ?? 'Main Unit',
                    'description'      => trim(($listing['publicDescription']['summary'] ?? '') . "\n\n" . ($listing['publicDescription']['space'] ?? '') . "\n\n" . ($listing['publicDescription']['access'] ?? '') . "\n\n" . ($listing['publicDescription']['neighborhood'] ?? '')),
                    'max_people'       => $listing['accommodates'] ?? 2,
                    'bedrooms'         => $listing['bedrooms'] ?? 1,
                    'bathrooms'        => $listing['bathrooms'] ?? 1,
                    'pets_allowed'     => ($listing['amenities']['petsAllowed'] ?? false) ? 1 : 0,
                    'has_parking'      => ($listing['amenities']['parking'] ?? false) ? 1 : 0,
                    'has_wifi'         => ($listing['amenities']['wifi'] ?? false) ? 1 : 0,
                    'currency'         => $listing['prices']['currency'] ?? 'USD',
                    'images'           => !empty($listing['pictures']) ? array_column($listing['pictures'], 'original') : [],
                    'amenities'        => isset($listing['amenities']) && is_array($listing['amenities']) ? $listing['amenities'] : [],
                    'house_rules'      => $listing['houseRules'] ?? '',
                    'cancellation_policy' => $listing['cancellationPolicy'] ?? ''
                ]
            ];
            
            return [
                'status' => 200,
                'data'   => $standardizedRoom
            ];
        }
        
        return $res;
    }

    public function getAvailability(array $input): array
    {
        $propertyId = $input['property_id'] ?? '';
        $checkin = $input['checkin'] ?? '';
        $checkout = $input['checkout'] ?? '';
        
        return $this->request('GET', "v1/listings/{$propertyId}/calendar", [
            'query' => [
                'from' => $checkin,
                'to'   => $checkout
            ]
        ]);
    }

    /**
     * Returns the full raw Guesty quote response for use by QuoteNormalizer.
     */
    public function getRawQuote(array $input): array
    {
        $payload = [
            'listingId'             => $input['property_id'] ?? '',
            'checkInDateLocalized'  => $input['checkin'] ?? '',
            'checkOutDateLocalized' => $input['checkout'] ?? '',
            'guestsCount'           => (int)($input['adults'] ?? 1) + (int)($input['children'] ?? 0),
            'source'                => 'website',
        ];
        $res = $this->request('POST', 'v1/quotes', ['json' => $payload]);
        \Log::channel('lodgify')->info('Guesty getRawQuote response', [
            'payload'      => $payload,
            'status'       => $res['status'] ?? null,
            'data_keys'    => isset($res['data']) ? array_keys($res['data']) : null,
            'error'        => $res['error'] ?? null,
            'money_keys'   => isset($res['data']['money']) ? array_keys($res['data']['money']) : null,
            'fare_keys'    => isset($res['data']['fare'])  ? array_keys($res['data']['fare'])  : null,
        ]);
        return $res;
    }

    public function getQuote(array $input): array
    {
        $propertyId = $input['property_id'] ?? '';

        $payload = [
            'listingId'            => $propertyId,
            'checkInDateLocalized' => $input['checkin'] ?? '',
            'checkOutDateLocalized'=> $input['checkout'] ?? '',
            'guestsCount'          => (int)($input['people'] ?? 1),
            'source'               => 'website'
        ];

        if (isset($input['coupon'])) {
            $payload['couponCode'] = $input['coupon'];
        }

        $res = $this->request('POST', 'v1/quotes', ['json' => $payload]);

        if (($res['status'] ?? 0) === 200 && isset($res['data'])) {
            $calc = $res['data'];
            
            $standardizedQuote = [
                'subtotal'    => $calc['fare']['accommodation'] ?? 0,
                'total'       => $calc['fare']['total'] ?? 0,
                'net_amount'  => $calc['fare']['total'] ?? 0,
                'vat_amount'  => $calc['fare']['taxesTotal'] ?? 0,
                'taxes'       => [],
                'fees'        => []
            ];

            return [
                'status' => 200,
                'data'   => $standardizedQuote
            ];
        }

        return $res;
    }

    public function createReservation(array $input): array
    {
        $adults   = (int)($input['adults']   ?? 1);
        $children = (int)($input['children'] ?? 0);
        $pets     = (int)($input['pets']     ?? 0);

        // Guesty Open API v3 reservation schema
        $payload = [
            'listingId'             => $input['property_id']  ?? '',
            'checkInDateLocalized'  => $input['checkin']      ?? '',
            'checkOutDateLocalized' => $input['checkout']     ?? '',
            'guestsCount'           => $adults + $children,
            'numberOfGuests'        => [
                'numberOfAdults'   => $adults,
                'numberOfChildren' => $children,
                'numberOfInfants'  => 0,
                'numberOfPets'     => $pets,
            ],
            'status'  => 'confirmed',
            'source'  => 'direct',   // "direct" = direct booking, not channel
            'guest' => [
                'firstName' => $input['first_name'] ?? '',
                'lastName'  => $input['last_name']  ?? '',
                'email'     => $input['email']  ?? '',
                'phone'     => $input['phone']  ?? '',
            ],
            'money' => [
                'fareAccommodation' => (float)($input['total_amount'] ?? 0),
            ],
        ];

        return $this->request('POST', 'v1/reservations-v3', ['json' => $payload]);
    }

    public function updateReservation(array $input): array
    {
        $reservationId = $input['reservation_id'] ?? '';
        if (!$reservationId) return ['status' => 400, 'error' => 'Reservation ID required'];

        $payload = array_intersect_key($input, array_flip([
            'status', 'guestsCount'
        ]));

        return $this->request('PUT', "v1/reservations/{$reservationId}", ['json' => $payload]);
    }

    public function cancelReservation(array $input): array
    {
        $reservationId = $input['reservation_id'] ?? '';
        if (!$reservationId) return ['status' => 400, 'error' => 'Reservation ID required'];

        $res = $this->request('PUT', "v1/reservations/{$reservationId}", [
            'json' => [
                'status' => 'canceled',
                'cancellationReason' => $input['reason'] ?? 'No Reason Provided'
            ]
        ]);

        // Guesty returns HTTP 400 even on successful cancellation — normalise:
        // if the response body shows status=canceled, treat as success.
        if (($res['status'] ?? 0) === 400 && ($res['data']['status'] ?? '') === 'canceled') {
            $res['status'] = 200;
        }

        return $res;
    }

    public function listReservations(array $input = []): array
    {
        $limit = $input['limit'] ?? 50;
        $skip = $input['offset'] ?? 0;
        $allItems = [];
        $hasMore = true;

        while ($hasMore) {
            $res = $this->request('GET', 'v1/reservations', [
                'query' => [
                    'limit'  => $limit,
                    'skip'   => $skip
                ]
            ]);

            if (($res['status'] ?? 0) !== 200) {
                return $res;
            }

            $items = $res['data']['results'] ?? [];
            $allItems = array_merge($allItems, $items);

            if (count($items) < $limit) {
                $hasMore = false;
            } else {
                $skip += $limit;
            }
        }

        return [
            'status' => 200,
            'data' => ['results' => $allItems]
        ];
    }

    public function getPaymentLink(string $bookingId): array
    {
        return ['status' => 200, 'data' => ['url' => '']];
    }

    /**
     * Record a collected payment against a Guesty reservation.
     * Endpoint: POST /v1/reservations/{reservationId}/payments
     * Guesty Open API — Guest Payments section.
     */
    public function recordPayment(string $bookingId, float $amount, string $currency): array
    {
        $res = $this->request('POST', "v1/reservations/{$bookingId}/payments", [
            'json' => [
                'amount'   => $amount,
                'currency' => strtoupper($currency),
                'method'   => 'CREDIT_CARD',
                'note'     => 'Scheduled installment charged via website (Stripe)',
                'shouldBeSentToOwner' => false,
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
        return ['status' => 200, 'data' => []];
    }

    /**
     * Register a webhook with Guesty.
     * POST /v1/webhooks
     * Events: reservation.created, reservation.updated, reservation.cancelled,
     *         listing.updated, calendar.blocked, calendar.unblocked
     * Docs: https://open-api-docs.guesty.com/docs/webhooks
     */
    public function registerWebhook(string $url, array $events): array
    {
        // Correct Guesty event names per Open API docs:
        // reservation.new, reservation.updated, listing.calendar.updated, calendar.updated.v2
        if (empty($events)) {
            $events = ['reservation.new', 'reservation.updated', 'listing.calendar.updated', 'calendar.updated.v2'];
        }

        $res = $this->request('POST', 'v1/webhooks', [
            'json' => [
                'url'    => $url,
                'events' => $events,
            ],
        ]);

        $ok  = in_array($res['status'] ?? 0, [200, 201]);
        $err = $res['error'] ?? ($res['data']['message'] ?? 'Guesty webhook registration failed');
        return [
            'ok'         => $ok,
            'webhook_id' => $res['data']['_id'] ?? $res['data']['id'] ?? null,
            'error'      => $ok ? null : (is_array($err) ? json_encode($err) : $err),
            'raw'        => $res,
        ];
    }

    /**
     * GET /v1/listings/{listingId}/reviews
     * Returns: {results: [{_id, reviewer:{firstName,lastName}, rating, publicReview, createdAt, checkIn, checkOut}]}
     * Rating: 1-5 integer.
     */
    public function getReviews(string $propertyId): array
    {
        $res = $this->request('GET', "v1/listings/{$propertyId}/reviews", [
            'query' => ['limit' => 50, 'skip' => 0],
        ]);

        if (($res['status'] ?? 0) !== 200) return [];

        $reviews = $res['data']['results'] ?? $res['data']['result'] ?? $res['data'] ?? [];
        if (!is_array($reviews)) return [];

        $out = [];
        foreach ($reviews as $r) {
            $reviewer = $r['reviewer'] ?? [];
            $first    = $reviewer['firstName'] ?? '';
            $last     = $reviewer['lastName']  ?? '';
            $name     = trim("{$first} {$last}") ?: 'Guest';

            $score = (int)($r['rating'] ?? 5);
            $score = max(1, min(5, $score));

            // Guesty dates: checkIn/checkOut are ISO strings (YYYY-MM-DDThh:mm:ss)
            $stayDate = isset($r['checkIn'])  ? substr($r['checkIn'],  0, 10) : null;
            $endDate  = isset($r['checkOut']) ? substr($r['checkOut'], 0, 10) : null;

            $out[] = [
                'id'        => (string)($r['_id'] ?? $r['id'] ?? uniqid()),
                'name'      => $name,
                'score'     => $score,
                'message'   => $r['publicReview'] ?? $r['comment'] ?? '',
                'stay_date' => $stayDate,
                'end_date'  => $endDate,
                'profile'   => $reviewer['location'] ?? null,
            ];
        }
        return $out;
    }

    // Raw Storage & Frontend Mapping
    public function fetchAndSaveRawData(string $propertyId): void
    {
        $propertyRes = $this->request('GET', "v1/listings/{$propertyId}");
        $propertyRaw = ($propertyRes['status'] ?? 0) === 200 ? ($propertyRes['data'] ?? null) : null;
        
        \App\Modules\MultiPms\Models\GuestyData::updateOrCreate(
            ['pms_property_id' => $propertyId],
            [
                'raw_property_json' => $propertyRaw ? json_encode($propertyRaw) : null,
                'raw_units_json' => null,
            ]
        );
    }

    public function getFrontendData(string $propertyId): array
    {
        $data = \App\Modules\MultiPms\Models\GuestyData::where('pms_property_id', $propertyId)->first();
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

        $amenities = isset($listing['amenities']) && is_array($listing['amenities']) ? $listing['amenities'] : [];

        // Guesty exposes house rules as an object with explicit boolean flags.
        $hr = (isset($listing['houseRules']) && is_array($listing['houseRules'])) ? $listing['houseRules'] : [];
        $houseRulesText = '';
        if (!empty($hr['additionalRules'])) {
            $houseRulesText = is_array($hr['additionalRules']) ? implode("\n", $hr['additionalRules']) : (string) $hr['additionalRules'];
        } elseif (is_string($listing['houseRules'] ?? null)) {
            $houseRulesText = $listing['houseRules'];
        }

        // Property type — Guesty returns propertyType string (e.g. "Apartment", "House")
        $rawType      = $listing['propertyType'] ?? $listing['listingType'] ?? null;
        $propertyType = $rawType ? strtolower(str_replace(['_','-'], ' ', (string)$rawType)) : null;

        // Bed configuration — Guesty returns bedrooms[] as an array of room objects,
        // but some listings return bedrooms as a plain integer (count only). Guard both.
        $bedConfig   = [];
        $bedroomsRaw = $listing['bedrooms'] ?? [];
        if (!is_array($bedroomsRaw)) $bedroomsRaw = []; // integer count — no detail available
        foreach ($bedroomsRaw as $br) {
            if (!is_array($br)) continue;
            $beds = [];
            foreach ($br['beds'] ?? [] as $bed) {
                $beds[] = [
                    'type'  => $bed['type'] ?? 'bed',
                    'count' => (int)($bed['quantity'] ?? 1),
                ];
            }
            $bedConfig[] = [
                'name' => $br['type'] ?? ('Bedroom ' . (count($bedConfig) + 1)),
                'beds' => $beds,
            ];
        }

        // Address — Guesty returns address as an object: {full, city, state, country, lat, lng}
        $addrObj = is_array($listing['address'] ?? null) ? $listing['address'] : [];

        return [
            'title'         => $listing['title'] ?? 'Main Unit',
            'description'   => trim(
                ($listing['publicDescription']['summary']      ?? '') . "\n\n" .
                ($listing['publicDescription']['space']        ?? '') . "\n\n" .
                ($listing['publicDescription']['access']       ?? '') . "\n\n" .
                ($listing['publicDescription']['neighborhood'] ?? '')
            ),
            'images'        => !empty($listing['pictures']) ? array_column($listing['pictures'], 'original') : [],
            'bedrooms'      => is_array($listing['bedrooms'] ?? null) ? count($listing['bedrooms']) : (int)($listing['bedrooms'] ?? 1),
            'bathrooms'     => $listing['bathrooms'] ?? 1,
            'guests'        => $listing['accommodates'] ?? 2,
            'amenities'     => $amenities,
            'property_type' => $propertyType,
            'bed_config'    => $bedConfig ?: null,
            'address' => [
                'address' => $addrObj['full']   ?? $addrObj['street'] ?? '',
                'city'    => $addrObj['city']   ?? '',
                'state'   => $addrObj['state']  ?? '',
                'zip'     => $addrObj['zip']    ?? $addrObj['zipcode'] ?? '',
                'country' => $addrObj['country'] ?? $addrObj['countryCode'] ?? '',
                'lat'     => $addrObj['lat']    ?? null,
                'lng'     => $addrObj['lng']    ?? null,
            ],
            'pricing' => $listing['prices'] ?? [],
            'availability' => [], // Needs separate availability call if necessary
            'fees' => [],
            'house_rules' => $houseRulesText,
            'cancellation_policy' => $listing['cancellationPolicy'] ?? '',
            'booking_rules' => $listing['terms'] ?? [],
            'key_facts' => \App\Modules\MultiPms\Helper\ListingFacts::build([
                'check_in'  => $listing['defaultCheckInTime'] ?? null,
                'check_out' => $listing['defaultCheckOutTime'] ?? null,
                'min_stay'  => $listing['terms']['minNights'] ?? $listing['minNights'] ?? null,
                'max_stay'  => $listing['terms']['maxNights'] ?? $listing['maxNights'] ?? null,
                'pets_allowed' => $hr['petsAllowed'] ?? null,
                'smoking_allowed' => $hr['smokingAllowed'] ?? null,
                'events_allowed' => $hr['partiesAllowed'] ?? $hr['eventsAllowed'] ?? null,
                'suitable_for_children' => $hr['suitableForChildren'] ?? null,
            ], $amenities, $houseRulesText),
            'pricing_normalized' => \App\Modules\MultiPms\Helper\ListingPricing::normalize(
                $listing['prices'] ?? [],
                [],
                'guesty'
            ),
        ];
    }

    public function parseWebhook(array $payload): array
    {
        // Guesty wraps event data under a 'data' key:
        // {"event": "reservation.new", "data": {"_id": "...", "listingId": "...", ...}}
        $data = $payload['data'] ?? $payload;

        $result = [];
        $result['arrival'] = $data['checkInDateLocalized'] ?? null;
        $result['departure'] = $data['checkOutDateLocalized'] ?? null;
        $result['property_id'] = $data['listingId'] ?? null;
        $result['pms_booking_id'] = $data['_id'] ?? null;
        $result['status'] = $data['status'] ?? null;
        $result['source'] = $data['source'] ?? 'Website';
        $result['source_text'] = $data['source'] ?? 'Website Booking';
        $result['property_name'] = $data['listing']['title'] ?? null;
        $result['people'] = $data['guestsCount'] ?? 1;

        $result['guest'] = json_encode([
            'name' => ($data['guest']['firstName'] ?? '') . ' ' . ($data['guest']['lastName'] ?? ''),
            'email' => $data['guest']['email'] ?? '',
            'phone' => $data['guest']['phone'] ?? ''
        ]);

        $result['latest_webhook_event_id'] = md5(json_encode($payload));
        $result['total_amount'] = (float)($data['pricing']['total'] ?? 0);
        $result['total_paid'] = (float)($data['pricing']['paid'] ?? 0);
        $result['amount_to_pay'] = $result['total_amount'] - $result['total_paid'];
        $result['currency'] = $data['pricing']['currency'] ?? 'USD';

        return $result;
    }
}
