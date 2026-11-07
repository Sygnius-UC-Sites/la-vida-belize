<?php

namespace App\Http\Controllers;

/**
 * PMS MODULE — self-contained front controller for the PMS booking funnel.
 *
 * This controller is ADDITIVE: it never modifies the host site's PageController.
 * Its routes are registered by PmsServiceProvider BEFORE the site's own routes,
 * so the property-detail URL is intercepted here first. Anything that is not a
 * PMS room is delegated back to the site's own controller untouched.
 *
 * Funnel: propertyDetail (single-pms view) → getQuote (pms-get-quote view)
 *         → saveBookingData → PmsStripeController (pms/booking/payment/{id})
 */

use Illuminate\Http\Request;
use App\Models\Cms;
use App\Models\BookingRequest;
use App\Models\Property;
use App\Models\Pms\PmsRoom;
use App\Models\Pms\PmsRate;

class PmsPageController extends Controller
{

    /**
     * Render the module's own property page for a PMS room (single-pms view).
     * Public so InjectPmsWidget can use it as a 404 fallback in widget mode —
     * for rooms the host site has no page of its own for (new PMS listings).
     */
    public function renderRoom(PmsRoom $data)
    {
        $frontendData  = [];
        $missingImages = empty($data->images_json) || $data->images_json === '[]';
        $missingDesc   = empty(trim(strip_tags((string) ($data->description ?? ''))));
        if ($missingImages && $missingDesc && !empty($data->pms_source) && !empty($data->pms_property_id)) {
            try {
                $adapter      = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver($data->pms_source);
                $frontendData = $adapter->getFrontendData($data->pms_property_id);
            } catch (\Throwable $e) {
                \Log::error('PMS renderRoom fallback failed: ' . $e->getMessage());
                $frontendData = [];
            }
        }
        return view('front.property.single-pms', compact('data', 'frontendData'));
    }

    /**
     * Intercepts the site's property detail URL.
     * PMS room → unified single-pms template. Anything else → delegate to the
     * site's existing PageController so CRM properties keep their old page.
     */
    public function propertyDetail($seo_url)
    {
        if ($this->sitePropertyExists($seo_url)) {
            return $this->delegateToSiteController($seo_url);
        }

        $data = PmsRoom::where('seo_url', $seo_url)
            ->where('status', 'true')
            ->first();

        if ($data) {
            // DB-FIRST: render from synced columns. Only re-normalize the stored
            // raw payload when the record is missing BOTH images and description.
            $frontendData  = [];
            $missingImages = empty($data->images_json) || $data->images_json === '[]';
            $missingDesc   = empty(trim(strip_tags((string) ($data->description ?? ''))));
            if ($missingImages && $missingDesc && !empty($data->pms_source) && !empty($data->pms_property_id)) {
                try {
                    $adapter      = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver($data->pms_source);
                    $frontendData = $adapter->getFrontendData($data->pms_property_id);
                } catch (\Throwable $e) {
                    \Log::error('PMS propertyDetail fallback failed: ' . $e->getMessage());
                    $frontendData = [];
                }
            }
            return view('front.property.single-pms', compact('data', 'frontendData'));
        }

        // Not a PMS room → original site behaviour, completely unchanged.
        return $this->delegateToSiteController($seo_url);
    }

    /**
     * Call the host site's own property detail method, whatever it is named.
     */
    protected function sitePropertyExists($seo_url): bool
    {
        return Property::where('seo_url', $seo_url)->where('status', 'true')->exists();
    }

    protected function delegateToSiteController($seo_url)
    {
        $controller = app(\App\Http\Controllers\PageController::class);
        foreach (['propertyDetail', 'propertySingle'] as $method) {
            if (method_exists($controller, $method)) {
                return $controller->{$method}($seo_url);
            }
        }
        return abort(404);
    }

    /**
     * Intercepts the site's root catch-all (GET /{seo_url}) — on most sites in
     * this codebase family that is the REAL property page URL (handled by
     * PageController@dynamicDataCategory, which renders the old Guesty page).
     *
     * PMS room slug → unified single-pms view (new booking flow).
     * Any other slug (CMS pages, landing pages, blogs…) → delegated to the
     * site's own catch-all, completely unchanged.
     */
    public function catchAll(Request $request, $seo_url)
    {
        if ($this->sitePropertyExists($seo_url)) {
            return $this->delegateToSiteController($seo_url);
        }

        $data = PmsRoom::where('seo_url', $seo_url)
            ->where('status', 'true')
            ->first();

        if ($data) {
            $frontendData  = [];
            $missingImages = empty($data->images_json) || $data->images_json === '[]';
            $missingDesc   = empty(trim(strip_tags((string) ($data->description ?? ''))));
            if ($missingImages && $missingDesc && !empty($data->pms_source) && !empty($data->pms_property_id)) {
                try {
                    $adapter      = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver($data->pms_source);
                    $frontendData = $adapter->getFrontendData($data->pms_property_id);
                } catch (\Throwable $e) {
                    \Log::error('PMS catchAll fallback failed: ' . $e->getMessage());
                    $frontendData = [];
                }
            }
            return view('front.property.single-pms', compact('data', 'frontendData'));
        }

        // Not a PMS room → site's own catch-all behaviour, untouched.
        $controller = app(\App\Http\Controllers\PageController::class);
        if (method_exists($controller, 'dynamicDataCategory')) {
            return $controller->dynamicDataCategory($request, $seo_url);
        }
        return abort(404);
    }

    /**
     * PMS quote page (replaces the old get-quote CMS funnel page for PMS rooms).
     * GET pms/get-quote?property_id=&start_date=&end_date=&adults=&childs=...
     */
    public function getQuote(Request $request)
    {
        // Page chrome (title/meta) comes from the site's existing get-quote CMS
        // row when present, so the design stays identical to the host site.
        $data = Cms::where('seo_url', 'get-quote')->first();
        if (!$data) {
            $data                   = new \stdClass();
            $data->name             = 'Get a Quote';
            $data->meta_title       = 'Get a Quote';
            $data->meta_keywords    = '';
            $data->meta_description = '';
            $data->templete         = 'get-quote';
            // The quote blades read these directly — without them the page 500s
            // ("Undefined property: stdClass::$image") on sites that have no
            // 'get-quote' CMS row.
            $data->image            = null;
            $data->bannerImage      = null;
            $data->header_section   = '';
            $data->footer_section   = '';
        }

        if (!$request->property_id) {
            return redirect()->back()->with('danger', 'Invalid Property');
        }

        $property = PmsRoom::find($request->property_id);
        if (!$property) {
            $property = PmsRoom::where('pms_property_id', $request->property_id)->first();
        }
        if (!$property) {
            $property = PmsRoom::where('pms_room_id', $request->property_id)->first();
        }
        if (!$property) {
            return redirect()->back()->with('danger', 'Invalid Property');
        }

        $party = \App\Helper\BookingPartyValidator::validate(
            $property,
            (int) $request->get('adults', 1),
            (int) $request->get('child', $request->get('childs', 0)),
            (int) $request->get('no_of_pets', 0)
        );
        if (!$party['ok']) {
            return redirect('stay/' . $property->seo_url)->with('danger', $party['error']);
        }

        if (!$request->start_date) {
            return redirect('stay/' . $property->seo_url)->with('danger', 'Invalid Checkin');
        }
        if (!$request->end_date) {
            return redirect('stay/' . $property->seo_url)->with('danger', 'Invalid Checkout');
        }

        $now      = strtotime($request->start_date);
        $yourDate = strtotime($request->end_date);
        $day      = ceil(($yourDate - $now) / (60 * 60 * 24));

        // Min-stay check from synced rate rows (when available)
        $minimumStay = 0;
        $date        = date('Y-m-d', strtotime($request->get('start_date')));
        foreach (PmsRate::where(['property_id' => $property->id, 'single_date' => $date])->get() as $c) {
            if ($minimumStay < $c->min_stay) {
                $minimumStay = $c->min_stay;
            }
        }
        if ($minimumStay > $day) {
            return redirect('stay/' . $property->seo_url)->with('danger', 'Minimum stay criteria not met, Min Stay :-' . $minimumStay);
        }

        if (empty($property->pms_source)) {
            return redirect('stay/' . $property->seo_url)->with('danger', 'Pricing unavailable for this property.');
        }

        try {
            // Validate optional_services BEFORE calling the PMS
            $rawOptional       = json_decode($request->get('optional_services', '{}'), true) ?: [];
            $validated         = \App\Helper\FeeValidator::validate($rawOptional, $property);
            $confirmedServices = $validated['confirmed'];

            // Re-validate any previously-carried requested_services (approval-only).
            $prevRequested   = json_decode($request->get('requested_services', '{}'), true) ?: [];
            $mergedRequested = $validated['requested'] ?? [];
            if (!empty($prevRequested)) {
                $canonicalFees = json_decode($property->optional_fees_json ?? 'null', true);
                if (!empty($canonicalFees)) {
                    $cfMap = \App\Helper\FeeValidator::buildMap($canonicalFees, $property->pms_source ?? '');
                    foreach ($prevRequested as $id => $qty) {
                        $cf = $cfMap[(string) $id] ?? null;
                        if ($cf && ($cf['requires_approval'] ?? false) && ($cf['is_active'] ?? true)) {
                            $mergedRequested[(string) $id] = max(1, (int) $qty);
                        }
                    }
                }
            }

            $adapter  = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver($property->pms_source);
            $rawQuote = $adapter->getRawQuote([
                'property_id'       => $property->pms_property_id,
                'room_id'           => $property->pms_room_id,
                'checkin'           => $request->start_date,
                'checkout'          => $request->end_date,
                'adults'            => (int) $request->get('adults', 1),
                'children'          => (int) $request->get('child', $request->get('childs', 0)),
                'infants'           => (int) $request->get('infants', 0),
                'pets'              => (int) $request->get('no_of_pets', 0),
                'promo_code'        => $request->get('discount_coupon', ''),
                'optional_services' => $confirmedServices,
            ]);
            $quote = \App\Helper\QuoteNormalizer::normalize($rawQuote, $property->pms_source, [
                'checkin'  => $request->start_date,
                'checkout' => $request->end_date,
            ]);
        } catch (\Throwable $e) {
            return redirect('stay/' . $property->seo_url)->with('danger', 'Could not fetch pricing. Please try again.');
        }

        if (!($quote['ok'] ?? false)) {
            return redirect('stay/' . $property->seo_url)->with('danger', $quote['error'] ?? 'Pricing unavailable for those dates.');
        }

        // Build data in the exact shape pms-get-quote.blade.php expects.
        $beforeFees = [];
        $afterFees  = [];
        $taxTotal   = 0.0;
        foreach ($quote['line_items'] as $li) {
            $type   = $li['type'] ?? '';
            $amount = (float) ($li['amount'] ?? 0);
            if ($type === 'deposit') {
                continue; // shown separately as security deposit
            }
            if ($type === 'tax') {
                $taxTotal += $amount;
            }
            $beforeFees[] = [
                'type'   => $type,
                'prices' => [['description' => $li['label'], 'amount' => $amount]],
            ];
        }

        // Apply deposit_percent fallback if PMS quote returned no payment schedule.
        $scheduledPayments = $quote['scheduled_payments'] ?? [];
        if (empty($scheduledPayments) && !empty($property->deposit_percent)) {
            $pct            = min(100, max(1, (int) $property->deposit_percent));
            $total          = (float) $quote['total'];
            $depositAmt     = round($total * $pct / 100, 2);
            $balanceAmt     = round($total - $depositAmt, 2);
            $checkin        = $request->start_date ?? '';
            $balanceDueDays = $property->balance_due_days ?? null;
            $balanceDue     = ($checkin && $balanceDueDays)
                ? date('Y-m-d', strtotime("{$checkin} -{$balanceDueDays} days"))
                : $checkin;
            if ($depositAmt > 0 && $balanceAmt > 0) {
                $scheduledPayments = [
                    ['type' => 'deposit', 'amount' => $depositAmt,
                     'date_due' => date('Y-m-d'),
                     'label' => "Deposit ({$pct}% due today)"],
                    ['type' => 'payment', 'amount' => $balanceAmt,
                     'date_due' => $balanceDue,
                     'label' => $balanceDueDays
                        ? "Balance (due {$balanceDueDays} days before check-in)"
                        : 'Balance (due at check-in)'],
                ];
            }
        }

        $main_data = [
            'status'    => 'true',
            'addonData' => ['status' => 400, 'data' => 'Not'],
            'data'      => [[
                'amount_gross'            => $quote['total'],
                'total_vat'               => $taxTotal,
                'add_ons'                 => $afterFees,
                'room_types'              => [['price_types' => $beforeFees]],
                'scheduled_payments'      => $scheduledPayments,
                'security_deposit'        => $quote['security_deposit'] ?? null,
                'deposit_in_total'        => $quote['deposit_in_total'] ?? false,
                'deposit_is_preauth'      => $quote['deposit_is_preauth'] ?? true,
                'optional_items'          => $quote['optional_items'] ?? [],
                'currency'                => $quote['currency'] ?? 'USD',
                'nights'                  => $quote['nights'] ?? 1,
                'applied_coupon'          => $request->get('discount_coupon', ''),
                'manual_collection'       => $quote['manual_collection'] ?? null,
                'optional_services_json'  => json_encode($confirmedServices ?? []),
                'requested_services_json' => json_encode($mergedRequested),
            ]],
        ];

        $main_data['total_night']          = $day;
        $main_data['total_guests']         = (int) $request->get('adults', 1)
            + (int) $request->get('child', $request->get('childs', 0));
        $main_data['adults']               = $request->get('adults');
        $main_data['child']                = $request->get('child', $request->get('childs', 0));
        $main_data['start_date']           = $request->get('start_date');
        $main_data['end_date']             = $request->get('end_date');
        $main_data['pet_fee_data_guarav']  = $request->get('no_of_pets');
        $main_data['heating_pool_fee']     = $request->get('heating_pool_fee');

        return $this->renderUnifiedCheckout($property, $quote, $main_data, $confirmedServices ?? [], $mergedRequested ?? []);
    }

    private function renderUnifiedCheckout(
        PmsRoom $property,
        array $normalizedQuote,
        array $mainData,
        array $confirmedServices,
        array $requestedServices
    ) {
        $cms = Cms::firstOrCreate(
            ['seo_url' => 'checkout'],
            ['name' => \Illuminate\Support\Str::headline('checkout'), 'templete' => 'checkout', 'publish' => 'published']
        );

        $context = new \App\Helpers\BookingContext();
        $context->check_in  = $mainData['start_date'] ?? null;
        $context->check_out = $mainData['end_date'] ?? null;
        $context->adults    = max(1, (int) ($mainData['adults'] ?? 1));
        $context->children  = max(0, (int) ($mainData['child'] ?? 0));
        $context->pets      = max(0, (int) ($mainData['pet_fee_data_guarav'] ?? 0));

        $quote = $this->pmsQuoteForCheckout($normalizedQuote);

        $enabledGateways = ['stripe'];
        $cardGateway     = 'stripe';
        $paypalEnabled   = false;
        $addons          = ['fees' => []];
        $allFees         = collect();
        $widgetBlocked   = $this->pmsCalendarForCheckout($property);
        $cancelType      = 'pms';
        $cancelText      = $property->cancellation_policy ?: $property->booking_policy;
        $checkoutMode    = 'pms';

        $checkoutPropertyUrl   = url($property->seo_url);
        $checkoutPreviewImage  = $this->pmsPreviewImage($property);
        $checkoutPropertyType  = $property->property_type ?: 'Stay';
        $checkoutCurrency      = $normalizedQuote['currency'] ?? $property->currency ?? 'USD';
        $pmsCheckoutPayload    = [
            'optional_services'  => json_encode($confirmedServices),
            'requested_services' => json_encode($requestedServices),
            'discount_coupon'    => $mainData['data'][0]['applied_coupon'] ?? '',
        ];

        return response()->view('front.static.checkout', compact(
            'property', 'cms', 'context', 'addons', 'quote',
            'enabledGateways', 'cardGateway', 'paypalEnabled',
            'cancelType', 'cancelText',
            'allFees', 'widgetBlocked', 'checkoutMode',
            'checkoutPropertyUrl', 'checkoutPreviewImage', 'checkoutPropertyType',
            'checkoutCurrency', 'pmsCheckoutPayload'
        ))->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    private function pmsQuoteForCheckout(array $normalizedQuote): \App\Services\Booking\Quote
    {
        $quote = new \App\Services\Booking\Quote();
        $quote->valid  = (bool) ($normalizedQuote['ok'] ?? false);
        $quote->nights = (int) ($normalizedQuote['nights'] ?? 0);
        $quote->total  = (float) ($normalizedQuote['total'] ?? 0);

        $tax = 0.0;
        foreach (($normalizedQuote['line_items'] ?? []) as $line) {
            $type = $line['type'] ?? '';
            $amount = (float) ($line['amount'] ?? 0);
            if ($type === 'tax') {
                $tax += $amount;
                continue;
            }
            if ($type === 'deposit') {
                continue;
            }
            $quote->breakdown[] = [
                'label'      => $line['label'] ?? ucfirst($type ?: 'Charge'),
                'amount'     => $amount,
                'taxable'    => false,
                'refundable' => false,
                'category'   => $type ?: 'fee',
            ];
        }

        $quote->tax      = $tax;
        $quote->subtotal = max(0, $quote->total - $tax);

        if (!empty($normalizedQuote['security_deposit']) && !empty($normalizedQuote['deposit_in_total'])) {
            $quote->damage_deposit = (float) $normalizedQuote['security_deposit'];
        }

        $sequence = 1;
        foreach (($normalizedQuote['scheduled_payments'] ?? []) as $payment) {
            $quote->installments[] = [
                'due_date' => $payment['date_due'] ?: now()->toDateString(),
                'amount'   => (float) ($payment['amount'] ?? 0),
                'sequence' => $sequence++,
                'label'    => $payment['label'] ?? 'Payment',
            ];
        }

        return $quote;
    }

    private function pmsPreviewImage(PmsRoom $property): ?string
    {
        if (!empty($property->banner_image)) return $property->banner_image;
        if (!empty($property->image_url)) return $property->image_url;

        $images = json_decode($property->images_json ?? '[]', true) ?: [];
        $first = $images[0] ?? null;
        if (is_string($first)) return $first;
        if (is_array($first)) return $first['url'] ?? $first['image'] ?? null;

        return null;
    }

    private function pmsCalendarForCheckout(PmsRoom $property): array
    {
        $checkin = [];
        $checkout = [];
        $blocked = [];

        if (!\Illuminate\Support\Facades\Schema::hasTable('pms_calendar')) {
            return ['checkin' => [], 'checkout' => [], 'blocked' => []];
        }

        $closedDays = \Illuminate\Support\Facades\DB::table('pms_calendar')
            ->where('pms_source', $property->pms_source)
            ->where('pms_room_id', $property->pms_room_id)
            ->where('state', 0)
            ->where('date', '>=', date('Y-m-d'))
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($date) => date('Y-m-d', strtotime($date)))
            ->unique()
            ->values()
            ->all();

        $run = [];
        $flushRun = function () use (&$run, &$checkin, &$checkout, &$blocked) {
            if (empty($run)) return;
            $checkin[] = $run[0];
            $checkout[] = date('Y-m-d', strtotime(end($run) . ' +1 day'));
            foreach (array_slice($run, 1) as $interior) {
                $blocked[] = $interior;
            }
            $run = [];
        };

        foreach ($closedDays as $day) {
            if (!empty($run) && $day !== date('Y-m-d', strtotime(end($run) . ' +1 day'))) {
                $flushRun();
            }
            $run[] = $day;
        }
        $flushRun();

        $turnovers = array_intersect($checkin, $checkout);
        $blocked = array_merge($blocked, $turnovers);
        $checkin = array_values(array_diff($checkin, $turnovers));
        $checkout = array_values(array_diff($checkout, $turnovers));

        sort($checkin);
        sort($checkout);
        $blocked = array_values(array_unique($blocked));
        sort($blocked);

        return ['checkin' => $checkin, 'checkout' => $checkout, 'blocked' => $blocked];
    }

    /**
     * Save the booking request from the quote page, then route to payment.
     * POST pms/save-booking-data
     */
    public function saveBookingData(Request $request)
    {
        // pms_room_db_id is injected by the funnel script when the SITE's own
        // quote page is used (its property_id is the site model's id, not ours).
        $property = null;
        if ($request->pms_room_db_id) {
            $property = PmsRoom::find($request->pms_room_db_id);
        }
        if (!$property && $request->property_id) {
            $property = PmsRoom::find($request->property_id)
                ?: PmsRoom::where('pms_room_id', $request->property_id)->first()
                ?: PmsRoom::where('pms_property_id', $request->property_id)->first();
        }
        if (!$property) {
            return $this->bookingFail($request, 'Invalid Property');
        }
        if (!$request->checkin || !$request->checkout) {
            return $this->bookingFail($request, 'Please select check-in and check-out dates');
        }

        // ── Guest and pet validation ──────────────────────────────────────────
        $party = \App\Helper\BookingPartyValidator::validate(
            $property,
            (int) ($request->adults ?? 1),
            (int) ($request->child ?? 0),
            (int) ($request->pet ?? 0)
        );
        if (!$party['ok']) {
            return $this->bookingFail($request, $party['error']);
        }

        // ── Step 1: Fetch live quote from PMS ─────────────────────────────────
        try {
            $adapter = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver($property->pms_source);

            // FeeValidator strips unknown, inactive, mandatory, and tampered IDs.
            // NULL canonical → fail-closed (all IDs rejected until sync runs).
            $submittedServices = json_decode($request->input('optional_services', '{}'), true) ?: [];
            $feeValidation     = \App\Helper\FeeValidator::validate($submittedServices, $property);
            $confirmedServices = $feeValidation['confirmed'];   // safe to send to PMS
            $requestedServices = $feeValidation['requested'];   // requires_approval only, never sent to PMS

            // Also validate the explicit requested_services input (hidden field from blade).
            $explicitRequested = json_decode($request->input('requested_services', '{}'), true) ?: [];
            if (!empty($explicitRequested)) {
                $canonicalFees = json_decode($property->optional_fees_json ?? 'null', true);
                if (!empty($canonicalFees)) {
                    $cfMap = \App\Helper\FeeValidator::buildMap($canonicalFees, $property->pms_source ?? '');
                    foreach ($explicitRequested as $id => $qty) {
                        $cf = $cfMap[(string) $id] ?? null;
                        if ($cf && ($cf['requires_approval'] ?? false) && ($cf['is_active'] ?? true)) {
                            $requestedServices[(string) $id] = max(1, (int) $qty);
                        }
                    }
                }
            }

            $rawQuote = $adapter->getRawQuote([
                'property_id'       => $property->pms_property_id,
                'room_id'           => $property->pms_room_id,
                'checkin'           => $request->checkin,
                'checkout'          => $request->checkout,
                'adults'            => (int) ($request->adults ?? $request->total_guests ?? 1),
                'children'          => (int) ($request->child ?? 0),
                'infants'           => (int) ($request->infants ?? 0),
                'pets'              => (int) ($request->pet ?? 0),
                'promo_code'        => $request->input('discount_coupon', ''),
                'optional_services' => $confirmedServices,
            ]);
            $quote = \App\Helper\QuoteNormalizer::normalize(
                $rawQuote,
                $property->pms_source,
                ['checkin' => $request->checkin, 'checkout' => $request->checkout]
            );
        } catch (\Throwable $e) {
            return $this->bookingFail($request, 'Could not fetch pricing. Please try again.');
        }

        if (!($quote['ok'] ?? false)) {
            return $this->bookingFail($request, 'Pricing unavailable for the selected dates. Please try different dates.');
        }

        // ── Step 2: Build BookingRequest fields from PMS quote ────────────────
        $lineItems = $quote['line_items'] ?? [];
        $nights    = $quote['nights'] ?? 1;
        $currency  = $quote['currency'] ?? 'USD';
        $totalAmt  = (float) $quote['total'];

        $grossAmt = 0.0;
        foreach ($lineItems as $li) {
            if (($li['type'] ?? '') === 'accommodation') {
                $grossAmt += (float) $li['amount'];
            }
        }
        if ($grossAmt == 0) {
            $grossAmt = $totalAmt; // fallback
        }

        $taxTotal = 0.0;
        foreach ($lineItems as $li) {
            if (($li['type'] ?? '') === 'tax') {
                $taxTotal += (float) $li['amount'];
            }
        }
        $subAmt = $totalAmt - $taxTotal;

        // Optional add-ons selected by guest. STORAGE FORMAT: {"provider_fee_id": quantity}
        $optionalServices = $confirmedServices;

        // before_total_fees: fee line items shown ABOVE the tax row.
        // Excludes 'tax' (rendered once via the dedicated tax row + tax column)
        // and 'deposit' (shown separately as a refundable security deposit).
        // NOTE: tax MUST NOT live here — the email and admin booking templates
        // render before_total_fees AND a separate tax row, so including tax here
        // makes tax appear 2-3x and the breakdown stops summing to the total.
        $beforeFees = [];
        foreach ($lineItems as $li) {
            $liType = $li['type'] ?? '';
            if ($liType === 'deposit' || $liType === 'tax') {
                continue;
            }
            $beforeFees[] = [
                'name'   => $li['label'],
                'value'  => (float) $li['amount'],
                // Duplicate as 'amount' — the site's admin views and PDF emailer
                // read $c->amount (native booking shape). Keeping both keys lets
                // every consumer work without per-view shape detection.
                'amount' => (float) $li['amount'],
                'type'   => $liType,
            ];
        }

        // after_total_fees: intentionally EMPTY for PMS bookings. Tax is shown
        // exactly once via the dedicated tax row (driven by the 'tax' column).
        // PMS quotes have no genuine "post-tax fee" concept, and populating this
        // with tax rows duplicated the tax in every legacy template.
        $afterFees = [];

        // Payment schedule → amount_data for installment tracking.
        $scheduledPayments = $quote['scheduled_payments'] ?? [];
        $amountData        = [];
        $installmentTypes  = ['payment', 'deposit', 'balance'];
        foreach ($scheduledPayments as $sp) {
            if (in_array(strtolower($sp['type'] ?? ''), $installmentTypes)) {
                $amountData[] = [
                    'amount'     => (float) $sp['amount'],
                    'date_due'   => $sp['date_due'] ?? $sp['due_date'] ?? $sp['date'] ?? '',
                    'label'      => $sp['label'] ?? '',
                    'is_current' => $sp['is_current'] ?? false,
                ];
            }
        }

        if (empty($amountData) && !empty($property->deposit_percent)) {
            $pct        = min(100, max(1, (int) $property->deposit_percent));
            $depositAmt = round($totalAmt * $pct / 100, 2);
            $balanceAmt = round($totalAmt - $depositAmt, 2);

            $checkin        = $request->checkin ?? '';
            $balanceDueDays = $property->balance_due_days ?? null;
            $balanceDue     = ($checkin && $balanceDueDays)
                ? date('Y-m-d', strtotime("{$checkin} -{$balanceDueDays} days"))
                : $checkin;

            $amountData = [
                ['amount' => $depositAmt, 'date_due' => date('Y-m-d'),
                 'label'  => "Deposit payment ({$pct}% due today)", 'is_current' => true],
                ['amount' => $balanceAmt, 'date_due' => $balanceDue,
                 'label'  => $balanceDueDays
                    ? "Balance payment (due {$balanceDueDays} days before check-in)"
                    : 'Balance payment (due at check-in)', 'is_current' => false],
            ];
        }

        // deposit_due_amount = amount the guest must pay today.
        if (!empty($amountData)) {
            $currentRows      = array_filter($amountData, fn ($r) => $r['is_current'] ?? false);
            $depositDueAmount = !empty($currentRows)
                ? (float) array_sum(array_column($currentRows, 'amount'))
                : (float) $amountData[0]['amount'];
        } else {
            $depositDueAmount = $totalAmt;
        }

        $adults  = (int) ($request->adults ?? 1);
        $child   = (int) ($request->child ?? 0);
        $infants = (int) ($request->infants ?? 0);

        $data = [
            'property_id'        => $property->id,
            'pms_source'         => $property->pms_source,
            'pms_room_id'        => $property->pms_room_id,
            'checkin'            => $request->checkin,
            'checkout'           => $request->checkout,
            'adults'             => $adults,
            'child'              => $child,
            'infants'            => $infants,
            'total_guests'       => $adults + $child,
            'total_pets'         => (int) ($request->pet ?? 0),
            'total_night'        => $nights,
            'gross_amount'       => $grossAmt,
            'sub_amount'         => $subAmt,
            'total_amount'       => $totalAmt,
            'deposit_due_amount' => $depositDueAmount,
            'tax'                => $taxTotal,
            'currency'           => $currency,
            'before_total_fees'  => json_encode($beforeFees),
            'after_total_fees'   => json_encode($afterFees),
            'amount_data'        => json_encode($amountData),
            'optional_services'  => json_encode($optionalServices),
            'requested_services' => json_encode($requestedServices),
            'discount_coupon'    => $request->discount_coupon ?? '',
            'request_id'         => $request->request_id ?? uniqid('pms_'),
            'name'               => trim(($request->first_name ?? $request->firstname ?? '') . ' ' . ($request->last_name ?? $request->lastname ?? '')),
            'first_name'         => $request->first_name ?? $request->firstname ?? '',
            'last_name'          => $request->last_name ?? $request->lastname ?? '',
            'email'              => $request->email ?? '',
            'mobile'             => $request->mobile ?? $request->phone ?? '',
            'message'            => $request->message ?? '',
            'booking_status'     => 'pending-payment',
            'payment_status'     => 'pending',
        ];

        // Merge any extra fields the quote form still sends
        foreach ($request->except([
            '_token', 'operation', 'first_name', 'last_name', 'phone',
            'firstname', 'lastname', 'pms_ajax', 'pms_room_db_id',
            // SECURITY: raw card fields from legacy forms must never be stored.
            'card_number', 'card_cvv', 'card_expiry_month', 'card_expiry_year',
        ]) as $k => $v) {
            if (!isset($data[$k])) {
                $data[$k] = $v;
            }
        }

        $existing = BookingRequest::where('request_id', $request->request_id)->first();
        if ($existing) {
            $existing->update($data);
            $booking = $existing->fresh();
        } else {
            $booking = BookingRequest::create($data);
        }

        // ── AJAX mode (site's own quote page) ────────────────────────────────
        // The injected funnel script posts pms_ajax=1 and completes payment on
        // the SAME page via Stripe Elements: it fetches the PaymentIntent from
        // the payment endpoint (?pms_json=1) and confirms client-side.
        if ($request->input('pms_ajax')) {
            if (empty($booking->payment_token)) {
                $booking->forceFill(['payment_token' => \Illuminate\Support\Str::random(48)])->save();
            }
            return response()->json([
                'status'           => 200,
                'message'          => 'Booking saved — confirming payment…',
                'booking_id'       => $booking->id,
                'payment_json_url' => url("pms/booking/payment/{$booking->id}") . '?pms_json=1&token=' . $booking->payment_token,
                'payment_page_url' => url("pms/booking/payment/{$booking->id}") . '?token=' . $booking->payment_token,
            ]);
        }

        // ── Step 3: Route by payment processor — NOT by PMS ─────────────────
        $paymentProcessor = \ModelHelper::getDataFromSetting('payment_processor') ?? 'stripe';

        if ($paymentProcessor === 'lodgify_payments' && $property->pms_source === 'lodgify') {
            return $this->saveLodgifyPaymentLink($property, $booking);
        }

        // Square only when the host site actually has the Square controller.
        if ($paymentProcessor === 'square'
            && class_exists(\App\Http\Controllers\Payment\SquarePaymentController::class)) {
            return redirect()->to('booking/payment/square/' . $booking->id);
        }

        return redirect()->to('pms/booking/payment/' . $booking->id); // Stripe (default)
    }

    /**
     * Lodgify Payments flow — only when payment_processor = 'lodgify_payments'.
     */
    private function saveLodgifyPaymentLink(PmsRoom $room, BookingRequest $booking): \Illuminate\Http\RedirectResponse
    {
        $bookingService = app(\App\Services\PmsBookingService::class);

        if ($bookingService->alreadyCreated($booking)) {
            $quoteData = json_decode($booking->quote_data_all ?? '{}', true);
            if (!empty($quoteData['url'])) {
                return redirect()->away($quoteData['url']);
            }
        }

        $result = $bookingService->create($booking);

        if (!$result['ok']) {
            return redirect()->back()->with('danger', $result['error'] ?? 'Booking failed. Please try again.');
        }

        $adapter    = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver('lodgify');
        $linkResult = method_exists($adapter, 'getPaymentLink')
            ? $adapter->getPaymentLink($result['pms_booking_id'])
            : [];

        if (!empty($linkResult['url'])) {
            $booking->update(['quote_data_all' => json_encode($linkResult)]);
            return redirect()->away($linkResult['url']);
        }

        // Lodgify payment link unavailable — fall back to Stripe
        return redirect()->to('pms/booking/payment/' . $booking->id);
    }

    /** Error response that honours both classic redirects and the ajax funnel. */
    private function bookingFail(Request $request, string $message)
    {
        if ($request->input('pms_ajax') || $request->wantsJson()) {
            return response()->json(['status' => 400, 'message' => $message]);
        }
        return redirect()->back()->with('danger', $message);
    }

    /**
     * Booking preview page. GET pms/booking/preview/{id}
     */
    public function previewBooking(Request $request, $id)
    {
        $booking = BookingRequest::find($id);
        if ($booking) {
            $property = PmsRoom::find($booking->property_id);
            if (!$property) {
                $property = PmsRoom::where('pms_property_id', $booking->property_id)
                    ->orWhere('pms_room_id', $booking->property_id)
                    ->first();
            }
            if ($property) {
                $data                   = new \stdClass();
                $data->name             = 'Booking Request';
                $data->meta_title       = 'Booking Request';
                $data->meta_keywords    = 'Booking Request';
                $data->meta_description = 'Booking Request';
                return view('front.booking.pms-preview', compact('booking', 'data', 'property'));
            }
        }
        return abort(404);
    }
}
