<?php

namespace App\Helper;

use App\Models\Pms\PmsRoom;

class BookingPartyValidator
{
    public static function limits(PmsRoom $room): array
    {
        $keyFacts = json_decode($room->key_facts_json ?? '[]', true) ?: [];

        $petsAllowed = $keyFacts['pets_allowed'] ?? null;
        if ($petsAllowed === null && $room->pets_allowed !== null) {
            $petsAllowed = (bool) (int) $room->pets_allowed;
        }

        $pmsMaxPets = max(0, (int) ($room->max_pets ?? 0));
        $defaultMax = max(1, (int) config('pms.default_max_pets', 2));
        $maxPets    = $petsAllowed === true
            ? ($pmsMaxPets > 0 ? $pmsMaxPets : $defaultMax)
            : 0;

        return [
            'max_guests'             => max(0, (int) ($room->max_people ?? 0)),
            'pets_allowed'           => $petsAllowed,
            'max_pets'               => $maxPets,
            'uses_default_pet_limit' => $petsAllowed === true && $pmsMaxPets === 0,
        ];
    }

    public static function validate(
        PmsRoom $room,
        int $adults,
        int $children,
        int $pets
    ): array {
        $limits   = self::limits($room);
        $adults   = max(0, $adults);
        $children = max(0, $children);
        $pets     = max(0, $pets);
        $guests   = $adults + $children;

        if ($adults < 1) {
            return self::fail('At least one adult is required.', $limits);
        }

        if ($limits['max_guests'] > 0 && $guests > $limits['max_guests']) {
            return self::fail(
                "This property accommodates a maximum of {$limits['max_guests']} guests.",
                $limits
            );
        }

        if ($pets > 0 && $limits['pets_allowed'] !== true) {
            return self::fail('Pets cannot be added to this property.', $limits);
        }

        if ($pets > $limits['max_pets']) {
            $label = $limits['max_pets'] === 1 ? 'pet' : 'pets';
            return self::fail(
                "This property allows a maximum of {$limits['max_pets']} {$label}.",
                $limits
            );
        }

        return ['ok' => true, 'error' => null, 'limits' => $limits];
    }

    private static function fail(string $message, array $limits): array
    {
        return ['ok' => false, 'error' => $message, 'limits' => $limits];
    }
}
