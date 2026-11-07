<?php

namespace App\Http\Controllers\Admin;

use App\Helper\LiveCart;
use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatepickerController extends Controller
{
    public function blockedDates(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
        ]);

        $propertyId = (int) $request->query('property_id');
        $property = Property::findOrFail($propertyId);

        $data = (new LiveCart())->iCalDataCheckInCheckOutCheckinCheckout($propertyId);

        return response()->json([
            'blocked' => array_values(array_unique($data['blocked'] ?? [])),
            'checkin' => array_values(array_unique($data['checkin'] ?? [])),
            'checkout' => array_values(array_unique($data['checkout'] ?? [])),
            'min_stay' => (int) ($property->min_stay ?? 0),
            'max_stay' => isset($property->max_stay) && $property->max_stay ? (int) $property->max_stay : null,
        ]);
    }
}
