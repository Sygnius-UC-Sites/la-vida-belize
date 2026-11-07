<?php

namespace App\Services\Booking;

use App\Models\BookingRequest;
use App\Models\Pms\PmsRoom;
use App\Models\Property;
use Illuminate\Database\Eloquent\Model;

final class RentalAgreementPropertyResolver
{
    public function resolve(BookingRequest $booking): ?Model
    {
        if ($this->looksLikePmsBooking($booking)) {
            $room = PmsRoom::find($booking->property_id);

            if (!$room && $booking->pms_room_id) {
                $room = PmsRoom::where('pms_room_id', $booking->pms_room_id)->first();
            }

            if ($room) {
                return $room;
            }
        }

        return Property::find($booking->property_id);
    }

    private function looksLikePmsBooking(BookingRequest $booking): bool
    {
        return !empty($booking->pms_source)
            || !empty($booking->pms_room_id)
            || !empty($booking->pms_booking_id);
    }
}
