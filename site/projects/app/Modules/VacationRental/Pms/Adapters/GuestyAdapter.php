<?php

namespace App\Modules\VacationRental\Pms\Adapters;

use App\Modules\VacationRental\Pms\PmsAdapter;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GuestyAdapter implements PmsAdapter
{
    protected Client $client;
    protected ?string $apiKey = null; // Used as client_secret in Open API v1
    protected ?string $clientId = null; // Used as client_id in Open API v1
    protected bool $testMode = false;

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

        if ($client !== null) {
            $this->testMode = true;
            $this->client   = $client;
        } else {
            $this->client = new Client([
                'base_uri'        => 'https://open-api.guesty.com/',
                'timeout'         => 15,
                'connect_timeout' => 8,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);
        }
    }

    public function getName(): string
    {
        return 'guesty';
    }

    /**
     * Retrieve OAuth2 token for Guesty Open API.
     *
     * Uses a mutex lock to prevent thundering herd: when the cache is empty
     * and many requests arrive simultaneously (e.g. page with multiple Guesty
     * properties), only ONE request hits the token endpoint. The others wait
     * up to 10 seconds and then read the cached result. Without this, every
     * concurrent request hits oauth2/token at once → Guesty returns 429 on
     * all but the first → nothing gets cached → loop repeats forever.
     */
    protected function getAccessToken(): ?string
    {
        if (empty($this->apiKey) || empty($this->clientId)) {
            return null;
        }

        $cacheKey    = 'guesty_access_token_'  . md5($this->apiKey);
        $blockedKey  = 'guesty_token_blocked_' . md5($this->apiKey);

        // Fast path — token already cached, no lock needed.
        $cached = Cache::get($cacheKey);
        if ($cached) return $cached;

        // If a recent 429 was received, skip the endpoint entirely until the
        // block window expires. This prevents pms:sync-reviews (or any loop)
        // from hammering the token endpoint for every property in the batch.
        if (Cache::get($blockedKey)) {
            Log::channel('lodgify')->warning('Guesty token blocked (rate-limited) — skipping endpoint call');
            return null;
        }

        // Slow path — acquire a lock so only ONE process fetches the token.
        $lock = Cache::lock('guesty_token_fetch_' . md5($this->apiKey), 30);
        try {
            $lock->block(10); // wait up to 10s for the lock

            // Re-check after acquiring lock.
            $cached = Cache::get($cacheKey);
            if ($cached) return $cached;
            if (Cache::get($blockedKey)) return null;

            // Single attempt — no retries. If Guesty rate-limits us, cache the
            // blocked state for 5 minutes so the whole batch fails fast instead
            // of each item independently sleeping and retrying.
            $token     = null;
            $expiresIn = 86400;
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
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $status = $e->getResponse()?->getStatusCode();
                if ($status === 429) {
                    // Block for 5 minutes — any call within this window returns null immediately.
                    Cache::put($blockedKey, true, 300);
                    Log::channel('lodgify')->warning('Guesty token 429 — blocked for 5 minutes. Run guesty:warm-token to recover early.');
                    return null;
                }
                throw $e;
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
        if ($this->testMode) {
            // Mock client injected — bypass OAuth entirely
        } else {
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
            Log::channel('lodgify')->error("Guesty API Error: {$method} {$uri}", ['error' => $this->safeLogBody($body)]);
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

    public function listProperties(array $options = []): array
    {
        $limit = 100;
        $skip = 0;
        $allItems = [];
        $hasMore = true;

        // Incremental sync: only fetch listings updated since the given timestamp.
        // Guesty supports updatedAt[gte] (ISO 8601). Subtract 5 min as a safety buffer.
        $updatedSince = $options['updated_since'] ?? null;
        $updatedSinceParam = null;
        if ($updatedSince) {
            $dt = \Carbon\Carbon::parse($updatedSince)->subMinutes(5);
            $updatedSinceParam = $dt->toIso8601ZuluString();
        }

        while ($hasMore) {
            $query = ['limit' => $limit, 'skip' => $skip];
            if ($updatedSinceParam) {
                $query['updatedAt[gte]'] = $updatedSinceParam;
            }
            $res = $this->request('GET', 'v1/listings', [
                'query' => $query
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
        $adults   = (int)($input['adults']   ?? 1);
        $children = (int)($input['children'] ?? 0);
        $infants  = (int)($input['infants']  ?? 0);
        $pets     = (int)($input['pets']     ?? 0);

        $payload = [
            'listingId'             => $input['property_id'] ?? '',
            'checkInDateLocalized'  => $input['checkin'] ?? '',
            'checkOutDateLocalized' => $input['checkout'] ?? '',
            // guestsCount = total occupants (Guesty requires this top-level field)
            'guestsCount'           => $adults + $children,
            // numberOfGuests breakdown — drives AFE extra-person fees, PET fees,
            // and child rates. Without the per-type counts Guesty cannot calculate
            // pet fees or extra-person charges correctly.
            'numberOfGuests'        => [
                'numberOfAdults'   => $adults,
                'numberOfChildren' => $children,
                'numberOfInfants'  => $infants,
                'numberOfPets'     => $pets,
            ],
            'ignoreCalendar' => false,
            'ignoreTerms'    => false,
            'ignoreBlocks'   => false,
            // mergeAccommodationFarePriceComponents is sent as a query param, not body
            'source'         => 'website',
        ];

        // Apply coupon/promo code if provided — Guesty field is couponCode
        $promoCode = trim($input['promo_code'] ?? $input['coupon'] ?? '');
        if ($promoCode !== '') {
            $payload['couponCode'] = $promoCode;
        }

        // Query parameters for Guesty v1/quotes.
        // includePaymentsTemplate: include paymentWorkflow for installment schedules.
        // mergeAccommodationFarePriceComponents: false = keep accommodation as a
        //   single line item rather than splitting by night (cleaner normalizer input).
        // NOTE: endpoint path is v1/quotes (v1/ prefix is correct for Guesty Open API).
        // Additional-fees listing endpoint is /additional-fees/listing/{id} (no v1/ prefix)
        // per Guesty API docs — that is a different resource group than v1/.
        $queryParams = 'includePaymentsTemplate=true&mergeAccommodationFarePriceComponents=false';
        $res = $this->request('POST', "v1/quotes?{$queryParams}", ['json' => $payload]);
        \Log::channel('lodgify')->info('Guesty getRawQuote response', [
            'payload'      => $payload,
            'status'       => $res['status'] ?? null,
            'data_keys'    => isset($res['data']) ? array_keys($res['data']) : null,
            'error'        => $res['error'] ?? null,
            'money_keys'   => isset($res['data']['money']) ? array_keys($res['data']['money']) : null,
            'fare_keys'    => isset($res['data']['fare'])  ? array_keys($res['data']['fare'])  : null,
            'coupons'      => $res['data']['coupons'] ?? null,  // debug coupon apply result
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
        $infants  = (int)($input['infants']  ?? 0); // fix #9: was hardcoded to 0
        $pets     = (int)($input['pets']     ?? 0);

        // Build fee line items from the raw_quote accepted by the guest (fix #9).
        // Guesty's money.otherFees array carries each non-accommodation, non-tax line
        // item from the previously issued quote so the reservation preserves the exact
        // fee breakdown the guest was shown and accepted.
        //
        // Source: raw_quote['data']['result']['rateSummary']['fees'] (or similar path).
        // We pull from the normalized line_items because the raw shape varies by Guesty
        // API version — normalizer already flattened it.
        $otherFees  = [];
        $rawQuote   = $input['raw_quote'] ?? null;
        if (!empty($rawQuote)) {
            // Try to extract fee line items from common Guesty response paths
            $rateSummary = $rawQuote['data']['result']['rateSummary']
                        ?? $rawQuote['data']['rateSummary']
                        ?? null;
            if ($rateSummary) {
                foreach ((array)($rateSummary['fees'] ?? []) as $fee) {
                    $name   = $fee['name'] ?? $fee['title'] ?? null;
                    $amount = (float)($fee['amount'] ?? $fee['value'] ?? 0);
                    if ($name && $amount > 0) {
                        $otherFees[] = ['name' => $name, 'amount' => $amount];
                    }
                }
            }
        }

        // Guesty Open API v3 reservation schema
        $payload = [
            'listingId'             => $input['property_id']  ?? '',
            'checkInDateLocalized'  => $input['checkin']      ?? '',
            'checkOutDateLocalized' => $input['checkout']     ?? '',
            'guestsCount'           => $adults + $children,
            'numberOfGuests'        => [
                'numberOfAdults'   => $adults,
                'numberOfChildren' => $children,
                'numberOfInfants'  => $infants,  // fix #9: use real infant count
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
                // gross_amount = accommodation-only (nightly rates, no tax/fees/deposit)
                // total_amount = full total; fall back to it if gross_amount not available
                'fareAccommodation' => (float)($input['gross_amount'] ?: $input['total_amount'] ?? 0),
                'totalTaxes'        => (float)($input['tax'] ?? 0),
            ],
        ];

        // Attach optional fee breakdown to money payload when fees are present (fix #9).
        // Guesty stores these as money.otherFees[]. If the account does not support
        // this field, Guesty will ignore it — the reservation total still reflects the
        // fareAccommodation amount which already includes fees from the accepted quote.
        if (!empty($otherFees)) {
            $payload['money']['otherFees'] = $otherFees;
        }

        \Log::channel('lodgify')->info('GuestyAdapter::createReservation: payload', [
            'listing_id'   => $payload['listingId'],
            'checkin'      => $payload['checkInDateLocalized'],
            'guests'       => $payload['numberOfGuests'],
            'fare_accom'   => $payload['money']['fareAccommodation'],
            'taxes'        => $payload['money']['totalTaxes'],
            'other_fees'   => $otherFees,
        ]);

        $response = $this->request('POST', 'v1/reservations-v3', ['json' => $payload]);

        // Fix #6: re-fetch the created reservation to verify total and fee breakdown
        // before the caller proceeds to charge.  Guesty may silently ignore unsupported
        // money fields — the GET confirms the reservation carries the expected amounts.
        $httpStatus = $response['status'] ?? 0;
        if ($httpStatus === 200 || $httpStatus === 201) {
            $reservationId = $response['data']['_id'] ?? $response['data']['id'] ?? null;
            if ($reservationId) {
                $verify = $this->request('GET', "v1/reservations/{$reservationId}");
                $verifyMoney = $verify['data']['money'] ?? null;
                \Log::channel('lodgify')->info('GuestyAdapter::createReservation verification', [
                    'reservation_id'  => $reservationId,
                    'verify_status'   => $verify['status'] ?? null,
                    'money_keys'      => $verifyMoney ? array_keys($verifyMoney) : null,
                    'fare_total'      => $verify['data']['fare']['total'] ?? null,
                    'invoice_items'   => $verifyMoney['invoiceItems'] ?? null,
                ]);
                // Attach verification data so PmsBookingService / StripeController
                // can confirm amounts before recording payment to PMS.
                $response['_verification'] = [
                    'money'        => $verifyMoney,
                    'fare_total'   => $verify['data']['fare']['total'] ?? null,
                    'invoice_items'=> $verifyMoney['invoiceItems'] ?? [],
                ];
            }
        }

        return $response;
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
     * Record a Stripe payment in Guesty's ledger.
     * Endpoint: POST /v1/reservations/{reservationId}/payments
     *
     * Uses BANK_TRANSFER — an external, non-integrated method that will not trigger
     * Guesty to initiate a second charge. paidAt timestamps when Stripe charged.
     * Idempotency-Key scoped to the Stripe PI prevents duplicate rows on retry.
     *
     * @param string $stripeIntentId Stripe PaymentIntent or charge ID
     * Returns: ['ok', 'payment_id', 'status', 'data', 'error']
     */
    public function recordPayment(string $bookingId, float $amount, string $currency, string $stripeIntentId = '', bool $isRetry = false): array
    {
        $idempotencyKey = 'guesty-pay-' . md5($bookingId . '-' . ($stripeIntentId ?: $amount));
        $note           = 'External website Stripe payment'
                          . ($stripeIntentId ? "; PaymentIntent {$stripeIntentId}" : '');

        $res = $this->request('POST', "v1/reservations/{$bookingId}/payments", [
            'json'    => [
                'paymentMethod' => ['method' => 'BANK_TRANSFER'],
                'amount'        => $amount,
                'paidAt'        => now()->toIso8601ZuluString(),
                'note'          => $note,
            ],
            'headers' => ['Idempotency-Key' => $idempotencyKey],
        ]);

        $ok        = ($res['status'] ?? 0) === 200 || ($res['status'] ?? 0) === 201;
        $paymentId = $res['data']['_id'] ?? $res['data']['id'] ?? null;

        return [
            'ok'         => $ok,
            'payment_id' => $paymentId,
            'status'     => $res['status'] ?? 0,
            'data'       => $res['data'] ?? [],
            'error'      => $res['error'] ?? null,
        ];
    }

    /**
     * Record a Stripe refund as a negative payment in Guesty's ledger.
     * Same endpoint as recordPayment but with a negative amount.
     * Spec: use the same BANK_TRANSFER method; include Stripe refund ID in note.
     *
     * @param string $stripeRefundId  Stripe refund ID (re_xxx)
     * @param string $stripeIntentId  Original PaymentIntent for cross-reference
     */
    public function recordRefund(string $reservationId, float $amount, string $currency, string $stripeRefundId = '', string $stripeIntentId = ''): array
    {
        $idempotencyKey = 'guesty-refund-' . md5($reservationId . '-' . ($stripeRefundId ?: $amount));
        $note           = 'External Stripe refund via website'
                          . ($stripeRefundId ? "; Refund {$stripeRefundId}"              : '')
                          . ($stripeIntentId ? " for PaymentIntent {$stripeIntentId}" : '');

        $res = $this->request('POST', "v1/reservations/{$reservationId}/payments", [
            'json'    => [
                'paymentMethod' => ['method' => 'BANK_TRANSFER'],
                'amount'        => -abs($amount),
                'paidAt'        => now()->toIso8601ZuluString(),
                'note'          => $note,
            ],
            'headers' => ['Idempotency-Key' => $idempotencyKey],
        ]);

        $ok        = ($res['status'] ?? 0) === 200 || ($res['status'] ?? 0) === 201;
        $paymentId = $res['data']['_id'] ?? $res['data']['id'] ?? null;

        return [
            'ok'         => $ok,
            'payment_id' => $paymentId,
            'status'     => $res['status'] ?? 0,
            'data'       => $res['data'] ?? [],
            'error'      => $res['error'] ?? null,
        ];
    }

    public function getPropertyAddons(string $propertyId, array $query = []): array
    {
        return ['status' => 200, 'data' => []];
    }

    /**
     * Register a webhook with Guesty.
     * POST /v1/webhooks
     * Creates or updates the single webhook registration for this callback URL.
     * Docs: https://open-api-docs.guesty.com/docs/webhooks
     */
    public function registerWebhook(string $url, array $events): array
    {
        if (empty($events)) {
            $events = ['reservation.new', 'reservation.updated', 'listing.calendar.updated', 'calendar.updated.v2'];
        }

        sort($events);
        $listRes = $this->request('GET', 'v1/webhooks');
        $webhooks = $listRes['data']['results']
            ?? $listRes['data']['webhooks']
            ?? $listRes['data']
            ?? [];
        if (!is_array($webhooks)) {
            $webhooks = [];
        }

        $exact = collect($webhooks)->first(
            fn ($webhook) => hash_equals((string) ($webhook['url'] ?? ''), $url)
        );
        $logical = $exact ? null : collect($webhooks)->first(
            fn ($webhook) => $this->sameWebhookEndpoint((string) ($webhook['url'] ?? ''), $url)
        );
        $existing = $exact ?? $logical;
        $existingEvents = $existing['events'] ?? [];
        sort($existingEvents);

        if ($exact && $existingEvents === $events) {
            $res = ['status' => 200, 'data' => $existing];
        } elseif ($exact) {
            $webhookId = $exact['_id'] ?? $exact['id'] ?? null;
            $res = $this->request('PUT', "v1/webhooks/{$webhookId}", [
                'json' => ['url' => $url, 'events' => $events],
            ]);
        } else {
            $res = $this->request('POST', 'v1/webhooks', [
                'json' => ['url' => $url, 'events' => $events],
            ]);

            // Guesty rejects changing a registered callback URL. Token rotation
            // therefore requires create-first replacement followed by cleanup.
            if ($logical && in_array($res['status'] ?? 0, [200, 201], true)) {
                $oldWebhookId = $logical['_id'] ?? $logical['id'] ?? null;
                if ($oldWebhookId) {
                    $this->request('DELETE', "v1/webhooks/{$oldWebhookId}");
                }
            }
        }

        $secretRes = $this->request('GET', 'v1/webhooks-v2/secret', [
            'query' => ['url' => $url],
        ]);
        $secret = $secretRes['data']['secret']
            ?? $secretRes['data']['signingSecret']
            ?? $secretRes['data']['key']
            ?? (is_string($secretRes['data'] ?? null) ? $secretRes['data'] : null);

        $ok  = in_array($res['status'] ?? 0, [200, 201]);
        $err = $res['error'] ?? ($res['data']['message'] ?? 'Guesty webhook registration failed');
        return [
            'ok'         => $ok,
            'webhook_id' => $res['data']['_id'] ?? $res['data']['id'] ?? $existing['_id'] ?? $existing['id'] ?? null,
            'existing'   => (bool) $existing,
            'signing_secret' => $secret,
            'secret_error' => $secret ? null : ($secretRes['error'] ?? 'Guesty signing secret was not returned'),
            'error'      => $ok ? null : (is_array($err) ? json_encode($err) : $err),
            'raw'        => $res,
        ];
    }

    private function sameWebhookEndpoint(string $left, string $right): bool
    {
        $normalize = static function (string $url): array {
            $parts = parse_url($url);
            $path = rtrim((string) ($parts['path'] ?? ''), '/');
            $path = preg_replace('#^(/webhooks/guesty)(?:/[^/]+)?$#i', '$1', $path) ?? $path;
            return [
                strtolower((string) ($parts['scheme'] ?? '')),
                strtolower((string) ($parts['host'] ?? '')),
                (int) ($parts['port'] ?? 0),
                $path,
            ];
        };

        return $normalize($left) === $normalize($right);
    }

    /**
     * GET /v1/reviews?listingId={id}&limit=100
     * listingId filter is supported per Guesty Open API docs.
     * Response shape: {results: [{_id, listingId, channelId, createdAt, contents: {body, reviewerName, rating, title}}]}
     * Rating: 1-10 scale → normalize to 1-5.
     */
    public function getReviews(string $propertyId): array
    {
        $query = ['limit' => 100, 'skip' => 0];
        if ($propertyId !== 'all') {
            $query['listingId'] = $propertyId;
        }

        $res = $this->request('GET', 'v1/reviews', ['query' => $query]);

        if (($res['status'] ?? 0) !== 200) {
            \Log::channel('lodgify')->error('Guesty getReviews failed', [
                'status' => $res['status'] ?? 0,
                'error'  => $res['error'] ?? '',
            ]);
            return [];
        }

        // Guesty v1/reviews response: {data: [...], limit, skip} — key is "data" not "results"
        $reviews = $res['data']['data'] ?? $res['data']['results'] ?? $res['data']['result'] ?? [];
        if (!is_array($reviews)) return [];

        $out = [];
        foreach ($reviews as $r) {
            // Actual Guesty v1/reviews shape (confirmed from API):
            // Channel reviews are in rawReview{} — Airbnb fields
            // rawReview.overall_rating: 1-5 (Airbnb native scale, NOT 1-10)
            // rawReview.public_review:  guest's written review text
            // rawReview.submitted_at:   ISO date string
            // Reviewer name is not returned — only reviewer_id
            $raw     = $r['rawReview'] ?? [];
            $message = $raw['public_review'] ?? '';
            if (empty($message)) continue; // skip reviews with no text

            $score = (int)($raw['overall_rating'] ?? 5);
            $score = max(1, min(5, $score)); // already 1-5

            $stayDate = isset($raw['submitted_at'])
                ? substr($raw['submitted_at'], 0, 10)
                : (isset($r['createdAt']) ? substr($r['createdAt'], 0, 10) : null);

            $out[] = [
                'id'         => (string)($r['_id'] ?? $r['id'] ?? uniqid()),
                'name'       => 'Guest',   // Guesty doesn't return reviewer name in this endpoint
                'score'      => $score,
                'message'    => $message,
                'stay_date'  => $stayDate,
                'end_date'   => null,
                'profile'    => null,
                'listing_id' => (string)($r['listingId'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Fetch optional/additional fees for a Guesty listing.
     *
     * Endpoint: GET /additional-fees/listing/{listingId}
     * Docs: https://open-api-docs.guesty.com/reference/get_additional-fees-listing-listingid
     *
     * Returns: ['ok', 'fees', 'api_blocked', 'error']
     *   api_blocked=true when the token lacks the required scope (HTTP 403).
     *   This is distinct from "API unavailable" — the endpoint EXISTS but
     *   this account/token has not been granted the additional-fees permission.
     */
    public function getAdditionalFees(string $listingId): array
    {
        $res = $this->request('GET', "additional-fees/listing/{$listingId}");
        $status = $res['status'] ?? 0;

        if ($status === 403) {
            Log::channel('lodgify')->warning('Guesty additional-fees: API-blocked (403)', [
                'listing_id' => $listingId,
                'note'       => 'Token lacks additional-fees scope. Contact Guesty support to grant access.',
            ]);
            return ['ok' => false, 'fees' => [], 'api_blocked' => true, 'error' => 'Guesty additional-fees API blocked (403)'];
        }

        if ($status !== 200) {
            Log::channel('lodgify')->warning('Guesty additional-fees: fetch failed', [
                'listing_id' => $listingId,
                'status'     => $status,
            ]);
            return ['ok' => false, 'fees' => [], 'api_blocked' => false, 'error' => 'Guesty additional-fees HTTP ' . $status];
        }

        $fees = $res['data']['additionalFees'] ?? $res['data']['data'] ?? $res['data'] ?? [];
        Log::channel('lodgify')->info('Guesty additional-fees: fetched', [
            'listing_id' => $listingId,
            'count'      => count($fees),
            'first_keys' => !empty($fees[0]) ? array_keys($fees[0]) : [],
        ]);

        return ['ok' => true, 'fees' => $fees, 'api_blocked' => false, 'error' => null];
    }

    // Raw Storage & Frontend Mapping
    public function fetchAndSaveRawData(string $propertyId): void
    {
        $propertyRes = $this->request('GET', "v1/listings/{$propertyId}");
        $propertyRaw = ($propertyRes['status'] ?? 0) === 200 ? ($propertyRes['data'] ?? null) : null;

        // ── Guesty listing additional-fees ────────────────────────────────────
        // Endpoint: GET /additional-fees/listing/{listingId}
        // If HTTP 403: mark as API-blocked — token lacks scope, not unavailable.
        // If HTTP 200: store fees for canonical_fees building in getFrontendData.
        $additionalFees    = [];
        $feesApiBlocked    = false;
        $feesResult        = $this->getAdditionalFees($propertyId);
        $feesFetchOk       = (bool) $feesResult['ok'];
        if ($feesResult['api_blocked']) {
            $feesApiBlocked = true;
        } elseif ($feesResult['ok']) {
            $additionalFees = $feesResult['fees'];
        }
        if ($propertyRaw) {
            $propertyRaw['_additionalFees']    = $additionalFees;
            $propertyRaw['_feesApiBlocked']    = $feesApiBlocked;
            $propertyRaw['_feesFetchOk']       = $feesFetchOk;
        }

        \App\Models\Pms\GuestyData::updateOrCreate(
            ['pms_property_id' => $propertyId],
            [
                'raw_property_json' => $propertyRaw ? json_encode($propertyRaw) : null,
                'raw_units_json' => null,
            ]
        );
    }

    public function getFrontendData(string $propertyId): array
    {
        $data = \App\Models\Pms\GuestyData::where('pms_property_id', $propertyId)->first();
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

        // Guesty amenities can come in two shapes depending on API version / account:
        //   1. Flat string array (current Open API): ['Air conditioning', 'Pool', ...]
        //   2. Boolean dict (legacy):                {"wirelessInternet": true, "pool": false, ...}
        $amenRaw   = isset($listing['amenities']) && is_array($listing['amenities']) ? $listing['amenities'] : [];
        $amenities = [];
        foreach ($amenRaw as $key => $val) {
            if (is_int($key)) {
                // Shape 1: flat string array — use value directly
                if (is_string($val) && $val !== '') {
                    $amenities[] = $val;
                }
            } else {
                // Shape 2: boolean dict — skip falsy, humanize key
                if (!$val) continue;
                $label = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', (string)$key);
                $label = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $label);
                $amenities[] = ucwords(strtolower(trim($label)));
            }
        }

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
            'key_facts' => \App\Helper\ListingFacts::build([
                'check_in'  => $listing['defaultCheckInTime'] ?? null,
                'check_out' => $listing['defaultCheckOutTime'] ?? null,
                'min_stay'  => $listing['terms']['minNights'] ?? $listing['minNights'] ?? null,
                'max_stay'  => $listing['terms']['maxNights'] ?? $listing['maxNights'] ?? null,
                'pets_allowed' => $hr['petsAllowed'] ?? null,
                'smoking_allowed' => $hr['smokingAllowed'] ?? null,
                'events_allowed' => $hr['partiesAllowed'] ?? $hr['eventsAllowed'] ?? null,
                'suitable_for_children' => $hr['suitableForChildren'] ?? null,
            ], $amenities, $houseRulesText),
            'pricing_normalized' => \App\Helper\ListingPricing::normalize(
                $listing['prices'] ?? [],
                [],
                'guesty'
            ),
            // Guesty canonical fees: built from additional-fees API if not blocked.
            // Returns null when API is blocked (403) — block message stored separately.
            // When null, QuoteController will not show optional fee controls.
            'canonical_fees'     => $this->buildGuestyCanonicalFees(
                $listing['_additionalFees'] ?? [],
                (bool)($listing['_feesApiBlocked'] ?? false),
                (bool)($listing['_feesFetchOk'] ?? false)
            ),
            'max_pets' => null, // Guesty does not expose a per-listing pet limit
        ];
    }

    /**
     * Build canonical fee definitions from Guesty additional-fees response.
     *
     * Returns null when:
     *   - API was blocked (403) — token lacks scope → mark as api_blocked, not unavailable
     *   - No fees returned
     *
     * Guesty additional-fee response shape (from docs, not yet verified live):
     *   [{_id, name, type, value, valueType, frequency, ...}]
     * STATUS: Endpoint exists but access may be blocked on this account.
     *         Response shape not yet verified against a live response.
     */
    private function buildGuestyCanonicalFees(array $fees, bool $apiBlocked, bool $fetchOk): ?array
    {
        if ($apiBlocked) {
            // Return a marker so QuoteController knows why there are no fees
            return [['_api_blocked' => true, '_note' => 'Guesty additional-fees API blocked (403). Contact Guesty support to grant additional-fees scope.']];
        }
        if (!$fetchOk) return null;
        if (empty($fees)) return [];

        $canonical = [];
        foreach ($fees as $fee) {
            if (!is_array($fee)) continue;
            $feeId = (string)($fee['_id'] ?? $fee['id'] ?? '');
            if (!$feeId) continue;

            // Guesty fee shape (unverified — log first_keys in getAdditionalFees)
            $valueType = strtolower($fee['valueType'] ?? 'flat');
            $frequency = strtolower($fee['frequency'] ?? 'per_stay');
            $isPct     = $valueType === 'percentage' || $valueType === 'percent';
            $scope     = match ($frequency) {
                'per_night'              => 'per_night',
                'per_guest'              => 'per_guest',
                'per_guest_per_night'    => 'per_guest_per_night',
                default                  => 'per_stay',
            };

            $canonical[] = [
                'pms_id'               => $feeId,
                'provider_fee_id'       => $feeId,
                'name'                 => $fee['name'] ?? 'Fee',
                'normal_type'          => strtoupper((string)($fee['normalType'] ?? '')),
                'second_identifier'     => strtoupper((string)($fee['secondIdentifier'] ?? $fee['type'] ?? '')),
                'is_mandatory'         => (bool)($fee['mandatory'] ?? false),
                'is_optional'          => !(bool)($fee['mandatory'] ?? false),
                'requires_approval'    => false,
                'is_active'            => true,
                'scope'                => $scope,
                'amount'               => $isPct ? null : (float)($fee['value'] ?? 0),
                'percentage'           => $isPct ? (float)($fee['value'] ?? 0) : null,
                'tax_rate'             => 0,
                'is_quantity_selectable' => false,
                'min_qty'              => 0,
                'max_qty'              => 1,
                'is_per_pet'           => false,
                'is_per_guest'         => in_array($scope, ['per_guest', 'per_guest_per_night']),
                'pms_source'           => 'guesty',
                '_verified'            => false, // shape not yet confirmed live
            ];
        }
        return $canonical;
    }

    public function parseWebhook(array $payload): array
    {
        // Guesty wraps event data under a 'data' key:
        // {"event": "reservation.new", "data": {"_id": "...", "listingId": "...", ...}}
        $data = $payload['reservation'] ?? $payload['data'] ?? $payload;

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
