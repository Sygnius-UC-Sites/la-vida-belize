<?php

namespace App\Services;

use App\Models\Pms\PmsCalendar;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ListingAvailabilityService
{
    public function filter(Collection $properties, ?string $checkIn, ?string $checkOut): Collection
    {
        if (!$checkIn || !$checkOut) {
            return $properties;
        }

        try {
            $arrival = Carbon::createFromFormat('Y-m-d', $checkIn)->startOfDay();
            $departure = Carbon::createFromFormat('Y-m-d', $checkOut)->startOfDay();
        } catch (\Throwable $e) {
            return collect();
        }

        if (!$departure->gt($arrival)) {
            return collect();
        }

        $pmsProperties = $properties->filter(fn ($property) => $property->pmsRoom);
        $roomKeys = $pmsProperties
            ->map(fn ($property) => [
                'source' => $property->pmsRoom->pms_source,
                'room' => (string) $property->pmsRoom->pms_room_id,
            ])
            ->unique(fn ($item) => $item['source'] . '|' . $item['room'])
            ->values();

        $calendar = collect();
        foreach ($roomKeys as $key) {
            $calendar = $calendar->merge(
                PmsCalendar::forRoom($key['source'], $key['room'])
                    ->inRange($checkIn, $checkOut)
                    ->get()
            );
        }
        $calendar = $calendar->groupBy(
            fn ($day) => $day->pms_source . '|' . $day->pms_room_id
        );

        $legacyConflictIds = $this->legacyConflictIds($properties, $checkIn, $checkOut);

        return $properties->filter(function ($property) use (
            $arrival,
            $departure,
            $calendar,
            $legacyConflictIds
        ) {
            $room = $property->pmsRoom;
            if (!$room) {
                return !$legacyConflictIds->contains((int) $property->id);
            }

            $days = $calendar->get($room->pms_source . '|' . $room->pms_room_id, collect())
                ->keyBy(fn ($day) => $day->date->format('Y-m-d'));

            $cursor = $arrival->copy();
            while ($cursor->lt($departure)) {
                $day = $days->get($cursor->format('Y-m-d'));
                if (!$day
                    || !$day->isAvailable()
                    || $day->stop_sell
                    || ($day->inventory !== null && $day->inventory < 1)
                ) {
                    return false;
                }
                if ($cursor->equalTo($arrival) && $day->closed_to_arrival) {
                    return false;
                }
                $cursor->addDay();
            }

            $checkoutDay = $days->get($departure->format('Y-m-d'));
            return !$checkoutDay || !$checkoutDay->closed_to_departure;
        })->values();
    }

    private function legacyConflictIds(
        Collection $properties,
        string $checkIn,
        string $checkOut
    ): Collection {
        $legacyIds = $properties->reject(fn ($property) => $property->pmsRoom)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($legacyIds->isEmpty()) {
            return collect();
        }

        $bookings = \DB::table('booking_requests')
            ->whereIn('property_id', $legacyIds)
            ->where('booking_status', 'booked')
            ->whereNotNull('checkin')
            ->whereNotNull('checkout')
            ->where('checkin', '<', $checkOut)
            ->where('checkout', '>', $checkIn)
            ->pluck('property_id');

        $ical = \DB::table('ical_events')
            ->whereIn('event_pid', $legacyIds)
            ->where('start_date', '<', $checkOut)
            ->where('end_date', '>', $checkIn)
            ->pluck('event_pid');

        // FIX (Jun 18 2026): also honor MANUAL calendar blocks (property_rates.is_available=0).
        // These were enforced by the date picker but NOT by /stays search → a blocked property
        // showed in results but couldn't be booked. Now both sources agree.
        $manualBlocks = \DB::table('property_rates')
            ->whereIn('property_id', $legacyIds)
            ->whereNull('rate_group_id')
            ->whereIn('is_available', ['0', 'false', 'no', 0])
            ->where('single_date', '>=', $checkIn)
            ->where('single_date', '<', $checkOut)
            ->pluck('property_id');

        return $bookings->merge($ical)->merge($manualBlocks)->map(fn ($id) => (int) $id)->unique();
    }
}
