<?php

namespace App\Helper;

/**
 * Normalizes "key facts" (check-in/out, min/max stay, house-rule flags) into one
 * uniform shape for every PMS. Adapters pass the explicit values they know; this
 * fills the rest by inferring pets/smoking/events/children rules from the
 * amenities list and house-rules text, so listings present consistently.
 *
 * Returned shape (any value may be null when unknown):
 *   check_in, check_out (string "HH:MM"), min_stay, max_stay (int),
 *   pets_allowed, smoking_allowed, events_allowed, suitable_for_children (bool|null)
 */
class ListingFacts
{
    public static function build(array $explicit, array $amenities = [], string $houseRules = ''): array
    {
        $hay = ' ' . strtolower(implode(' | ', array_map('strval', $amenities)) . ' || ' . strip_tags($houseRules)) . ' ';

        return [
            'check_in'  => self::time($explicit['check_in'] ?? null),
            'check_out' => self::time($explicit['check_out'] ?? null),
            'min_stay'  => self::int($explicit['min_stay'] ?? null),
            'max_stay'  => self::int($explicit['max_stay'] ?? null),
            'pets_allowed' => $explicit['pets_allowed']
                ?? self::infer($hay, ['pets allowed', 'pet friendly', 'pets welcome', 'dogs allowed'], ['no pets', 'pets not allowed', 'no animals']),
            'smoking_allowed' => $explicit['smoking_allowed']
                ?? self::infer($hay, ['smoking allowed', 'smoking permitted'], ['no smoking', 'non smoking', 'smoking not allowed']),
            'events_allowed' => $explicit['events_allowed']
                ?? self::infer($hay, ['events allowed', 'parties allowed', 'suitable for events'], ['no events', 'no parties', 'parties not allowed', 'no parties or events']),
            'suitable_for_children' => $explicit['suitable_for_children']
                ?? self::infer($hay, ['suitable for children', 'family friendly', 'kid friendly', 'children welcome'], ['not suitable for children', 'no children', 'adults only']),
        ];
    }

    protected static function time($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return sprintf('%02d:00', (int) $v);
        }
        return (string) $v;
    }

    protected static function int($v): ?int
    {
        return ($v === null || $v === '') ? null : (int) $v;
    }

    /** Negatives win over positives; null when nothing matches. */
    protected static function infer(string $hay, array $pos, array $neg): ?bool
    {
        foreach ($neg as $n) {
            if (str_contains($hay, $n)) {
                return false;
            }
        }
        foreach ($pos as $p) {
            if (str_contains($hay, $p)) {
                return true;
            }
        }
        return null;
    }
}
