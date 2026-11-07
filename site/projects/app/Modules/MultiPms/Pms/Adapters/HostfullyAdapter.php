<?php

namespace App\Modules\MultiPms\Pms\Adapters;

use App\Modules\MultiPms\Pms\PmsAdapter;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class HostfullyAdapter implements PmsAdapter
{
    protected Client $client;
    protected ?string $apiKey = null;
    protected ?string $agencyUid = null;

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

        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.hostfully.com/',
            'headers' => [
                'X-HOSTFULLY-APIKEY' => ($this->apiKey ?? ''),
                'Accept'             => 'application/json',
                'Content-Type'       => 'application/json',
            ],
        ]);
    }

    public function getName(): string
    {
        return 'hostfully';
    }

    protected function request(string $method, string $uri, array $options = []): array
    {
        if (empty($this->apiKey)) {
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
            Log::channel('lodgify')->error("Hostfully API Error: {$method} {$uri}", ['error' => $body]);
            return [
                'status' => $statusCode,
                'error'  => $body
            ];
        }
    }

    /** Fetch a single lead/reservation by PMS booking ID. */
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

    public function listProperties(): array
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

        return $this->request('POST', 'api/v3.3/quotes', ['json' => $payload]);
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
            ]
        ];

        $res = $this->request('POST', 'api/v3.3/leads', ['json' => $leadPayload]);

        if ((($res['status'] ?? 0) === 200 || ($res['status'] ?? 0) === 201) && (isset($res['data']['uid']) || isset($res['data']['lead']['uid']))) {
            $leadUid = $res['data']['lead']['uid'] ?? $res['data']['uid'];

            // Step 2: Mark the lead as booked (confirming reservation)
            $confirmRes = $this->request('POST', "api/v3.3/leads/{$leadUid}/mark-as-booked");
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
     * Record a collected payment against a Hostfully lead (booking).
     *
     * Hostfully's Transactions API requires an Order UID, not a Lead UID.
     * We first fetch the lead to get its associated order UID, then post the transaction.
     * Endpoint: POST /api/v3.3/orders/{orderUid}/transactions
     * Method: CREDIT_CARD (payment already collected externally via Stripe)
     */
    public function recordPayment(string $bookingId, float $amount, string $currency): array
    {
        // Step 1: Fetch the lead to get the order UID
        $lead = $this->request('GET', "api/v3.3/leads/{$bookingId}");
        if (($lead['status'] ?? 0) !== 200) {
            return ['ok' => false, 'error' => 'Could not fetch lead to record payment'];
        }

        $orderUid = $lead['data']['lead']['orderUid'] ?? $lead['data']['lead']['orderUid'] ?? null;
        if (!$orderUid) {
            // Hostfully may not always expose orderUid via this endpoint — log and skip
            return ['ok' => false, 'error' => 'No orderUid on lead — cannot record payment'];
        }

        // Step 2: Post transaction against the order
        $res = $this->request('POST', "api/v3.3/orders/{$orderUid}/transactions", [
            'json' => [
                'agencyUid'         => $this->agencyUid,
                'amount'            => $amount,
                'currency'          => strtoupper($currency),
                'paymentMethod'     => 'CREDIT_CARD',
                'transactionType'   => 'PAYMENT',
                'notes'             => 'Scheduled installment charged via website (Stripe)',
            ],
        ]);

        return [
            'ok'     => ($res['status'] ?? 0) === 200 || ($res['status'] ?? 0) === 201,
            'status' => $res['status'] ?? 0,
            'data'   => $res['data'] ?? [],
            'error'  => $res['error'] ?? null,
        ];
    }

    public function getPropertyAddons(string $propertyId, array $query = []): array
    {
        return ['status' => 200, 'data' => []];
    }

    /**
     * Register a webhook with Hostfully.
     * POST /api/v3.3/webhooks
     * Triggers supported: LEAD_CREATED, LEAD_STATUS_CHANGED, PROPERTY_AVAILABILITY_CHANGED,
     *                     PROPERTY_UPDATED, QUOTE_CREATED
     * Docs: https://dev.hostfully.com/reference/webhooks-1
     */
    public function registerWebhook(string $url, array $events): array
    {
        // Hostfully requires one registration per eventType (singular).
        // webhookType controls the HTTP format: POST_JSON = JSON body.
        // Correct eventType enum values from Hostfully v3.3 API docs.
        if (empty($events)) {
            $events = ['NEW_BOOKING', 'BOOKING_UPDATED', 'BOOKING_CANCELLED', 'PROPERTY_AVAILABILITY_UPDATED'];
        }

        $results = [];
        $allOk   = true;

        foreach ($events as $eventType) {
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
                'webhook_id' => $res['data']['uid'] ?? null,
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
     * GET /api/v3.3/reviews?propertyUid={uid}&limit=50
     * Returns: {reviews: [{uid, guestFirstName, guestLastName, rating, review, submittedAt, checkInDate, checkOutDate}]}
     * Rating: 1–5 integer.
     */
    public function getReviews(string $propertyId): array
    {
        $res = $this->request('GET', 'api/v3.3/reviews', [
            'query' => ['propertyUid' => $propertyId, 'limit' => 50],
        ]);

        if (($res['status'] ?? 0) !== 200) return [];

        $reviews = $res['data']['reviews'] ?? $res['data'] ?? [];
        if (!is_array($reviews)) return [];

        $out = [];
        foreach ($reviews as $r) {
            $first = $r['guestFirstName'] ?? '';
            $last  = $r['guestLastName']  ?? '';
            $name  = trim("{$first} {$last}") ?: ($r['guestName'] ?? 'Guest');
            $score = (int)($r['rating'] ?? 5);
            if ($score < 1) $score = 5;
            if ($score > 5) $score = 5;

            $out[] = [
                'id'        => (string)($r['uid'] ?? $r['id'] ?? uniqid()),
                'name'      => $name,
                'score'     => $score,
                'message'   => $r['review'] ?? $r['comment'] ?? '',
                'stay_date' => $r['checkInDate']  ?? null,
                'end_date'  => $r['checkOutDate'] ?? null,
                'profile'   => $r['guestLocation'] ?? null,
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

        $payload = [
            'property' => $propertyRaw,
            'descriptions' => $descRaw,
            'photos' => $photoRaw,
            'amenities' => $amenRaw,
            'fees' => $feeRaw,
        ];

        \App\Modules\MultiPms\Models\HostfullyData::updateOrCreate(
            ['pms_property_id' => $propertyId],
            [
                'raw_property_json' => json_encode($payload),
                'raw_units_json' => null,
            ]
        );
    }

    public function getFrontendData(string $propertyId): array
    {
        $data = \App\Modules\MultiPms\Models\HostfullyData::where('pms_property_id', $propertyId)->first();
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
        $feeRaw = $payload['fees'] ?? [];

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
                $parts = array_filter([
                    $desc['shortSummary'] ?? '',
                    $desc['longDescription'] ?? $desc['description'] ?? '',
                    $desc['neighbourhood'] ?? $desc['neighborhood'] ?? '',
                    $desc['access'] ?? '',
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

        return [
            'title'       => $listing['name'] ?? 'Main Unit',
            'description' => $description,
            'images'      => $images,
            'bedrooms'    => $listing['bedrooms'] ?? 1,
            'bathrooms'   => $listing['bathrooms'] ?? 1,
            'guests'      => $listing['availability']['maxGuests'] ?? $listing['maximumGuests'] ?? $listing['guests'] ?? 2,
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
            'key_facts' => \App\Modules\MultiPms\Helper\ListingFacts::build([
                'check_in'  => $listing['availability']['checkInTimeStart'] ?? null,
                'check_out' => $listing['availability']['checkOutTime']     ?? null,
                'min_stay'  => $listing['availability']['minimumStay']      ?? null,
                'max_stay'  => $listing['availability']['maximumStay']      ?? null,
            ], $amenities, (string)$houseRules),
            'pricing_normalized' => \App\Modules\MultiPms\Helper\ListingPricing::normalize(
                $listing['pricing'] ?? [],
                is_array($feeRaw) ? $feeRaw : [],
                'hostfully'
            ),
        ];
    }

    public function parseWebhook(array $payload): array
    {
        $data = $payload;

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
