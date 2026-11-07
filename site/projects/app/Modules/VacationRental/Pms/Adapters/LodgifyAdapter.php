<?php

namespace App\Modules\VacationRental\Pms\Adapters;

use App\Modules\VacationRental\Pms\PmsAdapter;
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
        $result['pms_booking_id']     = $data['id'] ?? null;
        $result['lodgify_booking_id'] = $data['id'] ?? null; // alias expected by tests and legacy columns
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
            Log::channel('lodgify')->error("Lodgify API Error: {$method} {$uri}", ['error' => $this->safeLogBody($body)]);
            return [
                'status' => $statusCode,
                'error'  => $body,  // raw body returned to callers; safeLogBody only used for logging
            ];
        }
    }

    /** Fetch a single reservation by PMS booking ID. */

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

    public function listProperties(array $options = []): array
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
        $res = $this->request('GET', "v1/availability/{$propertyId}", [
            'query' => [
                'periodStart' => $checkin,
                'periodEnd'   => $checkout,
            ],
        ]);

        // Augment the response with a date→lowest-price map from the stored rate calendar.
        // This replaces the old v1/rates endpoint call (which doesn't expose per-date bands).
        // Falls back to empty if no rates are stored yet for this range.
        if (($res['status'] ?? 0) >= 200 && ($res['status'] ?? 0) < 300) {
            $res['_daily_rates'] = $this->getDailyRatesFromStorage($propertyId, $checkin, $checkout);
        }

        return $res;
    }

    /**
     * Return a date → lowest-price_per_day map from the lodgify_rate_calendar table.
     * Used by AvailabilityController to inject prices into the calendar response.
     * Returns [] if no stored rates exist for the range (calendar still works, no prices shown).
     */
    public function getDailyRatesFromStorage(string $propertyId, string $from, string $to): array
    {
        try {
            $roomTypeId = $this->resolveRoomTypeId($propertyId);
            if (!$roomTypeId) return [];

            return \App\Models\Pms\LodgifyRateCalendar::lowestRatesForRange(
                (int)$propertyId, (int)$roomTypeId, $from, $to
            );
        } catch (\Throwable $e) {
            Log::channel('lodgify')->info('LodgifyAdapter::getDailyRatesFromStorage failed', [
                'property_id' => $propertyId,
                'error'       => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Resolve the primary room_type_id for a given property_id.
     * Tries pms_rooms first (fast, DB-only), then falls back to the rooms API.
     */
    public function resolveRoomTypeId(string $propertyId): ?int
    {
        $room = \App\Models\Pms\PmsRoom::where('pms_source', 'lodgify')
            ->where('pms_property_id', (string)$propertyId)
            ->value('pms_room_id');
        return $room ? (int)$room : null;
    }

    /**
     * Fetch rate calendar from Lodgify for a given property/room and date range.
     *
     * Primary:  GET /v2/rates/calendar (per-band, with additional-guest pricing)
     * Fallback: GET /v1/rates/calendar (simplified bands, no extra-guest data)
     *
     * The caller is responsible for chunking — this method fetches one date range.
     * Returns the raw API response with _source='v2' or _source='v1' injected,
     * plus _currency from the rate settings fetch.
     *
     * @param int    $propertyId  Lodgify houseId
     * @param int    $roomTypeId  Lodgify roomTypeId
     * @param string $from        Start date inclusive (YYYY-MM-DD)
     * @param string $to          End date inclusive (YYYY-MM-DD)
     */
    public function getRateCalendar(int $propertyId, int $roomTypeId, string $from, string $to): array
    {
        // ── Attempt V2 (primary) ──────────────────────────────────────────────
        $v2 = $this->request('GET', 'v2/rates/calendar', [
            'query' => [
                'houseId'    => $propertyId,
                'roomTypeId' => $roomTypeId,
                'startDate'  => $from,
                'endDate'    => $to,
            ],
        ]);

        $httpStatus = $v2['status'] ?? 0;
        if ($httpStatus >= 200 && $httpStatus < 300 && isset($v2['data']['calendar_items'])) {
            $v2['_source']   = 'v2';
            $v2['_property'] = $propertyId;
            $v2['_room']     = $roomTypeId;
            Log::channel('lodgify')->info('LodgifyAdapter::getRateCalendar v2 success', [
                'property_id'  => $propertyId,
                'room_type_id' => $roomTypeId,
                'from'         => $from,
                'to'           => $to,
                'items'        => count($v2['data']['calendar_items'] ?? []),
            ]);
            return $v2;
        }

        Log::channel('lodgify')->warning('LodgifyAdapter::getRateCalendar v2 failed — trying v1', [
            'property_id'  => $propertyId,
            'room_type_id' => $roomTypeId,
            'status'       => $httpStatus,
            'error'        => $v2['error'] ?? null,
        ]);

        // ── Fallback V1 ───────────────────────────────────────────────────────
        $v1 = $this->request('GET', 'v1/rates/calendar', [
            'query' => [
                'houseId'    => $propertyId,
                'roomTypeId' => $roomTypeId,
                'startDate'  => $from,
                'endDate'    => $to,
            ],
        ]);

        $v1Status = $v1['status'] ?? 0;
        if ($v1Status >= 200 && $v1Status < 300) {
            $v1['_source']   = 'v1';
            $v1['_property'] = $propertyId;
            $v1['_room']     = $roomTypeId;
            Log::channel('lodgify')->info('LodgifyAdapter::getRateCalendar v1 fallback success', [
                'property_id'  => $propertyId,
                'room_type_id' => $roomTypeId,
                'items'        => is_array($v1['data'] ?? null) ? count($v1['data']) : 0,
            ]);
            return $v1;
        }

        Log::channel('lodgify')->error('LodgifyAdapter::getRateCalendar both v2 and v1 failed', [
            'property_id'  => $propertyId,
            'room_type_id' => $roomTypeId,
            'v1_status'    => $v1Status,
        ]);

        return ['status' => 0, 'error' => 'Both v2 and v1 rate calendar endpoints failed', '_source' => 'none'];
    }

    /**
     * Fetch rate settings for a property (currency, VAT, fees, taxes, promotions).
     * Used to interpret rate calendar values correctly.
     * Returns [] on error.
     */
    public function getRateSettings(int $propertyId): array
    {
        try {
            $res = $this->request('GET', 'v2/rates/settings', [
                'query' => ['houseId' => $propertyId],
            ]);
            if (($res['status'] ?? 0) >= 200 && ($res['status'] ?? 0) < 300) {
                return $res['data'] ?? [];
            }
        } catch (\Throwable $e) {
            Log::channel('lodgify')->warning('LodgifyAdapter::getRateSettings failed', [
                'property_id' => $propertyId,
                'error'       => $e->getMessage(),
            ]);
        }
        return [];
    }

    /**
     * @deprecated Use getDailyRatesFromStorage() instead.
     * Left here temporarily for backward compatibility with SyncPmsCalendars.
     */
    public function getDailyRates(string $propertyId, string $from, string $to): array
    {
        return $this->getDailyRatesFromStorage($propertyId, $from, $to);
    }

    // ── kept for internal use (old v1/rates endpoint for fallback reference) ─
    private function _legacyV1Rates(string $propertyId, string $from, string $to): array
    {
        try {
            $res = $this->request('GET', "v1/rates/{$propertyId}", [
                'query' => [
                    'periodStart' => $from,
                    'periodEnd'   => $to,
                ],
            ]);

            $httpStatus = $res['status'] ?? 0;
            if ($httpStatus < 200 || $httpStatus >= 300) return [];

            $periods = $res['data'] ?? [];
            if (!is_array($periods) || empty($periods)) return [];

            $rateMap = [];
            foreach ($periods as $period) {
                $start = $period['period_start'] ?? $period['start'] ?? null;
                $end   = $period['period_end']   ?? $period['end']   ?? null;
                $price = (float)($period['price_per_night'] ?? $period['nightly_rate'] ?? $period['price'] ?? 0);

                if (!$start || !$end || $price <= 0) continue;

                $cur   = new \DateTime($start);
                $endDt = new \DateTime($end);
                while ($cur <= $endDt) {
                    $rateMap[$cur->format('Y-m-d')] = $price;
                    $cur->modify('+1 day');
                }
            }

            return $rateMap;

        } catch (\Throwable $e) {
            Log::channel('lodgify')->info('LodgifyAdapter::getDailyRates: exception — rates unavailable', [
                'property_id' => $propertyId,
                'error'       => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Returns the full raw Lodgify v2/quote response for use by QuoteNormalizer.
     * Lodgify returns an array; index 0 contains amount_gross, room_types, add_ons, total_vat.
     */
    public function getRawQuote(array $input): array
    {
        $propertyId = $input['property_id'] ?? '';
        $roomTypeId = $input['room_id'] ?? $input['room_type_id'] ?? '';
        $adults     = (int)($input['adults']   ?? 1);
        $children   = (int)($input['children'] ?? 0);
        $infants    = (int)($input['infants']  ?? 0);
        $pets       = (int)($input['pets']     ?? 0);
        $people     = max(1, $adults + $children);

        $query = [
            'arrival'                                 => ($input['checkin']  ?? '') . ' 12:00:00',
            'departure'                               => ($input['checkout'] ?? '') . ' 12:00:00',
            'roomTypes[0].Id'                         => $roomTypeId,
            'roomTypes[0].People'                     => $people,
            'roomTypes[0].guest_breakdown.adults'     => $adults,
            'roomTypes[0].guest_breakdown.children'   => $children,
            'roomTypes[0].guest_breakdown.infants'    => $infants,
            'roomTypes[0].guest_breakdown.pets'       => $pets,
        ];

        // Apply promo/coupon code — Lodgify field is promotionCode
        $promoCode = trim($input['promo_code'] ?? $input['coupon'] ?? '');
        if ($promoCode !== '') {
            $query['promotionCode'] = $promoCode;
        }

        // Selected add-ons — pass back to v2/quote so Lodgify prices them authoritatively.
        // Input format: [{"id": 123, "qty": 1}, ...] OR {"lodgify_123": 2, ...}
        // Lodgify quote params: addOns[N].Id, addOns[N].Units
        // NOTE: Pet charges may come as automatic add-ons (in quote response) or as
        // optional add-ons (in /v1/properties/{id}/rates/addons). A pet charge must
        // NOT be identified by searching for the pet count in its name — use its ID.
        $optServices = $input['optional_services'] ?? [];
        $addonIndex  = 0;
        foreach ($optServices as $key => $val) {
            if (is_array($val)) {
                $addonId = (int)($val['id'] ?? $val['addon_id'] ?? 0);
                $units   = (int)($val['qty'] ?? $val['units'] ?? 1);
            } else {
                // key = "lodgify_{id}" or plain numeric id
                $rawKey  = preg_replace('/^lodgify_/', '', (string)$key);
                $addonId = is_numeric($rawKey) ? (int)$rawKey : 0;
                $units   = max(1, (int)$val);
            }
            if ($addonId > 0 && $units > 0) {
                $query["addOns[{$addonIndex}].Id"]    = $addonId;
                $query["addOns[{$addonIndex}].Units"] = $units;
                $addonIndex++;
            }
        }

        $res   = $this->request('GET', "v2/quote/{$propertyId}", ['query' => $query]);
        $entry = ($res['data'][0] ?? $res['data'] ?? []);

        \Log::channel('lodgify')->info('Lodgify getRawQuote response', [
            'status'             => $res['status'] ?? null,
            'entry_keys'         => is_array($entry) ? array_keys($entry) : null,
            'scheduled_payments' => $entry['scheduled_payments'] ?? 'MISSING',
            'amount_gross'       => $entry['amount_gross'] ?? null,
            'error'              => $res['error'] ?? null,
        ]);

        return $res;
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
        // Lodgify v1/reservation/booking payload.
        // 'total_amount' is the correct Lodgify key for the booking total —
        // NOT 'total' which is a different field in the Lodgify schema.
        // total_amount must match the accepted PMS quote total exactly.
        $payload = [
            "source_text"  => $input['source'] ?? "Website",
            "arrival"      => ($input['checkin'] ?? '') . "T12:00:00Z",
            "departure"    => ($input['checkout'] ?? '') . "T12:00:00Z",
            "property_id"  => (int) ($input['property_id'] ?? 0),
            "status"       => $input['status'] ?? "Open",
            "bookability"  => "InstantBooking",
            "ip_created"   => $input['ip'] ?? "127.0.0.1",
            // Use total_amount (authoritative PMS quote total), not 'total'
            "total_amount" => (float)($input['total_amount'] ?? $input['gross_amount'] ?? 0),
            "currency_code" => strtoupper($input['currency'] ?? 'USD'),
            "origin"       => "Website",
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

        // ── Optional add-ons from accepted quote ─────────────────────────────
        // Pass the same add-ons that were in the accepted quote so the Lodgify
        // booking record matches the charged total.
        // Format: [{"addon_type_id": id, "units": qty}, ...]
        // optional_services is {"lodgify_{id}": qty} — extract numeric IDs.
        $optServices = $input['optional_services'] ?? [];
        $addOns      = [];
        foreach ($optServices as $optId => $qty) {
            // Strip "lodgify_" prefix to get the numeric add-on ID
            $rawId = preg_replace('/^lodgify_/', '', (string)$optId);
            if (is_numeric($rawId) && (int)$qty > 0) {
                $addOns[] = ['addon_type_id' => (int)$rawId, 'units' => (int)$qty];
            }
        }
        if (!empty($addOns)) {
            $payload['add_ons'] = $addOns;
        }

        return $this->request('POST', 'v1/reservation/booking', ['json' => $payload]);
    }

    public function updateReservation(array $input): array
    {
        return ['status' => 501, 'error' => 'Not implemented'];
    }

    /**
     * Mark a reservation as Booked (confirmed). Lodgify creates bookings as
     * "Open" by default — Open bookings DO NOT block availability (verified
     * live: v1/availability still returns is_available=true for their dates),
     * so a paid direct booking must be flipped to Booked immediately or the
     * dates stay sellable on every channel (double-booking risk).
     * [UPSTREAM pms-modules]: same gap exists in the shared module.
     */
    public function markReservationBooked($reservationId): array
    {
        if (!$reservationId) return ['status' => 400, 'error' => 'Reservation ID required'];
        return $this->request('PUT', "v1/reservation/booking/{$reservationId}/book");
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
     * Lodgify external payment recording — UNCONFIRMED.
     *
     * The public Lodgify API reviewed as of 2026-06-09 does not document a confirmed
     * endpoint for recording an externally collected Stripe payment in Lodgify's
     * payment ledger. The /v2/reservations/{id}/transactions endpoint has NOT been
     * validated against a live account, and its behaviour for externally collected
     * payments is undocumented.
     *
     * Calling this method would risk:
     * - Creating a duplicate charge record that guests can see
     * - Triggering Lodgify automation that double-charges or mis-states the balance
     * - Silent failure with misleading ok=true if the endpoint accepts but ignores the call
     *
     * Resolution: obtain written confirmation from Lodgify on their supported workflow
     * for recording externally collected payments, validate it, then implement.
     * Until then callers must set pms_sync_status = 'not_required' and log the Stripe
     * transaction locally.
     *
     * @see Lodgify_Booking_Lifecycle_Sync.md § 3 "Record the Initial Payment"
     */
    public function recordPayment(string $bookingId, float $amount, string $currency, string $stripeIntentId = '', bool $isRetry = false): array
    {
        Log::channel('lodgify')->info('LodgifyAdapter::recordPayment: skipped — no confirmed external-payment endpoint', [
            'booking_id'     => $bookingId,
            'amount'         => $amount,
            'stripe_intent'  => $stripeIntentId,
        ]);

        return [
            'ok'           => false,
            'not_supported' => true,
            'error'        => 'Lodgify: no confirmed external-payment recording endpoint. '
                              . 'Stripe transaction stored locally. Manual reconciliation required until Lodgify confirms their workflow.',
        ];
    }

    /**
     * Fetch available add-ons for a Lodgify property.
     *
     * Correct endpoint (verified against Lodgify API docs):
     *   GET /v1/properties/{propertyId}/rates/addons
     *
     * The old endpoint /v1/properties/{id}/addons returned 404 for most accounts.
     * The rates/addons endpoint returns add-ons that can be passed to the v2/quote
     * request as addOns[N].Id / addOns[N].Units.
     */
    public function getPropertyAddons(string $propertyId, array $query = []): array
    {
        $res = $this->request('GET', "v1/properties/{$propertyId}/rates/addons", ['query' => $query]);

        // Log the structure so we know the exact response shape for integration
        \Log::channel('lodgify')->info('LodgifyAdapter::getPropertyAddons response', [
            'property_id' => $propertyId,
            'status'      => $res['status'] ?? 0,
            'top_keys'    => is_array($res['data'] ?? null) ? array_keys($res['data']) : null,
            'count'       => is_array($res['data'] ?? null) ? count($res['data']) : null,
        ]);

        return $res;
    }

    /**
     * Register a webhook with Lodgify.
     * Uses Lodgify's current webhooks/v1 subscription API.
     * Event types: booking_new_any_status, booking_change, availability_change,
     *              rate_change. Property and fee metadata uses scheduled reconciliation
     *              because Lodgify currently rejects property event subscriptions.
     *
     * NOTE: Lodgify uses token-in-URL auth. The token must be appended to the URL
     * before calling this method.
     */
    public function registerWebhook(string $url, array $events): array
    {
        if (empty($events)) {
            $events = ['booking_new_any_status', 'booking_change', 'availability_change'];
        }

        $listRes = $this->request('GET', 'webhooks/v1/list');
        $existing = $listRes['data'] ?? [];
        if (!is_array($existing)) {
            $existing = [];
        }

        $results = [];
        $allOk = true;
        foreach ($events as $event) {
            $eventUrl = $this->appendWebhookEventPath($url, $event);
            $match = collect($existing)->first(function ($webhook) use ($event, $eventUrl) {
                return strtolower((string) ($webhook['event'] ?? '')) === strtolower($event)
                    && rtrim((string) ($webhook['url'] ?? ''), '/') === rtrim($eventUrl, '/');
            });

            if ($match) {
                $results[$event] = [
                    'ok' => true,
                    'webhook_id' => $match['id'] ?? null,
                    'existing' => true,
                    'error' => null,
                ];
                continue;
            }

            $res = $this->request('POST', 'webhooks/v1/subscribe', [
                'json' => [
                    'target_url' => $eventUrl,
                    'event' => $event,
                ],
            ]);
            $ok = in_array($res['status'] ?? 0, [200, 201]);
            if (!$ok) $allOk = false;
            $results[$event] = [
                'ok' => $ok,
                'webhook_id' => $res['data']['id'] ?? null,
                'existing' => false,
                'error' => $ok ? null : ($res['error'] ?? 'failed'),
                'raw' => $res,
            ];
        }

        return [
            'ok'      => $allOk,
            'results' => $results,
            'error'   => $allOk ? null : 'One or more Lodgify webhook registrations failed. Check dashboard to configure manually.',
        ];
    }

    private function appendWebhookEventPath(string $url, string $event): string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return rtrim($url, '/') . '/' . $event;
        }

        $authority = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }
        $path = rtrim($parts['path'] ?? '', '/') . '/' . $event;
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $authority . $path . $query;
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
        // Try v2/reviews first, then v1/properties/{id}/reviews as fallback
        $res = $this->request('GET', 'v2/reviews', [
            'query' => ['propertyId' => $propertyId, 'limit' => 100],
        ]);

        if (($res['status'] ?? 0) !== 200) {
            $res = $this->request('GET', "v1/properties/{$propertyId}/reviews");
        }

        \Log::channel('lodgify')->info('Lodgify getReviews', [
            'property' => $propertyId,
            'status'   => $res['status'] ?? 0,
            'top_keys' => is_array($res['data'] ?? null) ? array_keys($res['data']) : [],
        ]);

        if (($res['status'] ?? 0) !== 200) return [];

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
        // Step 1: get the v2 property — has name, location, pricing, and room stubs.
        $v2Res       = $this->request('GET', "v2/properties/{$propertyId}");
        $propertyRaw = ($v2Res['status'] ?? 0) === 200 ? ($v2Res['data'] ?? null) : null;

        // Fall back to v1 property if v2 unavailable
        if (!$propertyRaw) {
            $v1Res       = $this->request('GET', "v1/properties/{$propertyId}");
            $propertyRaw = ($v1Res['status'] ?? 0) === 200 ? ($v1Res['data'] ?? null) : null;
        }

        // Step 2: collect room stubs.
        // v2 property already contains rooms[], use those first (no extra API call).
        // Older accounts may also expose them via the v1 property endpoint.
        $roomStubs = $propertyRaw['rooms'] ?? [];
        if (empty($roomStubs)) {
            $v1PropRes = $this->request('GET', "v1/properties/{$propertyId}");
            $roomStubs = ($v1PropRes['status'] ?? 0) === 200
                ? ($v1PropRes['data']['rooms'] ?? [])
                : [];
        }

        // Step 3: for each room stub try to get full room detail.
        // Priority:
        //   a) v1 rooms/{roomId} — amenities returned as sectioned dict at root
        //   b) v2 properties/{id}/rooms/{roomId} — may return richer data than the stub
        //   c) the v2 stub itself — limited (has_wifi, bedrooms, image_url) but avoids NULL
        $unitsRaw = [];
        foreach ($roomStubs as $stub) {
            $roomId = $stub['id'] ?? null;
            if (!$roomId) continue;

            // Attempt a: v1 room detail
            $roomRes = $this->request('GET', "v1/properties/{$propertyId}/rooms/{$roomId}");
            if (($roomRes['status'] ?? 0) === 200 && is_array($roomRes['data'])) {
                $unitsRaw[] = array_merge(['_id' => $roomId, '_source' => 'v1'], $roomRes['data']);
                continue;
            }

            // Attempt b: v2 room detail (plan-dependent, may or may not exist)
            $v2RoomRes = $this->request('GET', "v2/properties/{$propertyId}/rooms/{$roomId}");
            if (($v2RoomRes['status'] ?? 0) === 200 && is_array($v2RoomRes['data'])) {
                $unitsRaw[] = array_merge(['_id' => $roomId, '_source' => 'v2_detail'], $v2RoomRes['data']);
                continue;
            }

            // Attempt c: fall back to v2 stub
            Log::channel('lodgify')->warning("Lodgify: room detail unavailable for room {$roomId} (property {$propertyId}), using v2 stub");
            $unitsRaw[] = array_merge($stub, ['_source' => 'v2_stub']);
        }

        // Step 4: Fetch add-ons from the correct endpoint.
        // GET /v1/properties/{id}/rates/addons returns optional/mandatory add-ons
        // that can be included in v2/quote requests as addOns[N].Id/Units.
        // Response shape logged on first fetch; used to build canonical_fees.
        $addonsRaw = [];
        $addonsRes = $this->getPropertyAddons($propertyId);
        $addonsFetched = ($addonsRes['status'] ?? 0) === 200;
        if ($addonsFetched) {
            $rawData = $addonsRes['data'] ?? [];
            // Lodgify may return array directly or wrap in an items/addons key
            $addonsRaw = $rawData['items'] ?? $rawData['addons'] ?? (is_array($rawData) ? $rawData : []);
            if (!is_array($addonsRaw)) $addonsRaw = [];
        }
        if ($propertyRaw) {
            $propertyRaw['_addons'] = $addonsRaw;
            $propertyRaw['_addonsFetched'] = $addonsFetched;
        }

        \App\Models\Pms\LodgifyData::updateOrCreate(
            ['pms_property_id' => $propertyId],
            [
                'raw_property_json' => $propertyRaw      ? json_encode($propertyRaw) : null,
                'raw_units_json'    => !empty($unitsRaw) ? json_encode($unitsRaw)    : null,
            ]
        );
    }

    public function getFrontendData(string $propertyId): array
    {
        $data = \App\Models\Pms\LodgifyData::where('pms_property_id', $propertyId)->first();
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
        // Lodgify: when rooms endpoint returned nothing, fall back to listing['rooms'] array.
        // Units may be v1 detail (full amenity dict), v2_detail, or v2_stub (limited).
        $firstRoom = $units[0] ?? $listing['rooms'][0] ?? [];
        $roomSource = $firstRoom['_source'] ?? 'unknown';

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

        // ── Amenities + Bedrooms/Bathrooms ───────────────────────────────────
        // Lodgify v1 returns room.amenities as a DICT keyed by category section
        // (e.g. "room", "cooking", "entertainment"), each value being an array of
        // {name, prefix, text} objects.
        //
        // The "room" section encodes room counts (RoomsBedroom, RoomsBathroom)
        // which should drive the bedrooms/bathrooms fields and NOT appear in the
        // display amenities list.
        //
        // All other sections ("cooking", "entertainment", etc.) are real amenities
        // and their "text" field is the human-readable label to display.
        $amenities = [];
        $bedrooms  = (int)($firstRoom['bedrooms']  ?? 0);
        $bathrooms = (int)($firstRoom['bathrooms'] ?? 0);

        // Lodgify v1 rooms/{id} endpoint returns amenity sections AS the response root:
        // {"room":[...],"cooking":[...],"entertainment":[...],...} — no nested 'amenities' wrapper.
        // Detect this case and use $firstRoom directly as the amenities dict.
        $rawAmen = $firstRoom['amenities']
            ?? (is_array($firstRoom) && !empty($firstRoom) && !isset($firstRoom[0])
                && (isset($firstRoom['room']) || isset($firstRoom['cooking']) || isset($firstRoom['entertainment']) || isset($firstRoom['laundry']) || isset($firstRoom['outside']) || isset($firstRoom['miscellaneous']))
                ? $firstRoom
                : null)
            ?? $listing['amenities']
            ?? null;

        if (is_array($rawAmen) && !isset($rawAmen[0]) && !empty($rawAmen)) {
            // Dict format (Lodgify v1): {room:[...], cooking:[...], ...}
            foreach ($rawAmen as $section => $items) {
                if (!is_array($items)) continue;
                foreach ($items as $item) {
                    if (!is_array($item)) continue;
                    $name   = $item['name']   ?? '';
                    $prefix = $item['prefix'] ?? null;
                    $text   = $item['text']   ?? '';

                    if ($section === 'room') {
                        // Extract bedroom/bathroom counts from the room section
                        if (str_contains($name, 'Bedroom') && (int)$prefix > 0) {
                            $bedrooms = (int)$prefix;
                        } elseif (str_contains($name, 'Bathroom') && (int)$prefix > 0) {
                            $bathrooms = (int)$prefix;
                        }
                        // Do NOT add room-count items to the amenities display list
                    } else {
                        // All other sections are real amenities
                        if ($text !== '') $amenities[] = $text;
                    }
                }
            }
        } else {
            // Flat list or other shapes — original logic as fallback
            $amenSources = array_filter([
                $listing['property_amenities'] ?? null,
                $listing['amenity_groups']     ?? null,
                $listing['facilities']         ?? null,
                is_array($rawAmen) ? $rawAmen : null,
            ], fn($v) => is_array($v) && !empty($v));

            foreach ($amenSources as $source) {
                foreach ($source as $am) {
                    if (is_string($am) && $am !== '') {
                        $amenities[] = $am;
                    } elseif (is_array($am)) {
                        $label = $am['text'] ?? $am['name'] ?? $am['title'] ?? '';
                        if ($label !== '') $amenities[] = $label;
                    }
                }
            }
        }
        $amenities = array_values(array_unique(array_filter($amenities)));

        // v2 stub fallback: when v1 amenity dict was unavailable, map Lodgify v2 boolean flags.
        // These are the only amenity signals the v2 endpoint exposes.
        if (empty($amenities) && in_array($roomSource, ['v2_stub', 'unknown'])) {
            $v2FlagMap = [
                'has_wifi'      => 'WiFi',
                'has_meal_plan' => 'Meal Plan',
            ];
            foreach ($v2FlagMap as $flag => $label) {
                if (!empty($firstRoom[$flag])) $amenities[] = $label;
            }
        }

        // Fallback: if still 0 after amenity parsing, use API fields or default to 1
        if ($bedrooms  === 0) $bedrooms  = (int)($firstRoom['bedrooms']  ?? $listing['bedrooms']  ?? 1);
        if ($bathrooms === 0) $bathrooms = (int)($firstRoom['bathrooms'] ?? $listing['bathrooms'] ?? 1);

        // Property type — Lodgify returns property_type or type field
        $rawType      = $listing['property_type'] ?? $listing['type'] ?? null;
        $propertyType = $rawType ? strtolower(str_replace(['_','-'], ' ', (string)$rawType)) : null;

        // Bed configuration — build from parsed bedroom count
        $bedConfig = [];
        for ($i = 1; $i <= $bedrooms; $i++) {
            $bedConfig[] = ['name' => "Bedroom {$i}", 'beds' => []];
        }

        return [
            'title'         => $listing['name'] ?? 'Main Unit',
            'description'   => $firstRoom['description'] ?? $listing['description'] ?? '',
            'images'        => $images,
            'bedrooms'      => $bedrooms,
            'bathrooms'     => $bathrooms,
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
            'key_facts' => \App\Helper\ListingFacts::build([
                'check_in'  => $listing['checkin'] ?? $listing['check_in'] ?? null,
                'check_out' => $listing['checkout'] ?? $listing['check_out'] ?? null,
                'min_stay'  => $firstRoom['min_stay'] ?? $listing['min_stay'] ?? null,
                'max_stay'  => $firstRoom['max_stay'] ?? $listing['max_stay'] ?? null,
                // Lodgify does not expose a pets_allowed boolean in the property API.
                // ListingFacts will infer from amenity text (e.g. "Pets allowed" amenity).
                // This is the best available signal for Lodgify.
                'pets_allowed' => null, // explicit null = infer from amenities
            ], $amenities, ''),
            // max_pets: Lodgify does not expose a numeric pet limit — leave null.
            'max_pets' => null,
            'pricing_normalized' => \App\Helper\ListingPricing::normalize(
                [
                    'currency'  => is_array($listing['currency'] ?? null)
                                    ? ($listing['currency']['code'] ?? 'USD')
                                    : ($listing['currency'] ?? 'USD'),
                    'basePrice' => $listing['min_price'] ?? 0,
                ],
                [],
                'lodgify'
            ),
            // Canonical fee definitions from rates/addons endpoint.
            // Response shape not yet verified; built from whatever the API returns.
            'canonical_fees' => $this->buildLodgifyCanonicalFees(
                ($listing['_addonsFetched'] ?? false) ? ($listing['_addons'] ?? []) : null
            ),
        ];
    }

    /**
     * Build canonical fee definitions from Lodgify rates/addons response.
     * Response shape not yet confirmed — logs are enabled in getPropertyAddons()
     * to capture the structure on first run.
     */
    private function buildLodgifyCanonicalFees(?array $addons): ?array
    {
        if ($addons === null) return null;
        if ($addons === []) return [];
        $canonical = [];
        foreach ($addons as $addon) {
            if (!is_array($addon)) continue;
            $cf = \App\Helper\CanonicalFee::fromLodgify($addon);
            if ($cf) {
                // Mark every Lodgify fee as unverified until a live rates/addons
                // response is captured and the field mapping is confirmed.
                // QuoteController will not show controls for unverified fees.
                $cf['_verified'] = false;
                $canonical[] = $cf;
            }
        }
        return $canonical;
    }

    public function createQuoteFromBooking(string $bookingId, array $payload): array
    {
        return $this->request('POST', "v1/reservation/booking/{$bookingId}/quote", ['json' => $payload]);
    }
}
