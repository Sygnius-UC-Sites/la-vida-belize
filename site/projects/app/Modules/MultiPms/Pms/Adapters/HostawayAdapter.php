<?php

namespace App\Modules\MultiPms\Pms\Adapters;

use App\Modules\MultiPms\Pms\PmsAdapter;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class HostawayAdapter implements PmsAdapter
{
    protected Client $client;
    protected ?string $apiKey = null;
    protected ?string $accountId = null;

    public function __construct(?Client $client = null)
    {
        try {
            $this->apiKey = class_exists(\ModelHelper::class) 
                ? (\ModelHelper::getDataFromSetting('hostaway_api_key') ?: config('services.hostaway.api_key', '')) 
                : config('services.hostaway.api_key', '');
            $this->accountId = class_exists(\ModelHelper::class) 
                ? (\ModelHelper::getDataFromSetting('hostaway_account_id') ?: config('services.hostaway.account_id', '')) 
                : config('services.hostaway.account_id', '');
        } catch (\Throwable $e) {
            $this->apiKey = config('services.hostaway.api_key', '');
            $this->accountId = config('services.hostaway.account_id', '');
        }

        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.hostaway.com/',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function getName(): string
    {
        return 'hostaway';
    }

    /**
     * Retrieve OAuth2 token using client credentials grant.
     */
    protected function getAccessToken(): ?string
    {
        if (empty($this->apiKey) || empty($this->accountId)) {
            return null;
        }

        return Cache::remember('hostaway_access_token_' . md5($this->apiKey), 7200, function () {
            try {
                $response = $this->client->post('v1/accessTokens', [
                    'form_params' => [
                        'grant_type'    => 'client_credentials',
                        'client_id'     => $this->accountId,
                        'client_secret' => $this->apiKey,
                        'scope'         => 'general'
                    ]
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                return $data['access_token'] ?? null;
            } catch (\Throwable $e) {
                Log::channel('lodgify')->error('Hostaway Access Token Error: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Normalized request helper injecting OAuth Bearer token
     */
    protected function request(string $method, string $uri, array $options = []): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'status' => 401,
                'error'  => 'Missing or invalid Hostaway API credentials (Account ID / API Key)'
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
            Log::channel('lodgify')->error("Hostaway API Error: {$method} {$uri}", ['error' => $body]);
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

    /** Fetch a single reservation by PMS booking ID. */
    public function getReservation(string $reservationId): array
    {
        return $this->request('GET', "v1/reservations/{$reservationId}");
    }

    public function listProperties(): array
    {
        $limit = 100;
        $offset = 0;
        $allItems = [];
        $hasMore = true;

        while ($hasMore) {
            $res = $this->request('GET', 'v1/listings', [
                'query' => [
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]);

            if (($res['status'] ?? 0) !== 200) {
                return $res; // Return error immediately if failed
            }

            $items = $res['data']['result'] ?? [];
            $allItems = array_merge($allItems, $items);

            if (count($items) < $limit) {
                $hasMore = false;
            } else {
                $offset += $limit;
            }
        }

        return [
            'status' => 200,
            'data' => ['result' => $allItems]
        ];
    }

    public function getProperty(string $propertyId): array
    {
        // Fetches listing detail
        $res = $this->request('GET', "v1/listings/{$propertyId}");
        if (($res['status'] ?? 0) === 200 && isset($res['data']['result'])) {
            // Map Hostaway response format to standardized fields expected by the catalog sync
            $hostawayListing = $res['data']['result'];
            
            $standardized = [
                'name'               => $hostawayListing['name'] ?? '',
                'latitude'           => $hostawayListing['latitude'] ?? null,
                'longitude'          => $hostawayListing['longitude'] ?? null,
                'address'            => $hostawayListing['address'] ?? '',
                'zip'                => $hostawayListing['zip'] ?? '',
                'city'               => $hostawayListing['city'] ?? '',
                'country'            => $hostawayListing['country'] ?? '',
                'image_url'          => $hostawayListing['thumbnailUrl'] ?? '',
                'price_unit_in_days' => 'true',
                'min_price'          => $hostawayListing['price'] ?? 0,
                'max_price'          => $hostawayListing['price'] ?? 0,
                'currency'           => $hostawayListing['currency'] ?? 'USD',
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
        if (($res['status'] ?? 0) === 200 && isset($res['data']['result'])) {
            $listing = $res['data']['result'];
            
            // Standardize room structure
            $standardizedRoom = [
                [
                    'id'               => (int)$propertyId,
                    'name'             => $listing['name'] ?? 'Main Room',
                    'description'      => $listing['description'] ?? '',
                    'max_people'       => $listing['personCapacity'] ?? 2,
                    'bedrooms'         => $listing['bedrooms'] ?? 1,
                    'bathrooms'        => $listing['bathrooms'] ?? 1,
                    'pets_allowed'     => ($listing['petsAllowed'] ?? false) ? 1 : 0,
                    'has_parking'      => ($listing['parkingAllowed'] ?? false) ? 1 : 0,
                    'has_wifi'         => ($listing['wirelessInternet'] ?? false) ? 1 : 0,
                    'currency'         => $listing['currency'] ?? $listing['currencyCode'] ?? 'USD',
                    'images'           => !empty($listing['listingImages']) ? array_column($listing['listingImages'], 'url') : (!empty($listing['thumbnailUrl']) ? [$listing['thumbnailUrl']] : []),
                    'amenities'        => $listing['listingAmenities'] ?? [],
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
                'startDate' => $checkin,
                'endDate'   => $checkout
            ]
        ]);
    }

    /**
     * Returns the full raw Hostaway quote response for use by QuoteNormalizer.
     *
     * Attempt 1: priceDetails endpoint (POST v1/listings/{listingId}/calendar/priceDetails)
     *            Returns components[] with accommodation/fee/tax + authoritative totalPrice.
     *            Uses the Hostaway listing ID (pms_property_id), correct field names
     *            (startingDate/endingDate/numberOfGuests/version), and a valid Bearer token.
     * Attempt 2: calendar + listing fallback — per-night prices + cleaning fee only, no taxes.
     *            Logged as 'calendar' source so the UI can warn that totals are approximate.
     */
    public function getRawQuote(array $input): array
    {
        $listingId = $input['property_id'] ?? '';   // Hostaway listing ID stored in pms_property_id
        $checkin   = $input['checkin']     ?? '';
        $checkout  = $input['checkout']    ?? '';
        $guests    = (int)($input['adults'] ?? 1) + (int)($input['children'] ?? 0);

        $endpoint = "v1/listings/{$listingId}/calendar/priceDetails";
        $body     = [
            'startingDate'   => $checkin,
            'endingDate'     => $checkout,
            'numberOfGuests' => $guests,
            'version'        => 2,
        ];

        // Verify token exists before calling
        $token = $this->getAccessToken();
        $tokenOk = !empty($token);

        Log::channel('lodgify')->info('Hostaway getRawQuote attempt', [
            'listing_id'   => $listingId,
            'endpoint'     => $endpoint,
            'body'         => $body,
            'token_exists' => $tokenOk,
            'token_prefix' => $tokenOk ? (substr($token, 0, 6) . '…') : null,
        ]);

        // Attempt 1: priceDetails — authoritative totals including all fees and taxes
        $res = $this->request('POST', $endpoint, ['json' => $body]);

        $resStatus = $res['status'] ?? 0;
        $resData   = $res['data']   ?? $res['error'] ?? [];

        Log::channel('lodgify')->info('Hostaway priceDetails response', [
            'listing_id' => $listingId,
            'status'     => $resStatus,
            'ok'         => $resStatus === 200 && isset($res['data']['result']['totalPrice']),
            'error'      => $res['error'] ?? null,
            // Log result summary, not the full body (may be large)
            'total_price' => $res['data']['result']['totalPrice'] ?? null,
            'component_count' => count($res['data']['result']['components'] ?? []),
        ]);

        if ($resStatus === 200 && isset($res['data']['result']['totalPrice'])) {
            $res['_source']  = 'priceDetails';
            $res['_listing_id'] = $listingId;
            return $res;
        }

        // Log the failure with enough detail to diagnose without exposing secrets
        Log::channel('lodgify')->warning('Hostaway priceDetails failed — falling back to calendar', [
            'listing_id'     => $listingId,
            'status'         => $resStatus,
            'error_message'  => is_array($res['error'] ?? null)
                ? ($res['error']['message'] ?? json_encode($res['error']))
                : ($res['error'] ?? 'unknown'),
            'checkin'        => $checkin,
            'checkout'       => $checkout,
            'guests'         => $guests,
        ]);

        // Check calendar availability for the requested range to aid debugging
        $calRes  = $this->getAvailability([
            'property_id' => $listingId,
            'checkin'     => $checkin,
            'checkout'    => $checkout,
        ]);
        $calDays = $calRes['data']['result'] ?? [];
        $blockedDays = array_filter($calDays, fn($d) => !($d['isAvailable'] ?? true));
        Log::channel('lodgify')->info('Hostaway calendar availability for fallback', [
            'listing_id'   => $listingId,
            'total_days'   => count($calDays),
            'blocked_days' => array_values(array_map(fn($d) => $d['date'] ?? '?', $blockedDays)),
        ]);

        $listingRes = $this->request('GET', "v1/listings/{$listingId}");
        $listing    = $listingRes['data']['result'] ?? [];

        return [
            'status'    => 200,
            '_source'   => 'calendar',
            '_calendar' => $calDays,
            '_listing'  => $listing,
            '_input'    => $input,
        ];
    }

    public function getQuote(array $input): array
    {
        $propertyId = $input['property_id'] ?? '';

        $payload = [
            'listingId'      => (int)$propertyId,
            'arrivalDate'    => $input['checkin'] ?? '',
            'departureDate'  => $input['checkout'] ?? '',
            'numberOfGuests' => (int)($input['people'] ?? 1),
        ];

        $res = $this->request('POST', 'v1/reservations/calculate-price', ['json' => $payload]);

        if (($res['status'] ?? 0) === 200 && isset($res['data']['result'])) {
            $calc = $res['data']['result'];
            
            // Standardize quote structure for the front-end checkout controller
            $standardizedQuote = [
                'subtotal'    => $calc['subtotal'] ?? 0,
                'total'       => $calc['totalPrice'] ?? 0,
                'net_amount'  => $calc['netAmount'] ?? ($calc['totalPrice'] ?? 0),
                'vat_amount'  => $calc['taxAmount'] ?? 0,
                'taxes'       => [],
                'fees'        => []
            ];

            return [
                'status' => 200,
                'data'   => $standardizedQuote
            ];
        }

        if (($res['status'] ?? 0) === 404) {
            $checkin = $input['checkin'] ?? '';
            $checkout = $input['checkout'] ?? '';
            
            $calRes = $this->getAvailability([
                'property_id' => $propertyId,
                'checkin'     => $checkin,
                'checkout'    => $checkout
            ]);

            if (($calRes['status'] ?? 0) === 200 && isset($calRes['data']['result'])) {
                $days = $calRes['data']['result'];
                $subtotal = 0;
                foreach ($days as $day) {
                    $dayDate = $day['date'] ?? '';
                    if ($dayDate && $dayDate < $checkout) {
                        $subtotal += $day['price'] ?? 0;
                    }
                }

                $standardizedQuote = [
                    'subtotal'    => $subtotal,
                    'total'       => $subtotal,
                    'net_amount'  => $subtotal,
                    'vat_amount'  => 0,
                    'taxes'       => [],
                    'fees'        => []
                ];

                return [
                    'status' => 200,
                    'data'   => $standardizedQuote
                ];
            }
        }

        return $res;
    }

    public function createReservation(array $input): array
    {
        $payload = [
            'listingId'      => (int)($input['property_id'] ?? 0),
            'listingMapId'   => (int)($input['property_id'] ?? 0),
            'channelId'      => 2000, // 2000 = Direct Booking channel
            'arrivalDate'    => $input['checkin']   ?? '',
            'departureDate'  => $input['checkout']  ?? '',
            'numberOfGuests' => (int)($input['adults'] ?? 1) + (int)($input['children'] ?? 0),
            'guestName'      => trim(($input['first_name'] ?? '') . ' ' . ($input['last_name'] ?? '')),
            'guestFirstName' => $input['first_name'] ?? '',
            'guestLastName'  => $input['last_name']  ?? '',
            'guestEmail'     => $input['email']  ?? '',
            'guestPhone'     => $input['phone']  ?? '',
            'totalPrice'     => (float)($input['total_amount'] ?? 0),
            'status'         => 'confirmed',
            'comment'        => 'Direct booking from website',
        ];

        // financeField[]: Hostaway requires the priceDetails components[] to be passed so
        // the reservation records the agreed pricing breakdown.
        // Without this, Hostaway may recalculate and the stored totals won't match.
        $rawQuote  = $input['raw_quote'] ?? null;
        $components = $rawQuote['data']['result']['components'] ?? [];
        if (!empty($components)) {
            $payload['financeField'] = array_map(function ($c) {
                return [
                    'id'                     => $c['id'],
                    'listingFeeSettingId'    => $c['listingFeeSettingId'],
                    'type'                   => $c['type'],
                    'name'                   => $c['name'],
                    'title'                  => $c['title'],
                    'alias'                  => $c['alias'] ?? null,
                    'quantity'               => $c['quantity'] ?? null,
                    'units'                  => $c['units']    ?? 1,
                    'value'                  => $c['value'],
                    'total'                  => $c['total'],
                    'isIncludedInTotalPrice' => $c['isIncludedInTotalPrice'] ?? 1,
                    'isQuantitySelectable'   => $c['isQuantitySelectable']   ?? 0,
                    'isMandatory'            => $c['isMandatory'],
                    'isDeleted'              => $c['isDeleted'] ?? 0,
                ];
            }, array_values($components));
        }

        return $this->request('POST', 'v1/reservations', ['json' => $payload]);
    }

    public function updateReservation(array $input): array
    {
        $reservationId = $input['reservation_id'] ?? '';
        if (!$reservationId) return ['status' => 400, 'error' => 'Reservation ID required'];

        $payload = array_intersect_key($input, array_flip([
            'status', 'comment', 'numberOfGuests', 'guestName', 'guestEmail', 'guestPhone'
        ]));

        return $this->request('PUT', "v1/reservations/{$reservationId}", ['json' => $payload]);
    }

    public function cancelReservation(array $input): array
    {
        $reservationId = $input['reservation_id'] ?? '';
        if (!$reservationId) return ['status' => 400, 'error' => 'Reservation ID required'];

        // Hostaway cancellation is triggered by PUTting to /statuses/cancelled
        return $this->request('PUT', "v1/reservations/{$reservationId}/statuses/cancelled", [
            'json' => ['cancelledBy' => 'host']
        ]);
    }

    public function listReservations(array $input = []): array
    {
        $limit = $input['limit'] ?? 50;
        $offset = $input['offset'] ?? 0;
        $allItems = [];
        $hasMore = true;

        while ($hasMore) {
            $res = $this->request('GET', 'v1/reservations', [
                'query' => [
                    'limit'  => $limit,
                    'offset' => $offset
                ]
            ]);

            if (($res['status'] ?? 0) !== 200) {
                return $res;
            }

            $items = $res['data']['result'] ?? [];
            $allItems = array_merge($allItems, $items);

            if (count($items) < $limit) {
                $hasMore = false;
            } else {
                $offset += $limit;
            }
        }

        return [
            'status' => 200,
            'data' => ['result' => $allItems]
        ];
    }

    public function getPaymentLink(string $bookingId): array
    {
        // Hostaway does not provide direct guest payment links via core API v1
        return ['status' => 200, 'data' => ['url' => '']];
    }

    /**
     * Record a collected payment against a Hostaway reservation.
     * Endpoint: POST /v1/reservations/{reservationId}/guestPayments
     * Marks the payment as received so Hostaway's ledger stays in sync.
     */
    public function recordPayment(string $bookingId, float $amount, string $currency): array
    {
        $res = $this->request('POST', "v1/reservations/{$bookingId}/guestPayments", [
            'json' => [
                'amount'        => $amount,
                'currency'      => strtoupper($currency),
                'type'          => 'credit_card',
                'note'          => 'Scheduled installment charged via website (Stripe)',
                'isPaid'        => 1,
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
        // Hostaway manages fees/extras within listing configuration
        return ['status' => 200, 'data' => []];
    }

    /**
     * Register a webhook with Hostaway.
     * POST /v1/webhooks
     * One registration per event type — Hostaway sends one URL per webhook type.
     * Types: reservation_created, reservation_updated, reservation_cancelled,
     *        listing_updated, calendar_updated
     * Docs: https://api.hostaway.com/documentation#/Webhooks
     */
    public function registerWebhook(string $url, array $events): array
    {
        if (empty($events)) {
            $events = ['reservation_created', 'reservation_updated', 'reservation_cancelled', 'calendar_updated'];
        }

        $results = [];
        $allOk   = true;

        // Hostaway requires one POST per event type
        foreach ($events as $event) {
            $res = $this->request('POST', 'v1/webhooks', [
                'json' => [
                    'url'         => $url,
                    'isEnabled'   => 1,
                    'webhookType' => $event,
                ],
            ]);
            $ok = in_array($res['status'] ?? 0, [200, 201]);
            if (!$ok) $allOk = false;
            $err = $res['error'] ?? ($res['data']['message'] ?? 'failed');
            $results[$event] = [
                'ok'         => $ok,
                'webhook_id' => $res['data']['result']['id'] ?? null,
                'error'      => $ok ? null : (is_array($err) ? json_encode($err) : $err),
                'raw'        => $res,
            ];
        }

        return [
            'ok'         => $allOk,
            'webhook_id' => null, // multiple IDs — stored per-event in results
            'results'    => $results,
            'error'      => $allOk ? null : 'One or more Hostaway webhook registrations failed',
        ];
    }

    /**
     * GET /v1/reviews?listingId={id}&limit=50
     * Returns: {result: [{id, rating, description, guestName, reviewedAt, arrivalDate, departureDate}]}
     * Rating: 1–10 (Hostaway uses 1-10 scale) → normalize to 1-5.
     */
    public function getReviews(string $propertyId): array
    {
        $res = $this->request('GET', 'v1/reviews', [
            'query' => ['listingId' => $propertyId, 'limit' => 50],
        ]);

        if (($res['status'] ?? 0) !== 200) return [];

        $reviews = $res['data']['result'] ?? [];
        if (!is_array($reviews)) return [];

        $out = [];
        foreach ($reviews as $r) {
            // Hostaway rating can be 1-10; normalize to 1-5
            $rawScore = (float)($r['rating'] ?? 5);
            $score    = (int)round($rawScore > 5 ? $rawScore / 2 : $rawScore);
            $score    = max(1, min(5, $score));

            $out[] = [
                'id'        => (string)($r['id'] ?? uniqid()),
                'name'      => $r['guestName'] ?? 'Guest',
                'score'     => $score,
                'message'   => $r['description'] ?? $r['comment'] ?? '',
                'stay_date' => $r['arrivalDate']   ?? null,
                'end_date'  => $r['departureDate']  ?? null,
                'profile'   => $r['guestLocation'] ?? null,
            ];
        }
        return $out;
    }

    // Raw Storage & Frontend Mapping
    public function fetchAndSaveRawData(string $propertyId): void
    {
        // IMPORTANT: Hostaway's includeRelations parameter only works on the LIST endpoint,
        // not on GET /v1/listings/{id}. Single-listing endpoint always returns empty arrays
        // for listingImages/listingAmenities regardless of includeRelations.
        // Solution: use the list endpoint filtered by listingId[].
        $qs = 'includeRelations[]=listingImages'
            . '&includeRelations[]=listingAmenities'
            . '&includeRelations[]=listingBedrooms'
            . '&listingId[]=' . urlencode($propertyId);

        $res = $this->request('GET', "v1/listings?{$qs}");

        $propertyRaw = null;
        if (($res['status'] ?? 0) === 200) {
            $items = $res['data']['result'] ?? [];
            foreach ($items as $item) {
                if ((string)($item['id'] ?? '') === (string)$propertyId) {
                    $propertyRaw = $item;
                    break;
                }
            }
            // Fallback: take first result if only one returned
            if (!$propertyRaw && count($items) === 1) {
                $propertyRaw = $items[0];
            }
        }

        \App\Modules\MultiPms\Models\HostawayData::updateOrCreate(
            ['pms_property_id' => $propertyId],
            [
                'raw_property_json' => null,
                'raw_units_json'    => $propertyRaw ? json_encode($propertyRaw) : null,
            ]
        );
    }

    public function getFrontendData(string $propertyId): array
    {
        $data = \App\Modules\MultiPms\Models\HostawayData::where('pms_property_id', $propertyId)->first();
        if (!$data || !$data->raw_units_json) {
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
        
        $listing = json_decode($data->raw_units_json, true);

        // ── Images: sort by sortOrder, fall back to thumbnail ──────────────────
        $images = [];
        if (!empty($listing['listingImages']) && is_array($listing['listingImages'])) {
            $imgs = $listing['listingImages'];
            usort($imgs, fn($a, $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));
            $images = array_values(array_filter(array_map(fn($i) => $i['url'] ?? null, $imgs)));
        }
        if (empty($images) && !empty($listing['thumbnailUrl'])) {
            $images[] = $listing['thumbnailUrl'];
        }

        // ── Amenities: normalize objects/ids to display names ──────────────────
        $amenities = $this->normalizeAmenities($listing['listingAmenities'] ?? []);

        // Property type — Hostaway returns propertyType string (e.g. "apartment", "house")
        $rawType      = $listing['propertyType'] ?? $listing['listingType'] ?? null;
        $propertyType = $rawType ? strtolower(str_replace(['_','-'], ' ', (string)$rawType)) : null;

        // Bed configuration — Hostaway returns listingBedrooms[] with bedType details
        $bedConfig = [];
        foreach ($listing['listingBedrooms'] ?? [] as $br) {
            if (!is_array($br)) continue;
            $beds = [];
            foreach ($br['listingBedroomBeds'] ?? $br['beds'] ?? [] as $bed) {
                $beds[] = [
                    'type'  => $bed['bedType'] ?? $bed['type'] ?? 'bed',
                    'count' => (int)($bed['quantity'] ?? $bed['count'] ?? 1),
                ];
            }
            $bedConfig[] = [
                'name' => $br['bedroomName'] ?? $br['name'] ?? ('Bedroom ' . (count($bedConfig) + 1)),
                'beds' => $beds,
            ];
        }

        return [
            'title'         => $listing['name'] ?? $listing['internalListingName'] ?? 'Main Unit',
            'description'   => $listing['description'] ?? $listing['airbnbSummary'] ?? '',
            'images'        => $images,
            'bedrooms'      => $listing['bedroomsNumber'] ?? $listing['bedrooms'] ?? 1,
            'bathrooms'     => $listing['bathroomsNumber'] ?? $listing['bathrooms'] ?? 1,
            'guests'        => $listing['personCapacity'] ?? 2,
            'amenities'     => $amenities,
            'area'          => $listing['squareMeters'] ?? null,
            'area_unit'     => 'm²',
            'property_type' => $propertyType,
            'bed_config'    => $bedConfig ?: null,
            'check_in'      => $listing['checkInTimeStart'] ?? null,
            'check_out'     => $listing['checkOutTime'] ?? null,
            'address' => [
                'address' => $listing['address'] ?? $listing['publicAddress'] ?? '',
                'city'    => $listing['city']    ?? '',
                'state'   => $listing['state']   ?? '',
                'zip'     => $listing['zipcode'] ?? '',
                'country' => $listing['country'] ?? $listing['countryCode'] ?? '',
                'lat'     => $listing['lat'] ?? null,
                'lng'     => $listing['lng'] ?? null,
            ],
            'pricing' => [
                'currency' => $listing['currency'] ?? $listing['currencyCode'] ?? 'USD',
                'basePrice' => $listing['price'] ?? $listing['basePrice'] ?? 0,
            ],
            'availability' => [],
            'fees' => [
                'cleaning' => $listing['cleaningFee'] ?? null,
                'checkin' => $listing['checkinFee'] ?? null,
                'deposit' => $listing['refundableDamageDeposit'] ?? null,
            ],
            'house_rules' => $listing['houseRules'] ?? '',
            'cancellation_policy' => $listing['cancellationPolicy'] ?? '',
            'booking_rules' => [],
            'key_facts' => \App\Modules\MultiPms\Helper\ListingFacts::build([
                'check_in'  => $listing['checkInTimeStart'] ?? null,
                'check_out' => $listing['checkOutTime'] ?? null,
                'min_stay'  => $listing['minNights'] ?? null,
                'max_stay'  => $listing['maxNights'] ?? null,
                'pets_allowed' => isset($listing['maxPetsAllowed']) ? ((int) $listing['maxPetsAllowed'] > 0) : null,
                'suitable_for_children' => isset($listing['maxChildrenAllowed']) ? ((int) $listing['maxChildrenAllowed'] > 0) : null,
            ], $amenities, (string) ($listing['houseRules'] ?? '')),
            'pricing_normalized' => \App\Modules\MultiPms\Helper\ListingPricing::normalize(
                [
                    'currency'  => $listing['currency'] ?? $listing['currencyCode'] ?? 'USD',
                    'basePrice' => $listing['price'] ?? $listing['basePrice'] ?? 0,
                ],
                [
                    'cleaning' => $listing['cleaningFee'] ?? null,
                    'checkin'  => $listing['checkinFee'] ?? null,
                    'deposit'  => $listing['refundableDamageDeposit'] ?? null,
                ],
                'hostaway'
            ),
        ];
    }

    /**
     * Normalize Hostaway listingAmenities (objects, ids, or strings) into a flat list of display names.
     */
    protected function normalizeAmenities($raw): array
    {
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $a) {
            if (is_string($a)) {
                $out[] = $a;
            } elseif (is_array($a)) {
                $name = $a['amenityName'] ?? $a['name'] ?? ($a['amenity']['name'] ?? null);
                if ($name) $out[] = $name;
            }
        }
        return array_values(array_unique(array_filter($out)));
    }

    public function parseWebhook(array $payload): array
    {
        // Parse a standard Hostaway webhook callback body
        $data = $payload;

        $result = [];
        $result['arrival'] = $data['arrivalDate'] ?? null;
        $result['departure'] = $data['departureDate'] ?? null;
        $result['property_id'] = $data['listingId'] ?? null;
        $result['pms_booking_id'] = $data['id'] ?? null; // Store Hostaway reservation ID in pms_booking_id column
        $result['status'] = $data['status'] ?? null;
        $result['source'] = $data['channelName'] ?? 'Website';
        $result['source_text'] = $data['channelName'] ?? 'Website Booking';
        $result['property_name'] = $data['listingName'] ?? null;
        $result['people'] = $data['numberOfGuests'] ?? 1;

        $result['guest'] = json_encode([
            'name' => ($data['guestFirstName'] ?? '') . ' ' . ($data['guestLastName'] ?? ''),
            'email' => $data['guestEmail'] ?? '',
            'phone' => $data['guestPhone'] ?? ''
        ]);

        $result['latest_webhook_event_id'] = md5(json_encode($payload));
        $result['total_amount'] = (float)($data['totalPrice'] ?? 0);
        $result['total_paid'] = (float)($data['totalPaid'] ?? 0);
        $result['amount_to_pay'] = $result['total_amount'] - $result['total_paid'];
        $result['currency'] = $data['currency'] ?? 'USD';

        return $result;
    }
}
