<?php

namespace App\Modules\VacationRental\Pms\Adapters;

use App\Modules\VacationRental\Pms\PmsAdapter;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class HostfullyAdapter implements PmsAdapter
{
    protected Client $client;
    protected ?string $apiKey = null;
    protected ?string $agencyUid = null;
    /** true when a mock client is injected (tests) — skips API-key guard in request() */
    protected bool $testMode = false;

    public function __construct(?Client $client = null)
    {
        try {
            $this->apiKey = class_exists(\ModelHelper::class) 
                ? (\ModelHelper::getDataFromSetting('hostfully_api_key') ?: config('services.hostfully.api_key', '')) 
                : config('services.hostfully.api_key', '');
            $this->agencyUid = class_exists(\ModelHelper::class) 
                ? (\ModelHelper::getDataFromSetting('hostfully_agency_uid') ?: config('services.hostfully.agency_uid', '')) 
                : config('services.hostfully.agency_uid', '');
        } catch (\Throwable $e) {
            $this->apiKey = config('services.hostfully.api_key', '');
            $this->agencyUid = config('services.hostfully.agency_uid', '');
        }

        if ($client !== null) {
            $this->testMode = true;
            $this->client   = $client;
        } else {
            $this->client = new Client([
                'base_uri' => 'https://api.hostfully.com/',
                'headers' => [
                    'X-HOSTFULLY-APIKEY' => ($this->apiKey ?? ''),
                    'Accept'             => 'application/json',
                    'Content-Type'       => 'application/json',
                ],
            ]);
        }
    }

    public function getName(): string
    {
        return 'hostfully';
    }

    protected function request(string $method, string $uri, array $options = []): array
    {
        if (empty($this->apiKey) && !$this->testMode) {
            return [
                'status' => 401,
                'error'  => 'Missing Hostfully API Key'
            ];
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
            Log::channel('lodgify')->error("Hostfully API Error: {$method} {$uri}", ['error' => $this->safeLogBody($body)]);
            return [
                'status' => $statusCode,
                'error'  => $body
            ];
        }
    }

    /** Fetch a single lead/reservation by PMS booking ID. */

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

    public function getReservation(string $reservationId): array
    {
        return $this->request('GET', "api/v3.3/leads/{$reservationId}");
    }

    public function healthCheck(): array
    {
        $res = $this->request('GET', 'api/v3.3/properties', [
            'query' => [
                'limit' => 1,
                'agencyUid' => $this->agencyUid
            ]
        ]);
        return [
            'ok' => $res['status'] === 200,
            'status' => $res['status']
        ];
    }

    public function listProperties(array $options = []): array
    {
        return $this->request('GET', 'api/v3.3/properties', [
            'query' => [
                'agencyUid' => $this->agencyUid
            ]
        ]);
    }

    public function getProperty(string $propertyId): array
    {
        $res = $this->request('GET', "api/v3.3/properties/{$propertyId}");
        if (($res['status'] ?? 0) === 200 && isset($res['data'])) {
            $hostfullyProp = $res['data']['property'] ?? $res['data'];
            
            $standardized = [
                'name'               => $hostfullyProp['name'] ?? '',
                'latitude'           => $hostfullyProp['address']['latitude'] ?? ($hostfullyProp['latitude'] ?? null),
                'longitude'          => $hostfullyProp['address']['longitude'] ?? ($hostfullyProp['longitude'] ?? null),
                'address'            => $hostfullyProp['address']['address'] ?? ($hostfullyProp['address1'] ?? ''),
                'zip'                => $hostfullyProp['address']['zipCode'] ?? ($hostfullyProp['postalCode'] ?? ''),
                'city'               => $hostfullyProp['address']['city'] ?? ($hostfullyProp['city'] ?? ''),
                'country'            => $hostfullyProp['address']['countryCode'] ?? ($hostfullyProp['countryCode'] ?? ''),
                'image_url'          => $hostfullyProp['pictureLink'] ?? ($hostfullyProp['picture'] ?? ''),
                'price_unit_in_days' => 'true',
                'min_price'          => $hostfullyProp['pricing']['dailyRate'] ?? ($hostfullyProp['baseDailyRate'] ?? 0),
                'max_price'          => $hostfullyProp['pricing']['dailyRate'] ?? ($hostfullyProp['baseDailyRate'] ?? 0),
                'currency'           => $hostfullyProp['pricing']['currency'] ?? ($hostfullyProp['currency'] ?? 'USD'),
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
        
        $res = $this->request('GET', "api/v3.3/properties/{$propertyId}");
        if (($res['status'] ?? 0) === 200 && isset($res['data'])) {
            $listing = $res['data']['property'] ?? $res['data'];
            
            $descRes = $this->request('GET', "api/v3.3/property-descriptions", ['query' => ['propertyUid' => $propertyId]]);
            $description = '';
            if (($descRes['status'] ?? 0) === 200 && !empty($descRes['data'])) {
                // Hostfully usually returns an array of descriptions or a single one
                $description = $descRes['data'][0]['description'] ?? $descRes['data']['description'] ?? '';
            }

            $photoRes = $this->request('GET', "api/v3.3/property-photos", ['query' => ['propertyUid' => $propertyId]]);
            $images = [];
            if (($photoRes['status'] ?? 0) === 200 && !empty($photoRes['data'])) {
                foreach ($photoRes['data'] as $photo) {
                    if (!empty($photo['url'])) {
                        $images[] = $photo['url'];
                    }
                }
            }
            if (empty($images) && !empty($listing['pictureLink'])) {
                $images[] = $listing['pictureLink'];
            }
            
            $standardizedRoom = [
                [
                    'id'               => $propertyId,
                    'name'             => $listing['name'] ?? 'Main Room',
                    'description'      => $description,
                    'max_people'       => $listing['availability']['maxGuests'] ?? $listing['guests'] ?? 2,
                    'bedrooms'         => $listing['bedrooms'] ?? 1,
                    'bathrooms'        => $listing['bathrooms'] ?? 1,
                    'pets_allowed'     => ($listing['pets'] ?? false) ? 1 : 0,
                    'currency'         => $listing['pricing']['currency'] ?? 'USD',
                    'images'           => $images,
                    'amenities'        => [],
                    'house_rules'      => $listing['extraNotes'] ?? '',
                    'cancellation_policy' => $listing['cancellationPolicy'] ?? '',
                    'booking_policy'   => $listing['bookingNotes'] ?? ''
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
        $from       = $input['checkin']     ?? '';
        $to         = $input['checkout']    ?? '';

        // Hostfully property-calendar endpoint:
        // propertiesUids — comma-separated UIDs (single here)
        // from / to      — YYYY-MM-DD date range
        return $this->request('GET', 'api/v3.3/property-calendar', [
            'query' => [
                'propertiesUids' => $propertyId,
                'from'           => $from,
                'to'             => $to,
            ]
        ]);
    }

    /**
     * Returns the full raw Hostfully quote response for use by QuoteNormalizer.
     * Includes per-night breakdown, all fees, optional fees, and security deposit.
     */
    public function getRawQuote(array $input): array
    {
        $payload = [
            'agencyUid'     => $this->agencyUid ?? '',
            'propertyUid'   => $input['property_id'] ?? '',
            'checkInDate'   => $input['checkin'] ?? '',
            'checkOutDate'  => $input['checkout'] ?? '',
            'guests'        => (int)($input['adults'] ?? 1),
            'childrenCount' => (int)($input['children'] ?? 0),
            'petCount'      => (int)($input['pets'] ?? 0),
        ];

        // Apply promo / discount code if provided
        if (!empty($input['promo_code'])) {
            $payload['promoCode'] = $input['promo_code'];
        }

        // Optional fee overrides — when the guest selects optional add-ons (e.g. pool
        // heating, early check-in), re-submit those fee UIDs with quantities so that
        // Hostfully calculates the authoritative price including those fees.
        //
        // Format (Hostfully v3.3 spec — verify against live account before relying on):
        //   overrides.fees.otherFees[]: [{feeUid: "...", quantity: N}]
        //
        // INPUT: optional_services = [{"fee_uid": "...", "qty": 1, ...}, ...]
        //   OR   optional_services = {"hf_<uid>": qty, ...}  (old JS map format)
        //
        // STATUS: The exact override field names (feeUid vs uid, quantity vs qty) have
        // NOT been verified against a live Hostfully account with optional fees enabled.
        // Until verified, this sends the overrides and logs what was sent. If Hostfully
        // ignores unknown fields, the quote will still return the base price correctly.
        // ── Optional fee overrides ────────────────────────────────────────────
        // Uses FeeValidator::buildHostfullyOverrides which produces the documented
        // v3.3 override format: [{feeUid, enabled:true, quantity}]
        //
        // VERIFICATION STATUS:
        //   feeUid + enabled + quantity is the documented Hostfully v3.3 format.
        //   Additional fields (scope, pricing) only needed to override the fee's
        //   configured values — not required when using default property pricing.
        //   Live test with a property that has optional fees recommended before
        //   relying on this in production.
        $confirmedServices = $input['optional_services'] ?? [];
        if (!empty($confirmedServices)) {
            $canonicalFees = json_decode(
                \App\Models\Pms\PmsRoom::where('pms_property_id', ($input['property_id'] ?? ''))
                    ->where('pms_source', 'hostfully')
                    ->value('optional_fees_json') ?? '[]',
                true
            ) ?: [];

            $overrideFees = \App\Helper\FeeValidator::buildHostfullyOverrides(
                $confirmedServices,
                $canonicalFees
            );

            if (!empty($overrideFees)) {
                $payload['overrides'] = ['fees' => ['otherFees' => $overrideFees]];
                \Log::channel('lodgify')->info('HostfullyAdapter::getRawQuote: optional fee overrides', [
                    'property_uid'  => $input['property_id'] ?? '',
                    'override_uids' => array_column($overrideFees, 'feeUid'),
                    'payload_sample' => $overrideFees[0] ?? null,
                ]);
            }
        }

        // ── Fix #10: full request/response log for live verification ─────────
        // Log the exact payload sent and the full response. This is the evidence
        // fixture for verifying override field names (feeUid/quantity vs uid/qty)
        // and that the totalAmount in the response reflects the selected fees.
        \Log::channel('lodgify')->info('HostfullyAdapter::getRawQuote: REQUEST', [
            'endpoint'          => 'POST api/v3.3/quotes',
            'property_uid'      => $payload['propertyUid'] ?? null,
            'checkin'           => $payload['checkInDate'] ?? null,
            'checkout'          => $payload['checkOutDate'] ?? null,
            'guests'            => $payload['guests'] ?? null,
            'children'          => $payload['childrenCount'] ?? null,
            'pets'              => $payload['petCount'] ?? null,
            'has_overrides'     => isset($payload['overrides']),
            'override_payload'  => $payload['overrides']['fees']['otherFees'] ?? null,
        ]);

        $res = $this->request('POST', 'api/v3.3/quotes', ['json' => $payload]);

        $q = $res['data']['quote'] ?? $res['data'] ?? [];
        // paymentSchedule is at data level (sibling of 'quote'), not inside quote
        $paymentScheduleLog = $res['data']['paymentSchedule'] ?? $q['paymentSchedule'] ?? 'MISSING';

        // Capture full fee list from response — needed to verify optional fees are priced
        $responseFees = $q['otherFees'] ?? $q['fees'] ?? null;

        \Log::channel('lodgify')->info('HostfullyAdapter::getRawQuote: RESPONSE', [
            'status'                => $res['status'] ?? null,
            'http_status'           => $res['status'] ?? null,
            'data_keys'             => is_array($res['data'] ?? null) ? array_keys($res['data']) : null,
            'quote_keys'            => is_array($q) ? array_keys($q) : null,
            'totalAmount'           => $q['totalAmount'] ?? null,
            'rent_netPrice'         => $q['rent']['netPrice'] ?? null,
            'paymentSchedule'       => $paymentScheduleLog,
            'response_fees'         => $responseFees,  // proves optional fees are included in total
            'override_echo'         => $res['data']['quote']['overrides'] ?? 'NOT_ECHOED',
            'error'                 => $res['error'] ?? null,
        ]);

        return $res;
    }

    public function getQuote(array $input): array
    {
        $propertyId = $input['property_id'] ?? '';
        
        $payload = [
            'agencyUid'     => $this->agencyUid,
            'propertyUid'   => $propertyId,
            'checkInDate'   => $input['checkin'] ?? '',
            'checkOutDate'  => $input['checkout'] ?? '',
            'guests'        => (int)($input['adults'] ?? ($input['people'] ?? 1)),
            'childrenCount' => (int)($input['children'] ?? 0),
            'petCount'      => (int)($input['pets'] ?? 0)
        ];

        $res = $this->request('POST', 'api/v3.3/quotes', ['json' => $payload]);

        if ((($res['status'] ?? 0) === 200 || ($res['status'] ?? 0) === 201) && isset($res['data'])) {
            $calc = $res['data']['quote'] ?? $res['data'];
            
            $standardizedQuote = [
                'subtotal'    => $calc['rent']['netPrice'] ?? $calc['rent']['rentNetPrice'] ?? 0,
                'total'       => $calc['totalAmount'] ?? $calc['totalPrice'] ?? 0,
                'net_amount'  => $calc['totalAmount'] ?? $calc['totalPrice'] ?? 0,
                'vat_amount'  => $calc['rent']['taxAmount'] ?? 0,
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
        $propertyId = $input['property_id'] ?? '';

        // Step 1: Create a Lead in Hostfully
        $leadPayload = [
            'agencyUid'           => $this->agencyUid,
            'propertyUid'         => $propertyId,
            'checkInLocalDate'    => $input['checkin'] ?? '',
            'checkOutLocalDate'   => $input['checkout'] ?? '',
            'adults'              => (int)($input['adults'] ?? 1),
            'children'            => (int)($input['children'] ?? 0),
            'pets'                => (int)($input['pets'] ?? 0),
            'status'              => 'NEW',
            'guestInformation'    => [
                'firstName' => $input['first_name'] ?? '',
                'lastName'  => $input['last_name'] ?? '',
                'email'     => $input['email'] ?? '',
                'phone'     => $input['phone'] ?? '',
            ],
        ];

        // ── Accepted optional fee overrides ───────────────────────────────────
        // The raw_quote from the re-quote (which included the overrides) is passed
        // via PmsBookingService. If it contains the accepted quote's override data,
        // include the same overrides when creating the lead so Hostfully prices the
        // lead at the accepted total.
        //
        // NOTE: Whether Hostfully's lead creation endpoint accepts 'overrides' is
        // NOT yet verified against a live account. It is included here to ensure the
        // lead pricing matches the accepted quote. Log the response to confirm.
        //
        // STATUS: REQUIRES live verification with an account that has optional fees.
        $rawQuote = $input['raw_quote'] ?? null;
        if ($rawQuote) {
            // Prefer pre-built overrides echoed back by Hostfully; fall back to
            // reconstructing from fees.otherFees in the quote response (the common
            // case since Hostfully doesn't always echo the override object).
            $acceptedOverrides = $rawQuote['data']['quote']['overrides'] ?? null;

            if (!$acceptedOverrides) {
                $otherFees = $rawQuote['data']['quote']['fees']['otherFees'] ?? [];
                if (!empty($otherFees)) {
                    // Hostfully matches overrides on the numeric feeId — include it
                    // (feeUid alone is ignored). Keep feeUid only as a reference.
                    $overrideFees = array_values(array_map(function ($f) {
                        $o = ['enabled' => true, 'quantity' => 1];
                        if (isset($f['feeId']))  $o['feeId']  = (int)$f['feeId'];
                        if (!empty($f['feeUid'])) $o['feeUid'] = $f['feeUid'];
                        return $o;
                    }, $otherFees));
                    $acceptedOverrides = ['fees' => ['otherFees' => $overrideFees]];
                }
            }

            if ($acceptedOverrides) {
                $leadPayload['overrides'] = $acceptedOverrides;
                \Log::channel('lodgify')->info('HostfullyAdapter::createReservation: including overrides in lead payload', [
                    'property_uid' => $propertyId,
                    'override_keys' => array_keys($acceptedOverrides),
                ]);
            }
        }

        // ── Fix #10: full request/response log for lead creation live test ────
        \Log::channel('lodgify')->info('HostfullyAdapter::createReservation: LEAD REQUEST', [
            'endpoint'          => 'POST api/v3.3/leads',
            'property_uid'      => $leadPayload['propertyUid'] ?? null,
            'checkin'           => $leadPayload['checkInLocalDate'] ?? null,
            'checkout'          => $leadPayload['checkOutLocalDate'] ?? null,
            'adults'            => $leadPayload['adults'] ?? null,
            'children'          => $leadPayload['children'] ?? null,
            'pets'              => $leadPayload['pets'] ?? null,
            'has_overrides'     => isset($leadPayload['overrides']),
            'override_payload'  => $leadPayload['overrides']['fees']['otherFees'] ?? null,
        ]);

        $res = $this->request('POST', 'api/v3.3/leads', ['json' => $leadPayload]);

        \Log::channel('lodgify')->info('HostfullyAdapter::createReservation: LEAD RESPONSE', [
            'http_status'    => $res['status'] ?? null,
            'data_keys'      => is_array($res['data'] ?? null) ? array_keys($res['data']) : null,
            'lead_uid'       => $res['data']['lead']['uid'] ?? $res['data']['uid'] ?? null,
            'lead_total'     => $res['data']['lead']['totalPrice'] ?? $res['data']['totalPrice'] ?? null,
            'lead_fees'      => $res['data']['lead']['fees'] ?? null,  // prove fees are in lead
            'error'          => $res['error'] ?? null,
        ]);

        if ((($res['status'] ?? 0) === 200 || ($res['status'] ?? 0) === 201) && (isset($res['data']['uid']) || isset($res['data']['lead']['uid']))) {
            $leadUid = $res['data']['lead']['uid'] ?? $res['data']['uid'];

            // Step 2: Mark the lead as booked (confirming reservation)
            $confirmRes = $this->request('POST', "api/v3.3/leads/{$leadUid}/mark-as-booked");

            \Log::channel('lodgify')->info('HostfullyAdapter::createReservation: MARK-AS-BOOKED RESPONSE', [
                'lead_uid'    => $leadUid,
                'http_status' => $confirmRes['status'] ?? null,
                'lead_status' => $confirmRes['data']['lead']['status'] ?? null,
                'lead_total'  => $confirmRes['data']['lead']['totalPrice'] ?? null,
                'error'       => $confirmRes['error'] ?? null,
            ]);

            if (($confirmRes['status'] ?? 0) === 200 || ($confirmRes['status'] ?? 0) === 201) {
                return [
                    'status' => 200,
                    'data'   => [
                        'uid'  => $leadUid,
                        'lead' => $confirmRes['data']['lead'] ?? $confirmRes['data']
                    ]
                ];
            }
            return $confirmRes;
        }

        return $res;
    }

    public function updateReservation(array $input): array
    {
        $leadUid = $input['reservation_id'] ?? '';
        if (!$leadUid) return ['status' => 400, 'error' => 'Lead UID required'];

        // Hostfully uses alteration endpoint to update active leads
        $payload = array_intersect_key($input, array_flip([
            'checkInLocalDate', 'checkOutLocalDate', 'adults', 'children', 'pets'
        ]));

        return $this->request('POST', "api/v3.3/leads/{$leadUid}/alteration", ['json' => $payload]);
    }

    public function cancelReservation(array $input): array
    {
        $leadUid = $input['reservation_id'] ?? '';
        if (!$leadUid) return ['status' => 400, 'error' => 'Lead UID required'];

        $action = $input['action'] ?? 'cancel';
        if ($action === 'delete') {
            return $this->request('DELETE', "api/v3.3/leads/{$leadUid}");
        }

        // Hostfully cancel lead endpoint
        return $this->request('POST', "api/v3.3/leads/{$leadUid}/cancel");
    }

    public function listReservations(array $input = []): array
    {
        return $this->request('GET', 'api/v3.3/leads', [
            'query' => [
                'agencyUid' => $this->agencyUid,
                'limit'     => $input['limit'] ?? 50,
                'offset'    => $input['offset'] ?? 0
            ]
        ]);
    }

    public function getPaymentLink(string $bookingId): array
    {
        return ['status' => 200, 'data' => ['url' => '']];
    }

    /**
     * Record a Stripe payment in Hostfully's transaction ledger.
     *
     * Spec: POST /api/v3.3/transactions
     * - manual:        false  (processor/external transaction, not a manual entry)
     * - type:          SALE
     * - status:        SUCCESS
     * - transactionId: Stripe PaymentIntent ID — serves as idempotency key;
     *                  Hostfully will reject duplicates with the same transactionId.
     * - orderUid:      fetched via GET /api/v3.3/orders?leadUid={leadUid}
     *
     * Returns: ['ok', 'transaction_uid', 'order_uid', 'status', 'data', 'error']
     *
     * @param string $bookingId      Hostfully lead UID (pms_booking_id)
     * @param float  $amount         Charged amount in major currency units
     * @param string $currency       ISO currency code (unused by Hostfully transactions endpoint but kept for parity)
     * @param string $stripeIntentId Stripe PaymentIntent ID
     */
    public function recordPayment(string $bookingId, float $amount, string $currency, string $stripeIntentId = '', bool $isRetry = false): array
    {
        // Step 1: Resolve order UID — required to post a transaction.
        // GET /api/v3.3/orders?leadUid={leadUid} is the authoritative source.
        // GET /api/v3.3/orders?leadUid={leadUid} — the authoritative source of orderUid.
        // Do NOT fall back to the lead's own UID. leadUid ≠ orderUid; using the wrong
        // identifier would silently attach a transaction to the wrong order or fail with
        // a 404. If the orders endpoint fails, return an error so the caller can queue a retry.
        $ordersRes = $this->request('GET', "api/v3.3/orders?leadUid={$bookingId}");
        if (($ordersRes['status'] ?? 0) !== 200) {
            Log::channel('lodgify')->warning('HostfullyAdapter::recordPayment: orders endpoint failed', [
                'lead_uid' => $bookingId,
                'http'     => $ordersRes['status'] ?? 0,
                'error'    => $ordersRes['error'] ?? null,
            ]);
            return [
                'ok'    => false,
                'error' => 'Hostfully /orders endpoint returned HTTP ' . ($ordersRes['status'] ?? 0)
                           . ' for leadUid ' . $bookingId . '; cannot resolve orderUid — queue a retry.',
            ];
        }
        $orders   = $ordersRes['data']['orders'] ?? (isset($ordersRes['data'][0]) ? $ordersRes['data'] : []);
        $orderUid = $orders[0]['uid'] ?? $orders[0]['orderUid'] ?? null;

        if (!$orderUid) {
            Log::channel('lodgify')->warning('HostfullyAdapter::recordPayment: no orderUid found', [
                'lead_uid' => $bookingId,
            ]);
            return ['ok' => false, 'error' => 'No orderUid found for Hostfully lead — cannot record payment'];
        }

        // Step 2: POST /api/v3.3/transactions
        // transactionId = Stripe PI acts as the idempotency key — Hostfully rejects duplicate transactionIds.
        $payload = [
            'manual'        => false,
            'type'          => 'SALE',
            'status'        => 'SUCCESS',
            'amount'        => $amount,
            'orderUid'      => $orderUid,
            'notes'         => 'Website Stripe payment'
                               . ($stripeIntentId ? "; PaymentIntent {$stripeIntentId}" : ''),
        ];
        if ($stripeIntentId) {
            $payload['transactionId'] = $stripeIntentId;
        }

        $res = $this->request('POST', 'api/v3.3/transactions', ['json' => $payload]);
        $ok  = ($res['status'] ?? 0) === 200 || ($res['status'] ?? 0) === 201;

        // Hostfully returns the new transaction under various keys depending on API version
        $txnUid = $res['data']['uid']
                  ?? $res['data']['transactionUid']
                  ?? $res['data']['transaction']['uid']
                  ?? null;

        if ($ok && empty($txnUid)) {
            Log::channel('lodgify')->warning('HostfullyAdapter::recordPayment: ok but no transaction UID', [
                'lead_uid'  => $bookingId,
                'order_uid' => $orderUid,
            ]);
        }

        return [
            'ok'              => $ok,
            'transaction_uid' => $txnUid,
            'order_uid'       => $orderUid,
            'status'          => $res['status'] ?? 0,
            'data'            => $res['data'] ?? [],
            'error'           => $res['error'] ?? null,
        ];
    }

    /**
     * Record a Stripe refund in Hostfully's transaction ledger.
     * Endpoint: POST /api/v3.3/transactions
     *
     * Spec:
     * - type: REFUND
     * - referenceTransactionId: the Hostfully transaction UID of the original SALE
     *   (stored as pms_payment_id on the booking_request — NOT the Stripe PI).
     * - transactionId: Stripe refund ID (re_xxx) — idempotency key.
     *
     * @param string $orderUid               Hostfully order UID (pms_order_uid on booking)
     * @param float  $amount                 Refund amount (positive; Hostfully treats REFUND type as negative internally)
     * @param string $stripeRefundId         Stripe refund ID (re_xxx)
     * @param string $originalTransactionUid Hostfully transaction UID of the original SALE (pms_payment_id)
     */
    public function recordRefund(string $orderUid, float $amount, string $stripeRefundId = '', string $originalTransactionUid = ''): array
    {
        $payload = [
            'manual'        => false,
            'type'          => 'REFUND',
            'status'        => 'SUCCESS',
            'amount'        => $amount,
            'orderUid'      => $orderUid,
            'notes'         => 'Website Stripe refund'
                               . ($stripeRefundId ? "; Refund {$stripeRefundId}" : ''),
        ];
        if ($stripeRefundId) {
            $payload['transactionId'] = $stripeRefundId;
        }
        if ($originalTransactionUid) {
            $payload['referenceTransactionId'] = $originalTransactionUid;
        }

        $res = $this->request('POST', 'api/v3.3/transactions', ['json' => $payload]);
        $ok  = ($res['status'] ?? 0) === 200 || ($res['status'] ?? 0) === 201;
        $txnUid = $res['data']['uid']
                  ?? $res['data']['transactionUid']
                  ?? $res['data']['transaction']['uid']
                  ?? null;

        return [
            'ok'              => $ok,
            'transaction_uid' => $txnUid,
            'status'          => $res['status'] ?? 0,
            'data'            => $res['data'] ?? [],
            'error'           => $res['error'] ?? null,
        ];
    }

    public function getPropertyAddons(string $propertyId, array $query = []): array
    {
        return ['status' => 200, 'data' => []];
    }

    /**
     * Register a webhook with Hostfully.
     * POST /api/v3.3/webhooks
     * Uses one registration per Hostfully event type.
     * Docs: https://dev.hostfully.com/reference/webhooks-1
     */
    public function registerWebhook(string $url, array $events): array
    {
        if (empty($events)) {
            $events = ['NEW_BOOKING', 'BOOKING_UPDATED', 'BOOKING_CANCELLED', 'PROPERTY_AVAILABILITY_UPDATED'];
        }

        $existingRes = $this->request('GET', 'api/v3.3/webhooks', [
            'query' => ['agencyUid' => $this->agencyUid],
        ]);
        $existing = $existingRes['data']['webhooks'] ?? $existingRes['data'] ?? [];
        if (!is_array($existing)) {
            $existing = [];
        }

        $results = [];
        $allOk   = true;

        foreach ($events as $eventType) {
            $match = collect($existing)->first(function ($webhook) use ($eventType, $url) {
                return ($webhook['eventType'] ?? null) === $eventType
                    && rtrim((string) ($webhook['callbackUrl'] ?? ''), '/') === rtrim($url, '/');
            });

            if ($match) {
                $results[$eventType] = [
                    'ok' => true,
                    'webhook_id' => $match['uid'] ?? $match['id'] ?? null,
                    'existing' => true,
                    'error' => null,
                ];
                continue;
            }

            $res = $this->request('POST', 'api/v3.3/webhooks', [
                'json' => [
                    'agencyUid'   => $this->agencyUid,
                    'objectUid'   => $this->agencyUid, // agency-wide: watch all properties
                    'callbackUrl' => $url,
                    'webhookType' => 'POST_JSON',
                    'eventType'   => $eventType,
                ],
            ]);

            $ok  = in_array($res['status'] ?? 0, [200, 201]);
            if (!$ok) $allOk = false;
            $err = $res['error'] ?? ($res['data']['apiErrorMessage'] ?? 'failed');
            $results[$eventType] = [
                'ok'         => $ok,
                'webhook_id' => $res['data']['webhook']['uid'] ?? $res['data']['uid'] ?? null,
                'existing'   => false,
                'error'      => $ok ? null : (is_array($err) ? json_encode($err) : $err),
                'raw'        => $res,
            ];
        }

        return [
            'ok'         => $allOk,
            'webhook_id' => null, // multiple — one per event
            'results'    => $results,
            'error'      => $allOk ? null : 'One or more Hostfully webhook registrations failed',
        ];
    }

    /**
     * Fetch reviews for a property — tries two approaches:
     *  1. GET /api/v3.3/reviews?propertyUid={uid}  — direct property-level reviews
     *  2. Fallback: GET leads for property → GET /api/v3.3/reviews?leadUid={uid} per booking
     *     (needed when the account never used Hostfully's review system directly)
     */
    public function getReviews(string $propertyId): array
    {
        // Approach 1: property-level
        $out = $this->fetchReviewsByProperty($propertyId);
        if (!empty($out)) return $out;

        // Approach 2: lead-level fallback
        return $this->fetchReviewsByLeads($propertyId);
    }

    private function fetchReviewsByProperty(string $propertyId): array
    {
        $res = $this->request('GET', 'api/v3.3/reviews', [
            'query' => [
                'propertyUid' => $propertyId,
                'agencyUid'   => $this->agencyUid,
                'limit'       => 50,
            ],
        ]);

        if (($res['status'] ?? 0) !== 200) {
            \Log::channel('lodgify')->error('Hostfully getReviews (property) failed', [
                'property' => $propertyId,
                'status'   => $res['status'] ?? 0,
                'error'    => $res['error'] ?? '',
            ]);
            return [];
        }

        return $this->parseHostfullyReviews($res['data']['reviews'] ?? []);
    }

    private function fetchReviewsByLeads(string $propertyId): array
    {
        // Get completed leads (bookings) for this property
        $leadsRes = $this->request('GET', 'api/v3.3/leads', [
            'query' => [
                'propertyUid' => $propertyId,
                'agencyUid'   => $this->agencyUid,
                'status'      => 'BOOKED',
                'limit'       => 100,
            ],
        ]);

        $leads = $leadsRes['data']['leads'] ?? [];
        if (empty($leads)) return [];

        $out     = [];
        $seenIds = []; // deduplicate across leads

        foreach ($leads as $lead) {
            $leadUid = $lead['uid'] ?? null;
            if (!$leadUid) continue;

            $res = $this->request('GET', 'api/v3.3/reviews', [
                'query' => [
                    'leadUid'   => $leadUid,
                    'agencyUid' => $this->agencyUid,
                ],
            ]);

            if (($res['status'] ?? 0) !== 200) continue;

            foreach ($this->parseHostfullyReviews($res['data']['reviews'] ?? []) as $review) {
                if (isset($seenIds[$review['id']])) continue;
                $seenIds[$review['id']] = true;
                $out[] = $review;
            }
        }

        if (!empty($out)) {
            \Log::channel('lodgify')->info('Hostfully getReviews via leads', [
                'property' => $propertyId,
                'leads'    => count($leads),
                'found'    => count($out),
            ]);
        }

        return $out;
    }

    private function parseHostfullyReviews(array $reviews): array
    {
        $out = [];
        foreach ($reviews as $r) {
            $message = $r['content'] ?? $r['review'] ?? $r['comment'] ?? '';
            if (empty($message)) continue;

            $score = (int)($r['rating'] ?? 5);
            $score = max(1, min(5, $score));

            $out[] = [
                'id'        => (string)($r['uid'] ?? $r['id'] ?? uniqid()),
                'name'      => $r['author'] ?? $r['guestName'] ?? 'Guest',
                'score'     => $score,
                'message'   => $message,
                'stay_date' => $r['date'] ?? $r['checkInDate'] ?? null,
                'end_date'  => null,
                'profile'   => null,
            ];
        }
        return $out;
    }

    // Raw Storage & Frontend Mapping
    public function fetchAndSaveRawData(string $propertyId): void
    {
        $res = $this->request('GET', "api/v3.3/properties/{$propertyId}");
        $propertyRaw = ($res['status'] ?? 0) === 200 ? ($res['data']['property'] ?? $res['data'] ?? null) : null;

        // Descriptions live on a dedicated v3.3 endpoint and are returned under "propertyDescriptions".
        $descRes = $this->request('GET', "api/v3.3/property-descriptions", ['query' => ['propertyUid' => $propertyId]]);
        $descRaw = ($descRes['status'] ?? 0) === 200
            ? ($descRes['data']['propertyDescriptions'] ?? $descRes['data'] ?? null)
            : null;

        $photoRes = $this->request('GET', "api/v3.3/photos", ['query' => ['propertyUid' => $propertyId]]);
        $photoRaw = ($photoRes['status'] ?? 0) === 200 ? ($photoRes['data']['photos'] ?? null) : null;

        // Amenities are also a dedicated endpoint (returned under "amenities").
        $amenRes = $this->request('GET', "api/v3.3/amenities", ['query' => ['propertyUid' => $propertyId]]);
        $amenRaw = ($amenRes['status'] ?? 0) === 200
            ? ($amenRes['data']['amenities'] ?? $amenRes['data'] ?? null)
            : null;

        // Fees (cleaning, deposit, etc.) for a complete price/display picture.
        $feeRes = $this->request('GET', "api/v3.3/fees", ['query' => ['propertyUid' => $propertyId]]);
        $feeRaw = ($feeRes['status'] ?? 0) === 200
            ? ($feeRes['data']['fees'] ?? $feeRes['data'] ?? null)
            : null;

        // ── Correct each fee's optionality from a live quote ──────────────────
        // Hostfully's /fees endpoint does NOT reliably report `isOptional` (some
        // accounts return null for every fee). The authoritative optionality —
        // exactly what the client configured — is in a QUOTE response under
        // overrides.fees.otherFees[].isOptional, keyed by numeric feeId. Without
        // this, an optional fee would be defaulted to mandatory and FeeValidator
        // would reject the guest's selection before it ever reaches the PMS.
        if (is_array($feeRaw) && !empty($feeRaw)) {
            $optMap = $this->fetchHostfullyFeeOptionality($propertyId);
            if (!empty($optMap)) {
                foreach ($feeRaw as &$f) {
                    $fid = $f['feeId'] ?? null;
                    if ($fid !== null && array_key_exists((int)$fid, $optMap)) {
                        $f['isOptional'] = $optMap[(int)$fid];
                    }
                }
                unset($f);
            }
        }

        $payload = [
            'property' => $propertyRaw,
            'descriptions' => $descRaw,
            'photos' => $photoRaw,
            'amenities' => $amenRaw,
            'fees' => $feeRaw,
            'fees_fetched' => ($feeRes['status'] ?? 0) === 200,
        ];

        \App\Models\Pms\HostfullyData::updateOrCreate(
            ['pms_property_id' => $propertyId],
            [
                'raw_property_json' => json_encode($payload),
                'raw_units_json' => null,
            ]
        );
    }

    /**
     * Derive per-fee optionality from a live quote, keyed by numeric feeId.
     *
     * Hostfully's /api/v3.3/fees endpoint does not reliably return `isOptional`,
     * but a quote's overrides.fees.otherFees[] does — it reflects exactly what the
     * client configured (optional fees come back isOptional=true/enabled=false;
     * mandatory ones isOptional=false). Picks the first bookable date range that
     * satisfies the property's minimum stay so the quote succeeds.
     *
     * @return array<int,bool>  [feeId => isOptional]  (empty on any failure)
     */
    private function fetchHostfullyFeeOptionality(string $propertyId): array
    {
        try {
            $from = date('Y-m-d', strtotime('+30 days'));
            $to   = date('Y-m-d', strtotime('+150 days'));

            $av = $this->getAvailability(['property_id' => $propertyId, 'checkin' => $from, 'checkout' => $to]);
            $entries = [];
            foreach (($av['data']['calendars'] ?? []) as $cal) {
                foreach (($cal['entries'] ?? []) as $e) $entries[] = $e;
            }

            // Available-date set + the longest min-stay we need to satisfy.
            $avail = []; $minStay = 1;
            foreach ($entries as $e) {
                $d = $e['date'] ?? null;
                if (!$d) continue;
                if (!($e['availability']['unavailable'] ?? true)) {
                    $avail[$d] = true;
                    $minStay = max($minStay, (int)($e['availability']['minimumStayLength'] ?? 1));
                }
            }
            ksort($avail);

            // First consecutive run of $minStay available nights.
            $nights = max(1, $minStay);
            $ci = null; $co = null;
            foreach (array_keys($avail) as $start) {
                $ok = true; $cur = $start;
                for ($i = 0; $i < $nights; $i++) {
                    if (!isset($avail[$cur])) { $ok = false; break; }
                    $cur = date('Y-m-d', strtotime($cur . ' +1 day'));
                }
                if ($ok) { $ci = $start; $co = $cur; break; }
            }
            if (!$ci) return [];

            $raw   = $this->getRawQuote(['property_id' => $propertyId, 'checkin' => $ci, 'checkout' => $co, 'adults' => 1]);
            $other = $raw['data']['quote']['overrides']['fees']['otherFees'] ?? [];

            $map = [];
            foreach ($other as $f) {
                if (isset($f['feeId'])) {
                    $map[(int)$f['feeId']] = (bool)($f['isOptional'] ?? false);
                }
            }
            return $map;
        } catch (\Throwable $e) {
            \Log::channel('lodgify')->info('HostfullyAdapter: fee-optionality probe failed — falling back to /fees isOptional', [
                'property' => $propertyId,
                'error'    => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getFrontendData(string $propertyId): array
    {
        $data = \App\Models\Pms\HostfullyData::where('pms_property_id', $propertyId)->first();
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
        
        $payload = json_decode($data->raw_property_json, true);
        $listing = $payload['property'] ?? [];
        $descRaw = $payload['descriptions'] ?? [];
        $photoRaw = $payload['photos'] ?? [];
        $amenRaw = $payload['amenities'] ?? [];
        $feeRaw = ($payload['fees_fetched'] ?? false) ? ($payload['fees'] ?? []) : null;

        // ── Description: pick the English locale entry, then the richest text field ──
        $description = '';
        // extraNotes can be an array (multiple notes) or a plain string
        $rawNotes = $listing['extraNotes'] ?? '';
        $houseRules = is_array($rawNotes) ? implode("\n\n", array_filter(array_map('strval', $rawNotes))) : (string)$rawNotes;
        if (!empty($descRaw) && is_array($descRaw)) {
            $desc = null;
            foreach ($descRaw as $d) {
                if (!is_array($d)) continue;
                $locale = strtolower($d['locale'] ?? $d['localeCode'] ?? '');
                if (str_starts_with($locale, 'en')) { $desc = $d; break; }
            }
            $desc = $desc ?? (is_array($descRaw[0] ?? null) ? $descRaw[0] : $descRaw);
            if (is_array($desc)) {
                // Match Airbnb's section order: Summary → Long Description → Space → Access → Notes
                $parts = array_filter([
                    $desc['shortSummary'] ?? $desc['summary'] ?? '',
                    $desc['longDescription'] ?? $desc['description'] ?? '',
                    $desc['space'] ?? '',
                    $desc['access'] ?? '',
                    $desc['notes'] ?? '',
                    $desc['neighbourhood'] ?? $desc['neighborhood'] ?? '',
                ]);
                $description = trim(implode("\n\n", $parts));
                if (!empty($desc['houseManual'])) {
                    $manual = $desc['houseManual'];
                    $houseRules = is_array($manual) ? implode("\n\n", array_filter(array_map('strval', $manual))) : (string)$manual;
                }
                if (empty($houseRules) && !empty($desc['notes'])) {
                    $notes = $desc['notes'];
                    $houseRules = is_array($notes) ? implode("\n\n", array_filter(array_map('strval', $notes))) : (string)$notes;
                }
            }
        }

        $images = [];
        if (!empty($photoRaw)) {
            foreach ($photoRaw as $photo) {
                $url = $photo['originalImageUrl'] ?? $photo['url'] ?? null;
                if ($url) $images[] = $url;
            }
        }
        if (empty($images) && !empty($listing['pictureLink'])) {
            $images[] = $listing['pictureLink'];
        }

        // ── Amenities: Hostfully returns objects like {"amenity":"HAS_POOL","category":"OUTDOOR"} ──
        // Humanize the codes for display: HAS_POOL → "Pool", HAS_CROCKERY_AND_CUTLERY → "Crockery And Cutlery".
        $amenities = [];
        if (is_array($amenRaw)) {
            foreach ($amenRaw as $a) {
                if (is_string($a)) {
                    $code = $a;
                } elseif (is_array($a)) {
                    $code = $a['amenity'] ?? $a['name'] ?? $a['amenityName'] ?? $a['label'] ?? null;
                } else {
                    $code = null;
                }
                if (!$code) continue;
                // Skip IS_* codes — these are property classification flags, not amenities.
                if (preg_match('/^IS_/i', (string)$code)) continue;
                // Strip HAS_ / CHECK_IN_OPTION → keep as-is for taxonomy matching.
                $label = preg_replace('/^HAS[_ ]/i', '', (string)$code);
                $label = ucwords(strtolower(str_replace('_', ' ', $label)));
                $amenities[] = trim($label);
            }
        }
        $amenities = array_values(array_unique(array_filter($amenities)));

        // Address — Hostfully can nest address inside an object or use flat fields
        $addr = is_array($listing['address'] ?? null) ? $listing['address'] : [];

        // Property type — normalize common Hostfully listingType codes
        $rawType      = $listing['propertyType'] ?? $listing['listingType'] ?? null;
        $propertyType = $rawType ? strtolower(str_replace(['_', '-'], ' ', (string)$rawType)) : null;

        // Bed configuration — Hostfully returns beddings[] array with bedroom details
        $bedConfig = [];
        foreach ($listing['beddings'] ?? $listing['bedrooms_info'] ?? [] as $br) {
            if (!is_array($br)) continue;
            $beds = [];
            foreach ($br['beds'] ?? [] as $bed) {
                $beds[] = [
                    'type'  => $bed['type'] ?? $bed['bedType'] ?? 'bed',
                    'count' => (int)($bed['quantity'] ?? $bed['count'] ?? 1),
                ];
            }
            $bedConfig[] = [
                'name' => $br['name'] ?? $br['roomType'] ?? ('Bedroom ' . (count($bedConfig) + 1)),
                'beds' => $beds,
            ];
        }
        // If no beddings array, at minimum note bedroom count
        if (empty($bedConfig) && ($listing['bedrooms'] ?? 0) > 0) {
            for ($i = 1; $i <= (int)$listing['bedrooms']; $i++) {
                $bedConfig[] = ['name' => "Bedroom {$i}", 'beds' => []];
            }
        }

        // Deposit percentage — from pricing.percentageUponReservation (authoritative per Hostfully docs).
        // IMPORTANT: Hostfully returns this as a decimal fraction (0.5 = 50%), NOT an integer.
        // Multiply by 100 when the value is <= 1 to convert to a whole-number percentage.
        // Fallback to legacy integer fields in case the property endpoint returns an older shape.
        $rawDepositPct  = $listing['pricing']['percentageUponReservation']
            ?? $listing['availability']['depositPercent']
            ?? $listing['availability']['depositPercentage']
            ?? $listing['pricing']['depositPercent']
            ?? $listing['depositPercent']
            ?? $listing['depositPercentage']
            ?? 0;
        $depositPercent = (float)$rawDepositPct <= 1.0 && (float)$rawDepositPct > 0
            ? (int)round((float)$rawDepositPct * 100)   // 0.5 → 50
            : (int)$rawDepositPct;                       // 50 → 50 (legacy integer shape)

        // Balance due days — from pricing.fullPaymentTiming.
        // Example: fullPaymentTiming=30 → balance due 30 days before check-in.
        // NULL means the property hasn't configured a payment timing policy.
        $balanceDueDays = isset($listing['pricing']['fullPaymentTiming'])
            ? (int)$listing['pricing']['fullPaymentTiming']
            : null;

        return [
            'title'          => $listing['name'] ?? 'Main Unit',
            'description'    => $description,
            'images'         => $images,
            'bedrooms'       => $listing['bedrooms'] ?? 1,
            'bathrooms'      => $listing['bathrooms'] ?? 1,
            'guests'         => $listing['availability']['maxGuests'] ?? $listing['maximumGuests'] ?? $listing['guests'] ?? 2,
            'deposit_percent' => $depositPercent ?: null,
            'balance_due_days'=> $balanceDueDays,
            'amenities'   => $amenities,
            'area'        => $listing['surfaceArea'] ?? $listing['area'] ?? null,
            'area_unit'   => $listing['surfaceAreaUnit'] ?? null,
            'property_type' => $propertyType,
            'bed_config'  => $bedConfig ?: null,
            'address' => [
                'address' => $addr['address'] ?? $listing['address1'] ?? (is_string($listing['address'] ?? null) ? $listing['address'] : ''),
                'city'    => $addr['city']    ?? $listing['city']    ?? '',
                'state'   => $addr['state']   ?? $listing['state']   ?? '',
                'zip'     => $addr['zipCode'] ?? $listing['postalCode'] ?? '',
                'country' => $addr['countryCode'] ?? $listing['countryCode'] ?? '',
                'lat'     => $addr['latitude']  ?? $listing['latitude']  ?? null,
                'lng'     => $addr['longitude'] ?? $listing['longitude'] ?? null,
            ],
            'pricing' => [
                'currency'  => $listing['pricing']['currency']  ?? $listing['baseCurrency'] ?? 'USD',
                'basePrice' => $listing['pricing']['dailyRate'] ?? $listing['baseDailyRate'] ?? 0,
            ],
            'availability' => [],
            'fees'         => is_array($feeRaw) ? $feeRaw : [],
            'house_rules'  => $houseRules,
            'cancellation_policy' => $listing['cancellationPolicy'] ?? '',
            'booking_rules' => $listing['bookingNotes'] ?? [],
            'key_facts' => \App\Helper\ListingFacts::build([
                'check_in'  => $listing['availability']['checkInTimeStart'] ?? null,
                'check_out' => $listing['availability']['checkOutTime']     ?? null,
                'min_stay'  => $listing['availability']['minimumStay']      ?? null,
                'max_stay'  => $listing['availability']['maximumStay']      ?? null,
                // Pass explicit pets_allowed from the Hostfully property object.
                // listing['pets'] is the canonical boolean flag from the API.
                // Passing explicitly avoids unreliable text-inference fallback.
                'pets_allowed' => isset($listing['pets']) ? (bool)$listing['pets'] : null,
            ], $amenities, (string)$houseRules),
            // Hostfully does not expose a per-property max-pet count in the listing object.
            // max_pets remains null (no known limit) when pets are allowed.
            'max_pets' => null,
            'pricing_normalized' => \App\Helper\ListingPricing::normalize(
                $listing['pricing'] ?? [],
                is_array($feeRaw) ? $feeRaw : [],
                'hostfully'
            ),
            // Canonical fee definitions built from /api/v3.3/fees response.
            // Covers both mandatory and optional fees for this property.
            // NOTE: Hostfully fee scope/optional fields verified against API docs;
            //   live test with a property that has optional fees recommended.
            'canonical_fees' => self::buildHostfullyCanonicalFees($feeRaw),
        ];
    }

    /**
     * Build canonical fee definitions from Hostfully /api/v3.3/fees response.
     *
     * Hostfully stores fees as a flat array of fee objects, each with uid, name,
     * type (scope), amount/netPrice, taxationRate, isOptional, isActive.
     *
     * Returns null if no fee data is available (first sync before fees endpoint is called).
     */
    private static function buildHostfullyCanonicalFees(?array $feeRaw): ?array
    {
        if ($feeRaw === null) return null;
        if ($feeRaw === []) return [];

        $canonical = [];
        foreach ($feeRaw as $fee) {
            if (!is_array($fee)) continue;
            $cf = \App\Helper\CanonicalFee::fromHostfully($fee);
            if ($cf) $canonical[] = $cf;
        }

        return $canonical;
    }

    public function parseWebhook(array $payload): array
    {
        $data = $payload['lead'] ?? $payload;

        $result = [];
        $result['arrival']   = $data['checkInLocalDateTime'] ?? $data['checkInDate'] ?? ($data['checkInLocalDate'] ?? null);
        $result['departure'] = $data['checkOutLocalDateTime'] ?? $data['checkOutDate'] ?? ($data['checkOutLocalDate'] ?? null);
        $result['property_id'] = $data['propertyUid'] ?? null;
        $result['pms_booking_id'] = $data['uid'] ?? null;
        $result['status'] = $data['status'] ?? null;
        $result['source'] = $data['source'] ?? 'Website';
        $result['source_text'] = $data['source'] ?? 'Website Booking';
        $result['property_name'] = $data['propertyName'] ?? null;
        $result['people'] = (int)($data['adults'] ?? 1) + (int)($data['children'] ?? 0);

        $result['guest'] = json_encode([
            'name' => ($data['guest']['firstName'] ?? '') . ' ' . ($data['guest']['lastName'] ?? ''),
            'email' => $data['guest']['email'] ?? '',
            'phone' => $data['guest']['phone'] ?? ''
        ]);

        $result['latest_webhook_event_id'] = md5(json_encode($payload));
        $result['total_amount'] = (float)($data['amount'] ?? 0);
        $result['total_paid'] = (float)($data['amountPaid'] ?? 0);
        $result['amount_to_pay'] = $result['total_amount'] - $result['total_paid'];
        $result['currency'] = $data['currency'] ?? 'USD';

        return $result;
    }
}
