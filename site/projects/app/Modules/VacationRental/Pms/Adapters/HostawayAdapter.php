<?php

namespace App\Modules\VacationRental\Pms\Adapters;

use App\Modules\VacationRental\Pms\PmsAdapter;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class HostawayAdapter implements PmsAdapter
{
    protected Client $client;
    protected ?string $apiKey = null;
    protected ?string $accountId = null;
    protected bool $testMode = false;

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

        if ($client !== null) {
            $this->testMode = true;
            $this->client   = $client;
        } else {
            $this->client = new Client([
                'base_uri' => 'https://api.hostaway.com/',
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);
        }
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
        if ($this->testMode) {
            // Mock client injected — bypass OAuth token acquisition entirely
        } else {
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
        }

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
            Log::channel('lodgify')->error("Hostaway API Error: {$method} {$uri}", ['error' => $this->safeLogBody($body)]);
            return [
                'status' => $statusCode,
                'error'  => $body
            ];
        }
    }


    /**
     * Redact PII and secrets from a PMS API error body, then truncate for safe logging.
     *
     * Redaction happens BEFORE truncation so sensitive data never appears even in
     * the first 500 characters. Patterns removed:
     *   - Email addresses          -> [EMAIL]
     *   - E.164 / US phone numbers -> [PHONE]
     *   - Bearer / auth tokens     -> [TOKEN]
     *   - JSON token/auth/secret fields -> [REDACTED]
     *
     * The original $body is returned to callers unchanged; only the log copy
     * is sanitised. Never pass Stripe PaymentIntent objects through this method.
     */
    protected function safeLogBody(mixed $body, int $maxLen = 500): string
    {
        $str = is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE) : (string) $body;

        $str = preg_replace([
            // Email addresses
            '/[a-zA-Z0-9._%+\\-]+@[a-zA-Z0-9.\\-]+\\.[a-zA-Z]{2,}/',
            // E.164 international phones
            '/\\+\\d{1,3}[\\s.\\-]?(?:\\(?\\d{1,4}\\)?[\\s.\\-]?){2,5}\\d{2,4}/',
            // US local format (555) 123-4567
            '/\\(?\\d{3}\\)?[\\s.\\-]\\d{3}[\\s.\\-]\\d{4}/',
            // Bearer / JWT tokens
            '/Bearer\\s+[A-Za-z0-9\\-._~+\\/=]+/',
            // JSON auth fields: "token":"...", "authorization":"..." etc.
            '/(["](token|authorization|auth|api_?key|secret|password|apikey)["]\\s*:\\s*["])[^"]{1,200}(["])/i',
        ], ['[EMAIL]', '[PHONE]', '[PHONE]', 'Bearer [TOKEN]', '$1[REDACTED]$3'], $str) ?? $str;

        return mb_strlen($str) > $maxLen ? mb_substr($str, 0, $maxLen) . '...' : $str;
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

    public function listProperties(array $options = []): array
    {
        $limit = 100;
        $offset = 0;
        $allItems = [];
        $hasMore = true;

        // Incremental sync: only fetch listings modified since the given timestamp.
        // Hostaway supports modifiedAfter (unix timestamp). Subtract 5 min as a safety buffer.
        $updatedSince = $options['updated_since'] ?? null;
        $modifiedAfterParam = null;
        if ($updatedSince) {
            $modifiedAfterParam = \Carbon\Carbon::parse($updatedSince)->subMinutes(5)->timestamp;
        }

        while ($hasMore) {
            $query = ['limit' => $limit, 'offset' => $offset];
            if ($modifiedAfterParam) {
                $query['modifiedAfter'] = $modifiedAfterParam;
            }
            $res = $this->request('GET', 'v1/listings', [
                'query' => $query
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
        $adults    = (int)($input['adults']   ?? 1);
        $children  = (int)($input['children'] ?? 0);
        $infants   = (int)($input['infants']  ?? 0);
        $pets      = (int)($input['pets']     ?? 0);

        $endpoint = "v1/listings/{$listingId}/calendar/priceDetails";
        $body     = [
            'startingDate'   => $checkin,
            'endingDate'     => $checkout,
            // numberOfGuests = adults + children (Hostaway total-guest field)
            'numberOfGuests' => $adults + $children,
            // Per-type breakdown — Hostaway uses these to apply pet fees and
            // child extra charges when configured on the listing.
            'numberOfAdults'   => $adults,
            'numberOfChildren' => $children,
            'numberOfInfants'  => $infants,
            'numberOfPets'     => $pets,
            'version'          => 2,
        ];
        // Pass coupon/promo code to Hostaway if provided
        $promoCode = trim($input['promo_code'] ?? '');
        if ($promoCode !== '') {
            $body['couponCode'] = $promoCode;
        }

        // ── Optional fee components ───────────────────────────────────────────
        // Hostaway priceDetails accepts a `components` array for non-mandatory
        // optional fees. Each entry: { listingFeeSettingId, name, quantity }.
        //
        // We build this from validated optional_services + auto-include petFee
        // components (identified by is_per_pet=true in canonical fees, NOT by name).
        // numberOfPets alone is NOT enough — Hostaway only applies pet fees
        // automatically if the listing has them configured as mandatory; for
        // optional pet fees the component must be explicitly included.
        //
        // NOTE: Do NOT rely on numberOfPets being accepted by priceDetails as a
        // trigger for petFee — use the components array for reliable behaviour.
        if ($this->testMode) {
            $canonicalFees = [];
        } else {
            $canonicalFees = json_decode(
                \App\Models\Pms\PmsRoom::where('pms_property_id', $listingId)
                    ->where('pms_source', 'hostaway')
                    ->value('optional_fees_json') ?? '[]',
                true
            ) ?: [];
        }

        $confirmedServices = $input['optional_services'] ?? [];
        $components = \App\Helper\FeeValidator::buildHostawayComponents(
            $confirmedServices,
            $canonicalFees,
            $pets
        );
        if (!empty($components)) {
            $body['components'] = $components;
            Log::channel('lodgify')->info('Hostaway priceDetails: including fee components', [
                'listing_id' => $listingId,
                'components' => array_map(fn($c) => [
                    'id'  => $c['listingFeeSettingId'],
                    'qty' => $c['quantity'],
                ], $components),
            ]);
        }

        // Verify token exists before calling (skip in testMode — no credentials available)
        $token    = $this->testMode ? null : $this->getAccessToken();
        $tokenOk  = !empty($token);

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
        $adults   = (int)($input['adults']   ?? 1);
        $children = (int)($input['children'] ?? 0);
        $infants  = (int)($input['infants']  ?? 0);
        $pets     = (int)($input['pets']     ?? 0);

        $payload = [
            'listingId'        => (int)($input['property_id'] ?? 0),
            'listingMapId'     => (int)($input['property_id'] ?? 0),
            'channelId'        => 2000, // 2000 = Direct Booking channel
            'arrivalDate'      => $input['checkin']  ?? '',
            'departureDate'    => $input['checkout'] ?? '',
            // numberOfGuests = total (required by Hostaway)
            'numberOfGuests'   => $adults + $children,
            // Per-type breakdown — Hostaway stores these on the reservation and
            // uses them for pet-fee and child-fee validation on their side.
            'numberOfAdults'   => $adults,
            'numberOfChildren' => $children,
            'numberOfInfants'  => $infants,
            'numberOfPets'     => $pets,
            'guestName'        => trim(($input['first_name'] ?? '') . ' ' . ($input['last_name'] ?? '')),
            'guestFirstName'   => $input['first_name'] ?? '',
            'guestLastName'    => $input['last_name']  ?? '',
            'guestEmail'       => $input['email']  ?? '',
            'guestPhone'       => $input['phone']  ?? '',
            'totalPrice'       => (float)($input['total_amount'] ?? 0),
            'status'           => 'confirmed',
            'comment'          => 'Direct booking from website',
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
     * Fetch account-level auto-payment rules.
     * Endpoint: GET /v1/guestPayments/autoPayment
     *
     * Returns rules grouped by guestAutoInvoiceId. Each group defines how
     * payments are split for reservations that use that invoice template.
     *
     * Rule fields used:
     *   triggerEvent    — "reservation" | "arrival" | "departure"
     *   triggerTimeDelta — hours relative to trigger (negative = before)
     *   percentageFee   — % of total (null when isRestOfPay=1)
     *   isRestOfPay     — 1 = charge whatever remains
     *
     * Returns: ['rules' => [...], 'grouped' => [guestAutoInvoiceId => [...]]]
     */
    public function getAutoPaymentRules(): array
    {
        $res = $this->request('GET', 'v1/guestPayments/autoPayment');
        if (($res['status'] ?? 0) !== 200) {
            return ['rules' => [], 'grouped' => []];
        }
        $rules   = $res['data']['result'] ?? $res['data'] ?? [];
        $grouped = [];
        foreach ($rules as $rule) {
            $id = $rule['guestAutoInvoiceId'] ?? 'default';
            $grouped[$id][] = $rule;
        }
        return ['rules' => $rules, 'grouped' => $grouped];
    }

    /**
     * Parse auto-payment rules into deposit_percent + balance_due_days.
     *
     * Takes the first group (most accounts have one). If multiple groups exist,
     * uses the first one (listing-level mapping is not available via this API).
     *
     * Returns: ['deposit_percent' => int|null, 'balance_due_days' => int|null]
     */
    public function parseAutoPaymentRules(array $rulesData): array
    {
        $grouped = $rulesData['grouped'] ?? [];
        if (empty($grouped)) return ['deposit_percent' => null, 'balance_due_days' => null];

        // Use first group
        $group = array_values($grouped)[0];

        $depositPct     = null;
        $balanceDueDays = null;

        foreach ($group as $rule) {
            $triggerEvent = strtolower($rule['triggerEvent'] ?? '');
            $timeDelta    = (int)($rule['triggerTimeDelta'] ?? 0);
            $pct          = isset($rule['percentageFee']) ? (float)$rule['percentageFee'] : null;
            $isRest       = (int)($rule['isRestOfPay'] ?? 0);

            // Deposit rule: fires at/after reservation confirmation
            if ($triggerEvent === 'reservation' && $timeDelta >= 0 && $pct !== null && $depositPct === null) {
                $depositPct = (int)round($pct);
            }

            // Balance rule: rest-of-pay, fires before arrival
            if ($isRest === 1 && $triggerEvent === 'arrival' && $timeDelta < 0 && $balanceDueDays === null) {
                $balanceDueDays = (int)round(abs($timeDelta) / 24); // hours → days
            }
        }

        return [
            'deposit_percent'  => $depositPct,
            'balance_due_days' => $balanceDueDays,
        ];
    }

    /**
     * Fetch generated charges for a confirmed Hostaway reservation.
     * Endpoint: GET /v1/guestPayments/charges?reservationId={id}
     *
     * Returns the authoritative split: actual amounts + scheduled dates.
     * Call this after the Hostaway reservation is created to correct amount_data.
     */
    public function getReservationCharges(string $reservationId): array
    {
        $res = $this->request('GET', 'v1/guestPayments/charges', [
            'query' => ['reservationId' => $reservationId],
        ]);
        if (($res['status'] ?? 0) !== 200) {
            return ['ok' => false, 'charges' => []];
        }
        $charges = $res['data']['result'] ?? $res['data'] ?? [];
        return ['ok' => true, 'charges' => $charges];
    }

    /**
     * Record a collected Stripe payment as an offline paid charge in Hostaway.
     * Endpoint: POST /v1/guestPayments/charges/{reservationId}
     *
     * Hostaway does not document API-level idempotency on this endpoint, so a
     * timed-out request leaves the outcome uncertain — the charge may or may not
     * have been created. The pre-flight lookup disambiguates this:
     *
     *   lookup → found PI      : return existing charge (recovered, no POST)
     *   lookup → PI not found  : POST is safe (GET confirmed no duplicate)
     *   lookup → GET failed
     *     $isRetry = false     : first attempt, no prior POST possible → POST allowed
     *     $isRetry = true      : previous attempt outcome unknown    → do NOT POST,
     *                            return error so retry fires later when GET may succeed
     *
     * @param string $stripeIntentId Stripe PaymentIntent ID embedded in description
     * @param bool   $isRetry        True when retrying after a previous uncertain outcome
     *
     * Returns: ['ok', 'charge_id', 'data', 'error', '_recovered' (bool)]
     */
    public function recordPayment(string $bookingId, float $amount, string $currency, string $stripeIntentId = '', bool $isRetry = false): array
    {
        if ($stripeIntentId) {
            $lookup = $this->findExistingChargeByPI($bookingId, $stripeIntentId);

            if ($lookup['found']) {
                // Existing charge confirmed — return it without POSTing.
                Log::channel('lodgify')->info('HostawayAdapter::recordPayment: existing charge recovered', [
                    'reservation_id' => $bookingId,
                    'charge_id'      => $lookup['charge_id'],
                    'stripe_intent'  => $stripeIntentId,
                ]);
                return [
                    'ok'         => true,
                    'charge_id'  => $lookup['charge_id'],
                    'status'     => 200,
                    'data'       => [],
                    'error'      => null,
                    '_recovered' => true,
                ];
            }

            if (!$lookup['lookup_ok']) {
                // GET failed — we cannot determine whether a previous POST succeeded.
                if ($isRetry) {
                    // Uncertain prior result + lookup failure → do NOT POST.
                    // Returning an error queues another retry later when the GET may succeed.
                    $msg = 'Hostaway charge lookup failed on retry — not proceeding to POST '
                         . 'to avoid duplicate charge. Will retry when GET becomes available. '
                         . 'Lookup error: ' . ($lookup['error'] ?? 'unknown');
                    Log::channel('lodgify')->warning('HostawayAdapter::recordPayment: blocked POST on retry after lookup failure', [
                        'reservation_id' => $bookingId,
                        'stripe_intent'  => $stripeIntentId,
                        'lookup_error'   => $lookup['error'] ?? null,
                    ]);
                    return ['ok' => false, 'charge_id' => null, 'status' => 0, 'data' => [], 'error' => $msg, '_recovered' => false];
                }
                // First attempt + GET failed → no prior POST possible → safe to proceed.
                Log::channel('lodgify')->info('HostawayAdapter::recordPayment: lookup failed on first attempt — proceeding to POST', [
                    'reservation_id' => $bookingId,
                    'lookup_error'   => $lookup['error'] ?? null,
                ]);
            }
            // $lookup['lookup_ok'] && !$lookup['found'] → GET confirmed no duplicate → safe to POST.
        }

        // ── POST new charge ──────────────────────────────────────────────────
        $res = $this->request('POST', "v1/guestPayments/charges/{$bookingId}", [
            'json' => [
                'title'         => 'Website Stripe payment',
                'description'   => $stripeIntentId
                    ? "Stripe PaymentIntent {$stripeIntentId}"
                    : 'External Stripe payment collected via website',
                'amount'        => $amount,
                'paymentMethod' => 'credit_card',
                'status'        => 'paid',
                'scheduledDate' => now()->format('Y-m-d H:i:s'),
            ],
        ]);

        $ok       = ($res['status'] ?? 0) === 200 || ($res['status'] ?? 0) === 201;
        $chargeId = $res['data']['result']['id'] ?? $res['data']['id'] ?? null;

        return [
            'ok'         => $ok,
            'charge_id'  => $chargeId,
            'status'     => $res['status'] ?? 0,
            'data'       => $res['data'] ?? [],
            'error'      => $res['error'] ?? null,
            '_recovered' => false,
        ];
    }

    /**
     * Search existing Hostaway charges for one whose description contains $stripeIntentId.
     *
     * Returns an array with three distinct states:
     *   ['found' => true,  'lookup_ok' => true,  'charge_id' => 'id']  — match found
     *   ['found' => false, 'lookup_ok' => true,  'charge_id' => null]  — GET ok, no match
     *   ['found' => false, 'lookup_ok' => false, 'error' => '...']     — GET failed
     *
     * The three-state return lets recordPayment() distinguish "no existing charge"
     * (safe to POST) from "lookup failed" (unsafe to POST on retry).
     */
    private function findExistingChargeByPI(string $reservationId, string $stripeIntentId): array
    {
        try {
            $res = $this->request('GET', 'v1/guestPayments/charges', [
                'query' => ['reservationId' => $reservationId],
            ]);

            if (($res['status'] ?? 0) !== 200) {
                return [
                    'found'     => false,
                    'lookup_ok' => false,
                    'charge_id' => null,
                    'error'     => 'GET charges returned HTTP ' . ($res['status'] ?? 0),
                ];
            }

            $charges = $res['data']['result'] ?? $res['data'] ?? [];
            foreach ((array)$charges as $charge) {
                $desc = (string)($charge['description'] ?? $charge['title'] ?? '');
                if (str_contains($desc, $stripeIntentId)) {
                    $id = (string)($charge['id'] ?? '');
                    if ($id !== '') {
                        return ['found' => true, 'lookup_ok' => true, 'charge_id' => $id, 'error' => null];
                    }
                }
            }

            return ['found' => false, 'lookup_ok' => true, 'charge_id' => null, 'error' => null];

        } catch (\Throwable $e) {
            return [
                'found'     => false,
                'lookup_ok' => false,
                'charge_id' => null,
                'error'     => $e->getMessage(),
            ];
        }
    }

    public function getPropertyAddons(string $propertyId, array $query = []): array
    {
        // Hostaway manages fees/extras within listing configuration
        return ['status' => 200, 'data' => []];
    }

    /**
     * Register a webhook with Hostaway.
     * Hostaway unified webhooks are account-wide and do not accept event filters.
     * Docs: https://api.hostaway.com/documentation#/Webhooks
     */
    public function registerWebhook(string $url, array $events): array
    {
        $listRes = $this->request('GET', 'v1/webhooks/unifiedWebhooks');
        $webhooks = $listRes['data']['result'] ?? $listRes['data'] ?? [];
        if (!is_array($webhooks)) {
            $webhooks = [];
        }

        $existing = collect($webhooks)->first(
            fn ($webhook) => rtrim((string) ($webhook['url'] ?? ''), '/') === rtrim($url, '/')
        );
        $payload = [
            'isEnabled' => 1,
            'url' => $url,
            'login' => null,
            'password' => null,
            'alertingEmailAddress' => null,
        ];

        if ($existing && (int) ($existing['isEnabled'] ?? 1) === 1) {
            $res = ['status' => 200, 'data' => ['result' => $existing]];
        } elseif ($existing) {
            $res = $this->request('PUT', 'v1/webhooks/unifiedWebhooks/' . ($existing['id'] ?? ''), [
                'json' => $payload,
            ]);
        } else {
            $res = $this->request('POST', 'v1/webhooks/unifiedWebhooks', [
                'json' => $payload,
            ]);
        }

        $ok = in_array($res['status'] ?? 0, [200, 201]);
        $err = $res['error'] ?? ($res['data']['message'] ?? 'Hostaway unified webhook registration failed');
        return [
            'ok' => $ok,
            'webhook_id' => $res['data']['result']['id'] ?? $existing['id'] ?? null,
            'existing' => (bool) $existing,
            'events' => ['reservation created', 'reservation updated', 'message received'],
            'error' => $ok ? null : (is_array($err) ? json_encode($err) : $err),
            'raw' => $res,
        ];
    }

    /**
     * GET /v1/reviews?listingId={id}&limit=50
     * Returns: {result: [{id, rating, description, guestName, reviewedAt, arrivalDate, departureDate}]}
     * Rating: 1–10 (Hostaway uses 1-10 scale) → normalize to 1-5.
     */
    public function getReviews(string $propertyId): array
    {
        // Hostaway reviews filter uses listingMapIds[] (channel-mapping IDs),
        // NOT listingId. Since we don't store listingMapId, get all account reviews
        // and filter client-side by internalListingName (matched in SyncPmsReviews).
        $res = $this->request('GET', 'v1/reviews?limit=200&sortBy=arrivalDate&sortOrder=desc');

        if (($res['status'] ?? 0) !== 200) return [];

        $reviews = $res['data']['result'] ?? [];
        if (!is_array($reviews)) return [];

        // LOG first review keys so we know the exact field names
        if (!empty($reviews[0])) {
            \Log::channel('lodgify')->info('Hostaway review fields (first review)', [
                'keys'   => array_keys($reviews[0]),
                'sample' => array_intersect_key($reviews[0], array_flip([
                    'id','guestName','rating','description','comment','guestReview',
                    'publicReview','reviewText','body','text','review','message',
                    'arrivalDate','departureDate','checkInDate','checkOutDate',
                ])),
            ]);
        }

        $out = [];
        foreach ($reviews as $r) {
            // Hostaway rating can be 1-10; normalize to 1-5
            $rawScore = (float)($r['rating'] ?? $r['guestRating'] ?? 5);
            $score    = (int)round($rawScore > 5 ? $rawScore / 2 : $rawScore);
            $score    = max(1, min(5, $score));

            // Try every known field name Hostaway uses for the review text
            $message = $r['guestReview']   ?? $r['publicReview']  ?? $r['reviewText']
                    ?? $r['description']   ?? $r['comment']       ?? $r['body']
                    ?? $r['text']          ?? $r['review']        ?? $r['message'] ?? '';

            $out[] = [
                'id'           => (string)($r['id'] ?? uniqid()),
                'name'         => $r['guestName'] ?? $r['reviewerName'] ?? 'Guest',
                'score'        => $score,
                'message'      => $message,
                'stay_date'    => $r['arrivalDate']  ?? $r['checkInDate']  ?? null,
                'end_date'     => $r['departureDate'] ?? $r['checkOutDate'] ?? null,
                'profile'      => $r['guestLocation'] ?? $r['location']    ?? null,
                // Used by SyncPmsReviews to assign reviews to the correct property
                'listing_name' => $r['internalListingName'] ?? $r['listingName'] ?? '',
            ];
        }
        return $out;
    }

    // Raw Storage & Frontend Mapping
    public function fetchAndSaveRawData(string $propertyId): void
    {
        // Attempt 1: list endpoint with includeRelations — this is the only way to get
        // listingImages, listingAmenities, and listingBedrooms in a single call.
        // IMPORTANT: Hostaway requires bracket-notation array params (listingId[]=X,
        // includeRelations[]=Y). Guzzle's query array encodes these as listingId[0]=X
        // which Hostaway ignores — so we build the query string manually.
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
            if (!$propertyRaw && count($items) === 1) {
                $propertyRaw = $items[0];
            }
        }

        // Attempt 2: if relations came back empty, try fetching amenities via a
        // dedicated amenities endpoint (Hostaway plan-dependent).
        if ($propertyRaw && empty($propertyRaw['listingAmenities'])) {
            $amenRes = $this->request('GET', "v1/listings/{$propertyId}/amenities");
            if (($amenRes['status'] ?? 0) === 200) {
                $amenItems = $amenRes['data']['result'] ?? [];
                if (!empty($amenItems)) {
                    $propertyRaw['listingAmenities'] = $amenItems;
                    Log::channel('lodgify')->info("Hostaway: fetched amenities via dedicated endpoint for {$propertyId}", ['count' => count($amenItems)]);
                }
            }
        }

        // Attempt 3: if listingImages also came back empty, fetch them separately.
        if ($propertyRaw && empty($propertyRaw['listingImages'])) {
            $imgRes = $this->request('GET', "v1/listings/{$propertyId}/photos");
            if (($imgRes['status'] ?? 0) === 200) {
                $imgItems = $imgRes['data']['result'] ?? [];
                if (!empty($imgItems)) {
                    $propertyRaw['listingImages'] = $imgItems;
                }
            }
        }

        // Fetch listing fee settings so we can build canonical fee definitions.
        // GET /v1/listingFeeSettings/{listingId} returns all fees configured for the listing:
        //   id, alias, feeType, feeAppliedPer, amountType, amount, isMandatory,
        //   isQuantitySelectable, quantity
        // These are stored alongside the listing data and consumed by getFrontendData().
        $feeSettings = [];
        $feeRes = $this->request('GET', "v1/listingFeeSettings/{$propertyId}");
        $feeSettingsFetched = ($feeRes['status'] ?? 0) === 200;
        if ($feeSettingsFetched) {
            $feeSettings = $feeRes['data']['result'] ?? $feeRes['data'] ?? [];
            if (!is_array($feeSettings)) $feeSettings = [];
            Log::channel('lodgify')->info("Hostaway: fetched listingFeeSettings for {$propertyId}", [
                'count' => count($feeSettings),
                'first_keys' => !empty($feeSettings[0]) ? array_keys($feeSettings[0]) : [],
            ]);
        } else {
            Log::channel('lodgify')->warning("Hostaway: listingFeeSettings fetch failed for {$propertyId}", [
                'status' => $feeRes['status'] ?? 0,
            ]);
        }
        if ($propertyRaw) {
            $propertyRaw['_feeSettings'] = $feeSettings;
            $propertyRaw['_feeSettingsFetched'] = $feeSettingsFetched;
        }

        \App\Models\Pms\HostawayData::updateOrCreate(
            ['pms_property_id' => $propertyId],
            [
                'raw_property_json' => null,
                'raw_units_json'    => $propertyRaw ? json_encode($propertyRaw) : null,
            ]
        );
    }

    public function getFrontendData(string $propertyId): array
    {
        $data = \App\Models\Pms\HostawayData::where('pms_property_id', $propertyId)->first();
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
            'key_facts' => \App\Helper\ListingFacts::build([
                'check_in'  => $listing['checkInTimeStart'] ?? null,
                'check_out' => $listing['checkOutTime'] ?? null,
                'min_stay'  => $listing['minNights'] ?? null,
                'max_stay'  => $listing['maxNights'] ?? null,
                'pets_allowed' => isset($listing['maxPetsAllowed']) ? ((int) $listing['maxPetsAllowed'] > 0) : null,
                'suitable_for_children' => isset($listing['maxChildrenAllowed']) ? ((int) $listing['maxChildrenAllowed'] > 0) : null,
            ], $amenities, (string) ($listing['houseRules'] ?? '')),
            // max_pets: Hostaway exposes maxPetsAllowed directly on the listing object.
            // Store it so the frontend can cap the pet stepper at this value.
            'max_pets' => isset($listing['maxPetsAllowed']) ? (int)$listing['maxPetsAllowed'] : null,
            'pricing_normalized' => \App\Helper\ListingPricing::normalize(
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
            // Canonical fee definitions built from listingFeeSettings fetched during sync.
            'canonical_fees' => $this->buildHostawayCanonicalFees(
                ($listing['_feeSettingsFetched'] ?? false) ? ($listing['_feeSettings'] ?? []) : null
            ),
        ];
    }

    /**
     * Build canonical fee definitions from Hostaway listingFeeSettings.
     *
     * Stored as _feeSettings on the listing object during fetchAndSaveRawData.
     * Returns null if no fee settings were fetched (first sync or API unavailable).
     */
    private function buildHostawayCanonicalFees(?array $feeSettings): ?array
    {
        if ($feeSettings === null) return null;
        if ($feeSettings === []) return [];
        $canonical = [];
        foreach ($feeSettings as $fee) {
            if (!is_array($fee)) continue;
            $cf = \App\Helper\CanonicalFee::fromHostaway($fee);
            if ($cf) $canonical[] = $cf;
        }
        return $canonical;
    }

    /**
     * Normalize Hostaway listingAmenities into a flat list of display names.
     *
     * Hostaway returns amenities in several shapes depending on endpoint:
     *   - listingAmenities via includeRelations: [{amenityName:"Pool", isAvailable:1, ...}]
     *   - /v1/listings/{id}/amenities:           [{id:1, name:"Pool"}, ...] or ["Pool", ...]
     *   - plain strings (rare):                  ["Pool", "Wifi"]
     */
    protected function normalizeAmenities($raw): array
    {
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $a) {
            if (is_string($a) && $a !== '') {
                $out[] = $a;
            } elseif (is_array($a)) {
                // Skip unavailable amenities if the flag is present
                if (array_key_exists('isAvailable', $a) && !$a['isAvailable']) continue;
                $name = $a['amenityName'] ?? $a['name'] ?? ($a['amenity']['name'] ?? ($a['title'] ?? null));
                if ($name && is_string($name)) $out[] = $name;
            }
        }
        return array_values(array_unique(array_filter($out)));
    }

    public function parseWebhook(array $payload): array
    {
        // Parse a standard Hostaway webhook callback body
        $data = $payload['result'] ?? $payload;

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
