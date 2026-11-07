<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Helper\LogRedactor;
use App\Models\Pms\PmsBooking;
use App\Models\Pms\PmsRoom;
use App\Models\BookingRequest;
use App\Models\ScheduledPayment;
use App\Jobs\SyncRoomCalendarJob;
use App\Modules\VacationRental\Pms\PmsManager;
use App\Services\Operations\OperationsRecorder;
use App\Services\PmsPropertySyncService;

class WebhookController extends Controller
{
    public function __construct(
        protected PmsManager $pmsManager,
        protected PmsPropertySyncService $propertySync,
        protected OperationsRecorder $ops
    ) {
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Property Events  →  Targeted Sync Engine
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Handles NEW_PROPERTY and UPDATED_PROPERTY webhook events from Lodgify.
     *
     * Lodgify sends a payload containing the property_id (uid) of the property
     * that changed. We extract that id and call the Targeted Sync Engine which
     * fetches only that one property from the API — ~2 seconds, zero server load.
     *
     * Why Route::any()? Some server firewall/SSL configs issue 301 redirects.
     * HTTP spec converts POST → GET on redirect. Route::any() accepts both,
     * preventing 405 errors on those servers.
     */
    public function property_event(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->all();

        Log::channel('pms_webhooks')->info('property_event received', [
            'method'  => $request->method(),
            'payload' => $payload,
        ]);

        try {
            // Extract property_id from the Lodgify payload
            // Lodgify sends property_id in different locations depending on event type
            $propertyId = $this->extractPropertyId($payload);

            if (!$propertyId) {
                Log::channel('pms_webhooks')->warning('property_event: could not extract property_id', [
                    'payload' => $payload,
                ]);
                $this->recordWebhook('lodgify', 'property_event', 'failure', $payload, 'Property event missing property_id', 'property_id not found in payload');
                return response()->json(['status' => 'error', 'message' => 'property_id not found in payload'], 400);
            }

            Log::channel('pms_webhooks')->info("property_event: triggering targeted sync for property #{$propertyId}");

            // Hand off to the Targeted Sync Engine — only syncs this one property
            $result = $this->propertySync->sync('lodgify', (string) $propertyId);
            $this->recordWebhook(
                'lodgify',
                'property_event',
                $result['success'] ? 'success' : 'failure',
                $payload,
                $result['message'] ?? "Property event processed for {$propertyId}",
                $result['success'] ? null : ($result['message'] ?? 'Property sync failed')
            );

            return response()->json([
                'status'      => $result['success'] ? 'success' : 'error',
                'message'     => $result['message'],
                'property_id' => $propertyId,
            ], $result['success'] ? 200 : 500);

        } catch (\Throwable $e) {
            Log::channel('pms_webhooks')->error('property_event: exception', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            $this->recordWebhook('lodgify', 'property_event', 'failure', $payload, 'Property event failed', $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Internal error'], 500);
        }
    }

    /**
     * Extracts the Lodgify property ID from any known payload shape.
     * Lodgify's payload structure varies slightly between event types.
     */
    private function extractPropertyId(array $payload): ?int
    {
        // Shape 1: top-level property_id (most common for property events)
        if (!empty($payload['property_id'])) {
            return (int) $payload['property_id'];
        }

        // Shape 2: nested inside 'property' key
        if (!empty($payload['property']['id'])) {
            return (int) $payload['property']['id'];
        }

        // Shape 3: array of events (booking-style payload containing property_id)
        if (is_array($payload) && isset($payload[0]['booking']['property_id'])) {
            return (int) $payload[0]['booking']['property_id'];
        }

        // Shape 4: property_uid key (used in some Lodgify webhook versions)
        if (!empty($payload['property_uid'])) {
            return (int) $payload['property_uid'];
        }

        return null;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Availability / Rate Events  →  Targeted Sync
    // ──────────────────────────────────────────────────────────────────────────

    public function availability_change(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->all();
        Log::channel('pms_webhooks')->info('availability_change', ['payload' => $payload]);

        // Availability change: re-sync just the affected property
        $propertyId = $this->extractPropertyId($payload);
        if ($propertyId) {
            $this->triggerAvailabilitySync('lodgify', (string) $propertyId);
        }

        $this->recordWebhook('lodgify', 'availability_change', 'success', $payload, $propertyId ? "Availability sync requested for {$propertyId}" : 'Availability event accepted without property_id');
        return response()->json(['status' => 'success'], 200);
    }

    public function rate_change(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload    = $request->all();
        $propertyId = $payload['property_id'] ?? $this->extractPropertyId($payload);
        $roomTypeIds = $payload['room_type_ids'] ?? [];   // Lodgify rate_change payload

        Log::channel('pms_webhooks')->info('rate_change webhook received', [
            'property_id'  => $propertyId,
            'room_type_ids'=> $roomTypeIds,
            'payload'      => $payload,
        ]);

        if (!$propertyId) {
            $this->recordWebhook('lodgify', 'rate_change', 'success', $payload, 'Rate event accepted without property_id');
            return response()->json(['status' => 'ok', 'note' => 'no property_id'], 200);
        }

        // Spec rule: rate_change payload has NO dates or prices.
        // Must re-fetch the complete 731-day horizon for every affected room type.
        // Dispatch after response so Lodgify gets the 200 immediately.
        if (!empty($roomTypeIds)) {
            foreach ($roomTypeIds as $roomTypeId) {
                dispatch(new \App\Jobs\SyncRoomCalendarJob('lodgify', (string)$roomTypeId))->afterResponse();
                // Also re-sync the rate bands specifically
                \Illuminate\Support\Facades\Artisan::queue('lodgify:sync-rates', [
                    '--property' => (string)$propertyId,
                    '--room'     => (string)$roomTypeId,
                ]);
            }
        } else {
            // No specific room types — re-sync all rooms for this property
            dispatch(function () use ($propertyId) {
                \Illuminate\Support\Facades\Artisan::call('lodgify:sync-rates', [
                    '--property' => (string)$propertyId,
                ]);
            })->afterResponse();
        }

        // Also invalidate availability cache for this property
        $room = \App\Models\Pms\PmsRoom::where('pms_source', 'lodgify')
            ->where('pms_property_id', (string)$propertyId)
            ->first();
        if ($room) {
            $vKey = "pms_avail_v_lodgify_{$room->pms_property_id}";
            Cache::put($vKey, (Cache::get($vKey, 0) + 1), 86400);
        }

        $this->recordWebhook('lodgify', 'rate_change', 'success', $payload, "Rate sync queued for property {$propertyId}");
        return response()->json(['status' => 'success'], 200);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Booking Events  →  Booking Upsert (unchanged logic)
    // ──────────────────────────────────────────────────────────────────────────

    private function processUnifiedBookingWebhook(Request $request, string $providerName, string $eventName): \Illuminate\Http\JsonResponse
    {
        Log::channel('pms_webhooks')->info("{$providerName} - {$eventName}", [
            'payload' => LogRedactor::clean($request->all()),
        ]);

        try {
            $payload = $request->all();
            $adapter = $this->pmsManager->driver($providerName);
            $parsePayload = $this->hydrateBookingWebhook($adapter, $providerName, $payload);
            $result = $adapter->parseWebhook($parsePayload);
            if (!empty($result)) {
                $result['latest_webhook_event_id'] = $this->webhookEventId($payload);
                $result['pms_source'] = $providerName;
            }
        } catch (\Throwable $e) {
            Log::channel('pms_webhooks')->error("{$providerName} Webhook parsing error: " . $e->getMessage(), ['payload' => LogRedactor::clean($request->all())]);
            $this->recordWebhook($providerName, $eventName, 'failure', $request->all(), 'Webhook parse failed', $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Invalid payload or adapter error'], 400);
        }

        // Handle case where parseWebhook returns null (e.g. ignoring unhandled events)
        if (empty($result)) {
            $this->recordWebhook($providerName, $eventName, 'ignored', $request->all(), 'Event ignored by adapter');
            return response()->json(['status' => 'success', 'message' => 'Event ignored'], 200);
        }
        if (empty($result['pms_booking_id'])) {
            Log::channel('pms_webhooks')->warning("{$providerName} webhook ignored: reservation ID missing", [
                'event' => $eventName,
                'payload' => LogRedactor::clean($request->all()),
            ]);
            $this->recordWebhook($providerName, $eventName, 'ignored', $request->all(), 'Reservation ID missing; event ignored');
            return response()->json(['status' => 'success', 'message' => 'Reservation ID missing; event ignored'], 200);
        }

        // Idempotency: skip if we already processed this exact payload
        $eventId  = $result['latest_webhook_event_id'] ?? null;
        if ($eventId) {
            $existing = PmsBooking::where('latest_webhook_event_id', $eventId)
                ->where('pms_booking_id', $result['pms_booking_id'])
                ->where(function ($query) use ($providerName) {
                    $query->where('pms_source', $providerName)->orWhereNull('pms_source');
                })
                ->first();
            if ($existing) {
                Log::channel('pms_webhooks')->info("Duplicate webhook skipped for event ID: {$eventId}");
                $this->recordWebhook($providerName, $eventName, 'ignored', $request->all(), "Duplicate event skipped: {$eventId}");
                return response()->json(['status' => 'success', 'message' => 'Idempotent skip'], 200);
            }
        }

        Log::channel('pms_webhooks')->info("Prepared Data {$providerName} {$eventName}", $result);

        $booking = PmsBooking::where('pms_booking_id', $result['pms_booking_id'])
            ->where(function ($query) use ($providerName) {
                $query->where('pms_source', $providerName)->orWhereNull('pms_source');
            })
            ->first();
        if ($booking) {
            $booking->update($result);
            Log::channel('pms_webhooks')->info("Result", ['message' => "Booking updated {$eventName}"]);
        } else {
            PmsBooking::create($result);
            Log::channel('pms_webhooks')->info("Result", ['message' => "Booking inserted {$eventName}"]);
        }

        // Bridge to legacy CRM BookingRequest table
        $guestObj = isset($result['guest']) && is_string($result['guest']) ? json_decode($result['guest'], true) : ($result['guest'] ?? []);
        $adults = 0;
        $child = 0;
        if(isset($result['rooms'])){
            $roomsArr = is_string($result['rooms']) ? json_decode($result['rooms'], true) : $result['rooms'];
            foreach($roomsArr as $c){
                if (isset($c['guest_breakdown'])) {
                    $adults += $c['guest_breakdown']['adults'] ?? 0;
                    $child += $c['guest_breakdown']['children'] ?? 0;
                }
            }
        }

        // Detect if the existing BookingRequest was originally made on our website
        // (booking_type_admin for external/synced bookings ends in '-sync'; website bookings have null or other values)
        $existingBookingReq = BookingRequest::where('pms_booking_id', $result['pms_booking_id'])->first();
        $isWebsiteBooking = $existingBookingReq
            && !str_ends_with(trim($existingBookingReq->booking_type_admin ?? 'external-sync'), '-sync');

        $legacyPropertyId = $this->resolveLegacyPropertyId($providerName, $result['property_id'] ?? null, $existingBookingReq);

        BookingRequest::updateOrCreate(
            ['pms_booking_id' => $result['pms_booking_id']],
            [
                'property_id' => $legacyPropertyId,
                'checkin' => $result['arrival'] ?? date('Y-m-d'),
                'checkout' => $result['departure'] ?? date('Y-m-d', strtotime('+1 day')),
                'total_guests' => $result['people'] ?? 1,
                'adults' => $adults,
                'child' => $child,
                'total_amount' => $result['total_amount'] ?? 0,
                'before_total_fees' => '[]',
                'after_total_fees' => '[]',
                'name' => $guestObj['name'] ?? 'Unknown Guest',
                'email' => $guestObj['email'] ?? 'guest@pms.com',
                'status' => $result['status'] ?? null,
                // Preserve the original booking_type_admin for existing records.
                // This keeps website bookings identifiable after PMS webhook updates.
                // New records (external syncs) get "{pms}-sync".
                'booking_type_admin' => $existingBookingReq
                    ? ($existingBookingReq->booking_type_admin ?? "{$providerName}-sync")
                    : "{$providerName}-sync",
            ]
        );

        // Handle cancellation — update status and (for website bookings only) trigger full cancel flow
        if ($this->isCancellationStatus($result['status'] ?? '')) {
            $bookingReq = BookingRequest::where('pms_booking_id', $result['pms_booking_id'])->first();
            if ($bookingReq && $bookingReq->booking_status !== 'booking-cancel') {
                $bookingReq->booking_status = 'booking-cancel';
                $bookingReq->save();

                Log::channel('pms_webhooks')->info("processUnifiedBookingWebhook: cancellation detected", [
                    'pms'              => $providerName,
                    'pms_booking_id'   => $result['pms_booking_id'],
                    'is_website'       => $isWebsiteBooking,
                ]);

                if ($isWebsiteBooking) {
                    // Website booking: cancel installments, release deposit, send emails
                    $this->handleWebsiteBookingCancellation($bookingReq);
                }

                // Always bust availability cache so calendar reflects the freed dates immediately
                $propertyId = $result['property_id'] ?? null;
                if ($propertyId) {
                    $vKey = "pms_avail_v_{$providerName}_{$propertyId}";
                    Cache::put($vKey, (Cache::get($vKey, 0) + 1), 86400);
                }
            }
        }

        $this->recordWebhook($providerName, $eventName, 'success', $request->all(), "Booking webhook processed: {$result['pms_booking_id']}");
        return response()->json(['status' => 'success'], 200);
    }

    private function resolveLegacyPropertyId(string $providerName, mixed $pmsPropertyId, ?BookingRequest $existingBookingReq = null): int
    {
        if ($existingBookingReq && (int) $existingBookingReq->property_id > 0) {
            return (int) $existingBookingReq->property_id;
        }

        if (is_numeric($pmsPropertyId)) {
            $directId = (int) $pmsPropertyId;
            if (\App\Models\Property::whereKey($directId)->exists()) {
                return $directId;
            }
        }

        $pmsPropertyId = (string) ($pmsPropertyId ?? '');
        if ($pmsPropertyId !== '') {
            $room = PmsRoom::where('pms_source', $providerName)
                ->where(function ($query) use ($pmsPropertyId) {
                    $query->where('pms_property_id', $pmsPropertyId)
                        ->orWhere('pms_room_id', $pmsPropertyId);
                })
                ->first();

            if ($room) {
                $linked = \App\Models\Property::where('pms_room_id', $room->pms_room_id)
                    ->orWhere('pms_room_id', $room->pms_property_id)
                    ->first();
                if ($linked) {
                    return (int) $linked->id;
                }
            }
        }

        Log::channel('pms_webhooks')->warning('Could not resolve PMS property to local property; using 0 for legacy BookingRequest bridge', [
            'pms' => $providerName,
            'pms_property_id' => $pmsPropertyId,
        ]);

        return 0;
    }

    private function processBookingEvent(Request $request, string $eventName): \Illuminate\Http\JsonResponse
    {
        return $this->processUnifiedBookingWebhook($request, 'lodgify', $eventName);
    }

    public function booking_new_any_status(Request $request)       { return $this->processBookingEvent($request, 'booking_new_any_status'); }
    public function booking_new_status_booked(Request $request)    { return $this->processBookingEvent($request, 'booking_new_status_booked'); }
    public function booking_change(Request $request)               { return $this->processBookingEvent($request, 'booking_change'); }
    public function booking_status_change_booked(Request $request) { return $this->processBookingEvent($request, 'booking_status_change_booked'); }
    public function booking_status_change_tentative(Request $request) { return $this->processBookingEvent($request, 'booking_status_change_tentative'); }
    public function booking_status_change_open(Request $request)   { return $this->processBookingEvent($request, 'booking_status_change_open'); }
    public function booking_status_change_declined(Request $request) { return $this->processBookingEvent($request, 'booking_status_change_declined'); }

    public function hostfully_webhook(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload     = $request->all();
        $triggerType = $payload['event_type'] ?? $payload['triggerType'] ?? '';

        // Availability / property change — trigger a targeted sync of the affected property
        if (in_array($triggerType, ['PROPERTY_AVAILABILITY_UPDATED', 'UPDATED_PROPERTY', 'NEW_PROPERTY', 'ACTIVATED_PROPERTY'])) {
            Log::channel('pms_webhooks')->info("hostfully_webhook: availability/property event [{$triggerType}]", ['payload' => $payload]);
            $propertyUid = $payload['property_uid'] ?? $payload['propertyUid'] ?? $payload['lead']['propertyUid'] ?? null;
            if ($propertyUid) {
                $this->triggerAvailabilitySync('hostfully', $propertyUid);
            }
            $this->recordWebhook('hostfully', $triggerType, 'success', $payload, $propertyUid ? "Property sync requested for {$propertyUid}" : 'Property event accepted');
            return response()->json(['status' => 'success'], 200);
        }

        if (in_array($triggerType, ['DELETED_PROPERTY', 'DEACTIVATED_PROPERTY'])) {
            $propertyUid = $payload['property_uid'] ?? $payload['propertyUid'] ?? null;
            if ($propertyUid) {
                PmsRoom::where('pms_source', 'hostfully')
                    ->where('pms_property_id', (string) $propertyUid)
                    ->update(['status' => 'false']);
                $vKey = "pms_avail_v_hostfully_{$propertyUid}";
                Cache::put($vKey, Cache::get($vKey, 0) + 1, 86400);
            }
            $this->recordWebhook('hostfully', $triggerType, 'success', $payload, $propertyUid ? "Property hidden and cache busted for {$propertyUid}" : 'Delete/deactivate event accepted');
            return response()->json(['status' => 'success'], 200);
        }

        // Booking event — process as before + also sync availability
        $response = $this->processUnifiedBookingWebhook($request, 'hostfully', $triggerType ?: 'hostfully_webhook');

        // Trigger availability sync so blocked dates update without waiting for hourly sync
        $propertyUid = $payload['property_uid'] ?? $payload['propertyUid'] ?? $payload['lead']['propertyUid'] ?? null;
        if ($propertyUid) {
            $this->triggerAvailabilitySync('hostfully', $propertyUid);
        }

        return $response;
    }

    public function guesty_webhook(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->all();
        $event   = $payload['event'] ?? '';

        // Calendar blocked/unblocked — availability changed, sync the property
        if ($event === 'listing.calendar.updated' || $event === 'calendar.updated.v2') {
            Log::channel('pms_webhooks')->info("guesty_webhook: calendar event [{$event}]", ['payload' => $payload]);
            foreach ($this->extractGuestyListingIds($payload) as $listingId) {
                $this->triggerAvailabilitySync('guesty', $listingId);
            }
            $this->recordWebhook('guesty', $event, 'success', $payload, 'Calendar sync requested');
            return response()->json(['status' => 'success'], 200);
        }

        // Listing deleted — do NOT try to re-sync a deleted property
        // Just bust the cache so the calendar stops serving stale data
        if ($event === 'listing.removed' || $event === 'listing.deleted') {
            Log::channel('pms_webhooks')->warning("guesty_webhook: listing removed — property removed from Guesty", ['payload' => $payload]);
            $listingId = $this->extractGuestyListingId($payload);
            if ($listingId) {
                PmsRoom::where('pms_source', 'guesty')
                    ->where('pms_property_id', (string) $listingId)
                    ->update(['status' => 'false']);
                $vKey = "pms_avail_v_guesty_{$listingId}";
                Cache::put($vKey, (Cache::get($vKey, 0) + 1), 86400);
            }
            $this->recordWebhook('guesty', $event, 'success', $payload, $listingId ? "Listing hidden/cache busted for {$listingId}" : 'Listing removed event accepted');
            return response()->json(['status' => 'success'], 200);
        }

        // Listing info changed (title, description, photos, amenities, etc.)
        // Guesty uses payload['data']['_id'] not ['data']['listingId'] for listing events
        if (str_starts_with($event, 'listing.')) {
            Log::channel('pms_webhooks')->info("guesty_webhook: listing event [{$event}]", ['payload' => $payload]);
            $listingId = $this->extractGuestyListingId($payload);
            if ($listingId) {
                $this->triggerAvailabilitySync('guesty', $listingId);
            }
            $this->recordWebhook('guesty', $event, 'success', $payload, $listingId ? "Listing sync requested for {$listingId}" : 'Listing event accepted');
            return response()->json(['status' => 'success'], 200);
        }

        // Booking event — process + sync
        $response  = $this->processUnifiedBookingWebhook($request, 'guesty', $event ?: 'guesty_webhook');
        $listingId = $this->extractGuestyListingId($payload);
        if ($listingId) {
            $this->triggerAvailabilitySync('guesty', $listingId);
        }
        return $response;
    }

    public function hostaway_webhook(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload   = $request->all();
        $event     = $payload['event'] ?? '';
        $listingId = $payload['result']['listingId'] ?? $payload['listingId'] ?? null;
        $object = strtolower((string) ($payload['object'] ?? ''));

        // Legacy events are still accepted, although current Hostaway unified
        // webhooks officially deliver reservation and message changes only.
        if ($event === 'calendar_updated') {
            Log::channel('pms_webhooks')->info("hostaway_webhook: calendar_updated", ['payload' => $payload]);
            if ($listingId) {
                $this->triggerAvailabilitySync('hostaway', (string)$listingId);
            }
            $this->recordWebhook('hostaway', $event, 'success', $payload, $listingId ? "Calendar sync requested for {$listingId}" : 'Calendar event accepted');
            return response()->json(['status' => 'success'], 200);
        }

        // Listing deleted — bust cache only, do not try to re-sync a removed property
        if ($event === 'listing_deleted') {
            Log::channel('pms_webhooks')->warning("hostaway_webhook: listing_deleted — property removed from Hostaway", ['payload' => $payload]);
            if ($listingId) {
                $vKey = "pms_avail_v_hostaway_{$listingId}";
                Cache::put($vKey, (Cache::get($vKey, 0) + 1), 86400);
            }
            $this->recordWebhook('hostaway', $event, 'success', $payload, $listingId ? "Listing cache busted for {$listingId}" : 'Listing deleted event accepted');
            return response()->json(['status' => 'success'], 200);
        }

        // Listing info changed (description, photos, amenities, etc.)
        if (in_array($event, ['listing_created', 'listing_updated'])) {
            Log::channel('pms_webhooks')->info("hostaway_webhook: listing event [{$event}]", ['payload' => $payload]);
            if ($listingId) {
                $this->triggerAvailabilitySync('hostaway', (string)$listingId);
            }
            $this->recordWebhook('hostaway', $event, 'success', $payload, $listingId ? "Listing sync requested for {$listingId}" : 'Listing event accepted');
            return response()->json(['status' => 'success'], 200);
        }

        // New or updated guest review
        if (in_array($event, ['review_created', 'review_updated'])) {
            Log::channel('pms_webhooks')->info("hostaway_webhook: review event [{$event}]", ['payload' => $payload]);
            $this->syncReviewsForPms('hostaway', $listingId ? (string)$listingId : null);
            $this->recordWebhook('hostaway', $event, 'success', $payload, $listingId ? "Review sync requested for {$listingId}" : 'Review sync requested');
            return response()->json(['status' => 'success'], 200);
        }

        if ($object !== '' && $object !== 'reservation') {
            $this->recordWebhook('hostaway', $event ?: 'hostaway_webhook', 'ignored', $payload, "Non-reservation object ignored: {$object}");
            return response()->json(['status' => 'success', 'message' => 'Non-reservation event ignored'], 200);
        }

        // Booking event — process + sync
        $response = $this->processUnifiedBookingWebhook($request, 'hostaway', $event ?: 'hostaway_webhook');
        if (!$listingId && $object === 'reservation' && !empty($payload['objectId'])) {
            $listingId = PmsBooking::where('pms_booking_id', (string) $payload['objectId'])
                ->where('pms_source', 'hostaway')
                ->value('property_id');
        }
        if ($listingId) {
            $this->triggerAvailabilitySync('hostaway', (string)$listingId);
        }
        return $response;
    }

    /**
     * Trigger a targeted sync for a specific property when an availability webhook arrives.
     * This is intentionally async-light: runs inline but only syncs one property,
     * so it's fast enough for a webhook response window (<10s).
     */
    private function triggerAvailabilitySync(string $pmsSource, string $propertyId): void
    {
        try {
            $result = $this->propertySync->sync($pmsSource, $propertyId);
            if (!$result['success']) {
                Log::channel('pms_webhooks')->error('triggerAvailabilitySync: property sync failed', [
                    'pms' => $pmsSource,
                    'property' => $propertyId,
                    'error' => $result['message'],
                ]);
                return;
            }

            $room = PmsRoom::where('pms_source', $pmsSource)
                           ->where(function ($q) use ($propertyId) {
                               $q->where('pms_property_id', $propertyId)
                                 ->orWhere('pms_room_id', $propertyId);
                           })
                           ->first();

            if (!$room) {
                Log::channel('pms_webhooks')->warning("triggerAvailabilitySync: no room found for {$pmsSource} property {$propertyId}");
                return;
            }

            // Bust the availability cache immediately — without this, the 1-hour
            // cache keeps serving stale dates even after a new booking is confirmed.
            $vKey = "pms_avail_v_{$pmsSource}_{$room->pms_property_id}";
            Cache::put($vKey, (Cache::get($vKey, 0) + 1), 86400);

            // Refresh pms_calendar for this room after the webhook response is sent.
            // Uses afterResponse() so the PMS receives our 200 immediately — the
            // calendar sync (731 days, chunked) runs after the HTTP response is flushed.
            // This keeps pms_calendar rates and restrictions up-to-date in near real-time
            // without affecting webhook round-trip latency.
            dispatch(new SyncRoomCalendarJob($pmsSource, $room->pms_room_id))->afterResponse();

            Log::channel('pms_webhooks')->info("triggerAvailabilitySync: synced {$pmsSource} property {$propertyId}", [
                'room_id'            => $room->id,
                'cache_busted'       => true,
                'calendar_sync_queued' => true,
            ]);

        } catch (\Throwable $e) {
            // Never let sync failure affect the webhook 200 response
            Log::channel('pms_webhooks')->error("triggerAvailabilitySync failed", [
                'pms'        => $pmsSource,
                'property'   => $propertyId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function hydrateBookingWebhook($adapter, string $provider, array $payload): array
    {
        $embedded = match ($provider) {
            'guesty' => $payload['reservation'] ?? $payload['data'] ?? null,
            'hostaway' => $payload['result'] ?? null,
            'hostfully' => $payload['lead'] ?? null,
            default => null,
        };

        if (is_array($embedded) && $this->hasReservationDates($provider, $embedded)) {
            return $embedded;
        }
        if ($provider === 'lodgify') {
            return $payload;
        }

        $reservationId = match ($provider) {
            'guesty' => $payload['reservation']['_id'] ?? $payload['data']['_id'] ?? null,
            'hostaway' => $payload['objectId'] ?? $payload['result']['id'] ?? $payload['id'] ?? null,
            'hostfully' => $payload['lead_uid'] ?? $payload['leadUid'] ?? $payload['lead']['uid'] ?? null,
            default => null,
        };

        if (!$reservationId || !method_exists($adapter, 'getReservation')) {
            return is_array($embedded) ? $embedded : $payload;
        }

        $response = $adapter->getReservation((string) $reservationId);
        if (($response['status'] ?? 0) !== 200) {
            throw new \RuntimeException("Unable to fetch {$provider} reservation {$reservationId}");
        }

        $data = $response['data'] ?? [];
        return match ($provider) {
            'hostfully' => $data['lead'] ?? $data,
            'hostaway' => $data['result'] ?? $data,
            default => $data['reservation'] ?? $data,
        };
    }

    private function hasReservationDates(string $provider, array $data): bool
    {
        return match ($provider) {
            'hostfully' => !empty($data['checkInLocalDateTime'] ?? $data['checkInDate'] ?? null),
            'hostaway' => !empty($data['arrivalDate'] ?? null),
            'guesty' => !empty($data['checkInDateLocalized'] ?? $data['checkIn'] ?? null),
            default => true,
        };
    }

    private function webhookEventId(array $payload): string
    {
        return (string) (
            $payload['meta']['eventId']
            ?? $payload['meta']['messageId']
            ?? $payload['event_id']
            ?? $payload['webhookId']
            ?? md5(json_encode($payload))
        );
    }

    private function extractGuestyListingId(array $payload): ?string
    {
        return $this->extractGuestyListingIds($payload)[0] ?? null;
    }

    private function extractGuestyListingIds(array $payload): array
    {
        $ids = [];
        $single = $payload['listing']['_id']
            ?? $payload['listing']['id']
            ?? $payload['reservation']['listingId']
            ?? $payload['data']['listingId']
            ?? $payload['data']['_id']
            ?? $payload['listingId']
            ?? null;
        if ($single !== null) {
            $ids[] = (string) $single;
        }
        foreach ($payload['calendar'] ?? [] as $calendarEntry) {
            if (is_array($calendarEntry) && !empty($calendarEntry['listingId'])) {
                $ids[] = (string) $calendarEntry['listingId'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Returns true if the PMS status string represents a cancellation.
     * Each PMS uses slightly different spellings/values.
     */
    private function isCancellationStatus(string $status): bool
    {
        return in_array(strtolower(trim($status)), [
            'canceled',   // Guesty (US spelling)
            'cancelled',  // Hostaway, Hostfully
            'declined',   // Lodgify (booking request rejected / cancelled)
        ]);
    }

    /**
     * Full cancellation side-effects for a booking that was originally made on our website.
     *
     * External PMS bookings (from Airbnb/VRBO/etc.) just get their status updated —
     * we never charged them via Stripe so there's nothing to refund/email.
     *
     * Website bookings need:
     *   1. Pending scheduled installments cancelled (stops daily cron from charging)
     *   2. Stripe security deposit released if still authorized
     *   3. Cancellation emails to guest + admin
     */
    private function handleWebsiteBookingCancellation(BookingRequest $booking): void
    {
        try {
            // 1. Cancel pending installments
            $cancelled = ScheduledPayment::where('booking_request_id', $booking->id)
                ->where('status', 'pending')
                ->count();
            if ($cancelled > 0) {
                ScheduledPayment::where('booking_request_id', $booking->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);
                Log::channel('pms_webhooks')->info("handleWebsiteBookingCancellation: {$cancelled} installments cancelled", [
                    'booking_id' => $booking->id,
                ]);
            }

            // 2. Release Stripe security deposit if still authorized
            if (!empty($booking->stripe_deposit_charge_id) && $booking->security_deposit_status === 'authorized') {
                try {
                    \Stripe\Stripe::setApiKey(\ModelHelper::getDataFromSetting('stripe_secret_key'));
                    \App\Helper\StripeHelper::releaseDepositHold($booking->stripe_deposit_charge_id);
                    $booking->security_deposit_status = 'released';
                    $booking->save();
                    Log::channel('pms_webhooks')->info("handleWebsiteBookingCancellation: deposit released", [
                        'booking_id' => $booking->id,
                        'charge_id'  => $booking->stripe_deposit_charge_id,
                    ]);
                } catch (\Throwable $e) {
                    Log::channel('pms_webhooks')->error("handleWebsiteBookingCancellation: deposit release failed", [
                        'booking_id' => $booking->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            // 3. Send cancellation emails
            $room = PmsRoom::find($booking->property_id);
            $property = $room ?? \App\Models\Pms\PmsProperty::find($booking->property_id);
            if ($property) {
                app(\App\Services\Email\BookingLifecycleMailer::class)
                    ->sendCancellation($booking->fresh(), $property);
            }

            Log::channel('pms_webhooks')->info("handleWebsiteBookingCancellation: all steps complete", [
                'booking_id' => $booking->id,
            ]);

        } catch (\Throwable $e) {
            Log::channel('pms_webhooks')->error("handleWebsiteBookingCancellation: unexpected error", [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync all reviews for a PMS when a review webhook fires.
     *
     * Runs pms:sync-reviews --pms=X inline. Fast enough for a webhook window
     * since we only have a handful of properties per PMS (~1-3 API calls).
     * Never throws — webhook 200 is always returned regardless.
     *
     * Lodgify has no reviews API so it is never called for Lodgify.
     */
    private function syncReviewsForPms(string $pmsSource, ?string $propertyId = null): void
    {
        try {
            Log::channel('pms_webhooks')->info("syncReviewsForPms: starting for {$pmsSource}", [
                'property' => $propertyId,
            ]);

            \Illuminate\Support\Facades\Artisan::call('pms:sync-reviews', ['--pms' => $pmsSource]);

            Log::channel('pms_webhooks')->info("syncReviewsForPms: completed for {$pmsSource}");
        } catch (\Throwable $e) {
            Log::channel('pms_webhooks')->error("syncReviewsForPms failed", [
                'pms'   => $pmsSource,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function recordWebhook(string $provider, string $event, string $status, array $payload, ?string $summary = null, ?string $error = null): void
    {
        try {
            $this->ops->recordWebhook($provider, $event, $status, $payload, $summary, $error);
        } catch (\Throwable) {
            // Webhook evidence should never change webhook response behavior.
        }
    }
}
