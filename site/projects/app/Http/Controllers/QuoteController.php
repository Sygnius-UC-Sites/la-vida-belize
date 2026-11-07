<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Pms\PmsRoom;
use App\Modules\VacationRental\Pms\PmsManager;
use App\Helper\QuoteNormalizer;
use App\Helper\BookingPartyValidator;

class QuoteController extends Controller
{
    /**
     * POST /pms-quote
     *
     * Called by the property page sidebar whenever the user selects dates.
     * Resolves the PmsRoom, calls the adapter's getRawQuote(), normalizes,
     * and returns a canonical QuoteResult JSON object.
     *
     * Required params: room_id, checkin (Y-m-d), checkout (Y-m-d)
     * Optional params: adults (int), children (int), pets (int)
     */
    public function get(Request $request): JsonResponse
    {
        // Resolve the room — accept either DB id or pms_room_id
        $room = PmsRoom::find($request->room_id)
             ?? PmsRoom::where('pms_room_id', $request->room_id)->first()
             ?? PmsRoom::where('pms_property_id', $request->room_id)->first();

        if (!$room) {
            return response()->json(['ok' => false, 'error' => 'Property not found'], 200);
        }

        if (empty($room->pms_source)) {
            return response()->json(['ok' => false, 'error' => 'No PMS configured for this property'], 200);
        }

        $checkin  = $request->input('checkin', $request->input('start_date', ''));
        $checkout = $request->input('checkout', $request->input('end_date', ''));

        if (!$checkin || !$checkout) {
            return response()->json(['ok' => false, 'error' => 'Checkin and checkout dates are required'], 200);
        }

        $adults   = (int) $request->input('adults', 1);
        $children = (int) $request->input('children', $request->input('childs', 0));
        $pets     = (int) $request->input('pets', 0);
        $party    = BookingPartyValidator::validate($room, $adults, $children, $pets);

        if (!$party['ok']) {
            return response()->json([
                'ok'         => false,
                'error'      => $party['error'],
                'error_type' => 'party_size',
                'limits'     => $party['limits'],
            ], 200);
        }

        // ── Minimum stay guard (applies to ALL PMS) ───────────────────────────
        // Check before calling the adapter so the user gets a clear, friendly
        // message instead of a raw PMS API error.
        try {
            $nights   = (int)(new \DateTime($checkout))->diff(new \DateTime($checkin))->days;
            $keyFacts = json_decode($room->key_facts_json ?? '[]', true) ?: [];
            $minStay  = (int)($room->custom_min_stay ?: ($keyFacts['min_stay'] ?? 1));
            if ($minStay > 1 && $nights < $minStay) {
                return response()->json([
                    'ok'         => false,
                    'error'      => "Minimum stay is {$minStay} nights. Your selection is {$nights} night"
                                  . ($nights !== 1 ? 's' : '') . ". Please adjust your dates.",
                    'error_type' => 'min_stay',
                    'min_stay'   => $minStay,
                    'nights'     => $nights,
                ]);
            }
        } catch (\Throwable $e) { /* malformed dates — let adapter handle */ }

        try {
            $adapter = app(PmsManager::class)->driver($room->pms_source);

            // Parse optional_services — may arrive as JSON string or array.
            // Used by Hostfully (overrides), Lodgify (addOns), and future PMS.
            $optRaw = $request->input('optional_services', '{}');
            $optionalServices = is_array($optRaw)
                ? $optRaw
                : (json_decode($optRaw, true) ?: []);

            // ── Validate fee IDs BEFORE calling the PMS ──────────────────────
            // This prevents sending unknown/inactive/mandatory IDs to the PMS.
            // FeeValidator returns only confirmed (non-approval) services ready for the PMS.
            // Approval-required services are stored separately and never sent to the quote.
            $validated         = \App\Helper\FeeValidator::validate($optionalServices, $room);
            $confirmedServices = $validated['confirmed'];

            $input = [
                'property_id'       => $room->pms_property_id,
                'room_id'           => $room->pms_room_id,
                'checkin'           => $checkin,
                'checkout'          => $checkout,
                'adults'            => $adults,
                'children'          => $children,
                'infants'           => (int) $request->input('infants', 0),
                'pets'              => $pets,
                'promo_code'        => $request->input('promo_code', ''),
                'optional_services' => $confirmedServices, // validated, not raw browser input
            ];

            $raw   = $adapter->getRawQuote($input);
            $quote = QuoteNormalizer::normalize($raw, $room->pms_source, $input);

            // ── Load canonical fee definitions for this property ─────────────
            // Canonical fees are built from the PMS fee API on sync and stored in
            // optional_fees_json. They provide metadata that the per-quote response
            // doesn't always include (scope, is_per_pet, is_per_guest, is_mandatory,
            // requires_approval) and serve as the authoritative source of available
            // optional services.
            $canonicalFees = json_decode($room->optional_fees_json ?? '[]', true) ?: [];

            // Build lookup map keyed by BOTH the canonical ID (pms_source_pmsId) and
            // the short-form IDs used in QuoteNormalizer (hf_xxx, ha_xxx, lodgify_xxx).
            // This lets us enrich optional_items regardless of which ID format they use.
            $canonicalMap = [];
            foreach ($canonicalFees as $cf) {
                if (!is_array($cf) || empty($cf['pms_id'])) continue;
                $src = $cf['pms_source'] ?? $room->pms_source;
                // Full canonical ID
                $canonicalMap[$src . '_' . $cf['pms_id']] = $cf;
                // Short-form IDs used by QuoteNormalizer
                $short = match ($src) {
                    'hostfully' => 'hf_' . $cf['pms_id'],
                    'hostaway'  => 'ha_' . $cf['pms_id'],
                    'lodgify'   => 'lodgify_' . $cf['pms_id'],
                    'guesty'    => 'guesty_' . $cf['pms_id'],
                    default     => $src . '_' . $cf['pms_id'],
                };
                $canonicalMap[$short] = $cf;
            }

            if ($quote['ok'] ?? false) {
                // ── Enrich optional_items with canonical metadata ─────────────
                // PMS quote responses (especially Hostaway) don't always return
                // scope, is_per_pet, is_per_guest. Canonical fees fill the gap.
                $enriched = [];
                foreach ($quote['optional_items'] ?? [] as $item) {
                    $cf = $canonicalMap[$item['id']] ?? null;
                    if ($cf) {
                        // Override scope/flags with authoritative canonical values
                        $item['scope']                  = $cf['scope']                  ?? $item['scope'];
                        // Rebuild per_unit label from canonical scope
                        $tmpItem = \App\Helper\CanonicalFee::toOptionalItem($cf, 1, 1, 0);
                        $item['per_unit'] = $tmpItem['per_unit'] ?? $item['per_unit'];
                        $item['is_per_pet']             = $cf['is_per_pet']             ?? $item['is_per_pet']     ?? false;
                        $item['is_per_guest']           = $cf['is_per_guest']           ?? $item['is_per_guest']   ?? false;
                        $item['is_quantity_selectable'] = $cf['is_quantity_selectable'] ?? $item['is_quantity_selectable'] ?? false;
                        $item['requires_approval']      = $cf['requires_approval']      ?? $item['requires_approval']     ?? false;
                        $item['min_qty']                = $cf['min_qty']                ?? $item['min_qty']        ?? 0;
                        // Respect canonical max_qty for per-pet (may be larger than quote default of 1)
                        if (($cf['max_qty'] ?? 0) > ($item['max_qty'] ?? 1)) {
                            $item['max_qty'] = $cf['max_qty'];
                        }
                    }
                    // Skip inactive fees — never show to guest
                    if (isset($cf['is_active']) && !$cf['is_active']) continue;
                    $enriched[] = $item;
                }

                // ── For PMS where optional_items is empty (Guesty, Lodgify, calendar fallback) ─
                // Populate directly from canonical fees — BUT only when verified.
                // Unverified fees (_verified=false) must not show controls since the
                // field mapping hasn't been confirmed against a live response.
                // API-blocked markers (_api_blocked=true) are skipped silently.
                if (empty($enriched) && !empty($canonicalFees)) {
                    $nights = $quote['nights'] ?? 1;
                    $guests = ($input['adults'] ?? 1) + ($input['children'] ?? 0);
                    $pets   = $input['pets'] ?? 0;
                    foreach ($canonicalFees as $cf) {
                        if (!is_array($cf)) continue;
                        if ($cf['_api_blocked'] ?? false) continue;   // API blocked marker
                        if (!($cf['is_active'] ?? true))  continue;   // inactive
                        if ($cf['is_mandatory'] ?? false) continue;   // auto-included
                        if (!($cf['is_optional'] ?? true)) continue;
                        // Skip unverified — Lodgify and Guesty fees pending live verification
                        if (array_key_exists('_verified', $cf) && !$cf['_verified']) continue;
                        $enriched[] = \App\Helper\CanonicalFee::toOptionalItem($cf, $nights, $guests, $pets);
                    }
                }

                $quote['optional_items'] = $enriched;

                // ── Surface reconciliation gap ───────────────────────────────
                if (!empty($quote['reconciliation_gap'])) {
                    $gap = (float)$quote['reconciliation_gap'];
                    if (abs($gap) > 0.10) {
                        $quote['line_items'][] = [
                            'label'  => $gap > 0 ? 'Other charges' : 'Other adjustments',
                            'amount' => $gap,
                            'type'   => $gap > 0 ? 'fee' : 'discount',
                        ];
                    }
                }
            }

            // If the PMS quote returned no payment schedule but the room has a
            // deposit_percent configured, build a 2-installment schedule so the
            // property page widget and checkout page always show the deposit split.
            if (($quote['ok'] ?? false) && empty($quote['scheduled_payments']) && !empty($room->deposit_percent)) {
                $pct        = min(100, max(1, (int)$room->deposit_percent));
                $total      = (float)$quote['total'];
                $depositAmt = round($total * $pct / 100, 2);
                $balanceAmt = round($total - $depositAmt, 2);

                // Balance due date: checkin - balance_due_days (e.g. 30 days before check-in).
                // Falls back to check-in date if balance_due_days is not configured.
                $checkin        = $input['checkin'] ?? null;
                $balanceDueDays = $room->balance_due_days ?? null;
                $balanceDueDate = ($checkin && $balanceDueDays)
                    ? date('Y-m-d', strtotime("{$checkin} -{$balanceDueDays} days"))
                    : $checkin;

                if ($depositAmt > 0 && $balanceAmt > 0) {
                    $quote['scheduled_payments'] = [
                        ['type' => 'deposit', 'amount' => $depositAmt, 'date_due' => date('Y-m-d'),
                         'label' => "Deposit ({$pct}% due today)"],
                        ['type' => 'payment', 'amount' => $balanceAmt, 'date_due' => $balanceDueDate,
                         'label' => $balanceDueDays
                            ? "Balance (due {$balanceDueDays} days before check-in)"
                            : 'Balance (due at check-in)'],
                    ];
                }
            }

            // ── Native-style HTML for the property-page widget ──────────────
            // If the host site ships its own quote partial (the markup the old
            // AJAX quote rendered), fill it with the PMS numbers so the widget
            // quote looks EXACTLY like the site's original styling.
            try {
                $partial = 'front.property.ajax-gaurav-data-get-quote';
                if (view()->exists($partial) && ($quote['ok'] ?? false)) {
                    $invoiceItems = [];
                    foreach (($quote['line_items'] ?? []) as $li) {
                        if (($li['type'] ?? '') === 'deposit') continue;
                        $invoiceItems[] = ['title' => $li['label'], 'amount' => (float) ($li['amount'] ?? 0)];
                    }
                    $taxTot = 0.0; $accom = 0.0;
                    foreach (($quote['line_items'] ?? []) as $li) {
                        if (($li['type'] ?? '') === 'tax') $taxTot += (float) $li['amount'];
                        if (($li['type'] ?? '') === 'accommodation') $accom += (float) $li['amount'];
                    }
                    $totalAll = (float) $quote['total'];
                    $legacyMain = [
                        'guestyapi' => ['status' => 200, 'data' => ['_id' => 'pms-' . $room->id,
                            'rates' => ['ratePlans' => [[
                                'ratePlan' => ['_id' => 'pms-' . $room->id],
                                'money' => ['_id' => 'pms-' . $room->id, 'money' => [
                                    'fareAccommodation' => $accom ?: max(0, $totalAll - $taxTot),
                                    'subTotalPrice'     => max(0, $totalAll - $taxTot),
                                    'hostPayout'        => $totalAll,
                                    'totalTaxes'        => $taxTot,
                                    'invoiceItems'      => $invoiceItems,
                                    'currency'          => $quote['currency'] ?? 'USD',
                                ]],
                            ]]],
                        ]],
                        'start_date' => $checkin,
                        'end_date'   => $checkout,
                        'adults'     => $adults,
                        'child'      => $children,
                        'childs'     => $children,
                    ];
                    $quote['html'] = view($partial, ['main_data' => $legacyMain])->render();
                }
            } catch (\Throwable $e) {
                // partial failed — widget falls back to its plain rendering
            }

            return response()->json($quote);

        } catch (\Throwable $e) {
            \Log::channel('lodgify')->error('QuoteController error', [
                'room_id' => $room->id,
                'pms'     => $room->pms_source,
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => 'Could not retrieve quote from PMS'], 200);
        }
    }
}
