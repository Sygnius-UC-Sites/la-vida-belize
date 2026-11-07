<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Booking\QuoteCalculator;
use App\Services\Booking\QuoteInput;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingQuoteController extends Controller
{
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_id'    => 'required|integer|exists:properties,id',
            'check_in'       => 'required|date',
            'check_out'      => 'required|date',
            'adults'         => 'required|integer|min:1',
            'children'       => 'nullable|integer|min:0',
            'pets'           => 'nullable|integer|min:0',
            'addons'         => 'nullable|array',
            // Phase 3.1: addons.fees is the canonical generic-fees array; legacy typed keys kept for shim compat
            'addons.fees'    => 'nullable|array',
            'addons.fees.*'  => 'integer|min:1',
            'addons.hottub'  => 'nullable|boolean',
            'addons.firewood'        => 'nullable|integer|min:0',
            'addons.rv_hookup'       => 'nullable|integer|min:0',
            'addons.extra_visitors'  => 'nullable|integer|min:0',
            'coupon_code'    => 'nullable|string|max:255',
            'customer_email' => 'nullable|email',
        ]);

        $input = QuoteInput::fromArray($validated);
        $calc = new QuoteCalculator();
        $quote = $calc->calculate($input);

        return response()->json([
            'valid'  => $quote->valid,
            'quote'  => $quote->toArray(),
            'errors' => $quote->errors,
        ]);
    }
}
