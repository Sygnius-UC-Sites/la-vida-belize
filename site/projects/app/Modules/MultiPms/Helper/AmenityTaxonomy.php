<?php

namespace App\Modules\MultiPms\Helper;

/**
 * Maps raw amenity strings from ANY PMS (Guesty "Air conditioning",
 * Hostfully "HAS_POOL" → "Pool", Hostaway names, Lodgify groups) into one
 * standard, categorized taxonomy with consistent Font Awesome icons — so the
 * front-end Amenities section looks identical regardless of the source PMS.
 *
 * Lossless: anything that doesn't match a known amenity is still shown under
 * a "More" group, so no data from the PMS is hidden.
 *
 * Usage:  $am = \App\Helper\AmenityTaxonomy::map($amenities);
 *         $am['flat']    // [ ['label'=>..,'icon'=>..], .. ]  (deduped, ordered)
 *         $am['grouped'] // [ 'Category' => [ ['label'=>..,'icon'=>..], .. ] ]
 *         $am['count']
 */
class AmenityTaxonomy
{
    /**
     * category => [ [canonicalLabel, faIcon, [matchSynonyms...]], ... ]
     * Order matters: earlier entries win, so list specific amenities
     * (e.g. "pool table") before generic ones (e.g. "pool").
     */
    protected static function catalog(): array
    {
        return [
            'Internet & Office' => [
                ['Wifi', 'fa-wifi', ['wifi', 'wi fi', 'wireless internet', 'internet']],
                ['Dedicated workspace', 'fa-laptop', ['workspace', 'work space', 'desk', 'office']],
            ],
            'Kitchen & Dining' => [
                ['Kitchen', 'fa-kitchen-set', ['kitchen', 'kitchenette']],
                ['Dishwasher', 'fa-soap', ['dishwasher']],
                ['Refrigerator', 'fa-box', ['refrigerator', 'fridge', 'freezer']],
                ['Microwave', 'fa-bowl-food', ['microwave']],
                ['Oven & stove', 'fa-fire-burner', ['oven', 'stove', 'cooker', 'hob']],
                ['Coffee maker', 'fa-mug-hot', ['coffee', 'espresso', 'keurig', 'nespresso']],
                ['Cookware & dishes', 'fa-utensils', ['crockery', 'cutlery', 'cookware', 'dishes', 'dishware', 'utensils', 'wine glasses', 'glasses']],
                ['BBQ grill', 'fa-fire-burner', ['bbq', 'barbecue', 'grill']],
                ['Toaster', 'fa-bread-slice', ['toaster']],
                ['Blender', 'fa-blender', ['blender']],
            ],
            'Bathroom' => [
                ['Hair dryer', 'fa-wind', ['hair dryer', 'hairdryer']],
                ['Shampoo & toiletries', 'fa-pump-soap', ['shampoo', 'body wash', 'toiletries', 'soap']],
                ['Hot water', 'fa-droplet', ['hot water']],
                ['Bathtub', 'fa-bath', ['bathtub', 'bath tub', 'jacuzzi tub']],
                ['Towels', 'fa-bookmark', ['towels']],
                ['Toilet', 'fa-toilet', ['toilet', 'bidet']],
            ],
            'Bedroom & Laundry' => [
                ['Washer', 'fa-soap', ['washer', 'washing machine', 'laundry']],
                ['Dryer', 'fa-wind', ['dryer', 'tumble dryer']],
                ['Iron', 'fa-shirt', ['iron', 'ironing']],
                ['Hangers', 'fa-bars', ['hangers']],
                ['Bed linens', 'fa-bed', ['linens', 'bed linen', 'sheets', 'extra pillows', 'blankets', 'bedding']],
                ['Closet', 'fa-door-closed', ['closet', 'wardrobe']],
            ],
            'Entertainment' => [
                ['Pool table', 'fa-table', ['pool table', 'billiard']],
                ['Game console', 'fa-gamepad', ['game console', 'playstation', 'xbox', 'nintendo']],
                ['TV', 'fa-tv', ['tv', 'television', 'smart tv', 'cable tv', 'roku']],
                ['Sound system', 'fa-volume-high', ['sound system', 'speaker', 'stereo', 'sonos']],
                ['Books & games', 'fa-book', ['books', 'reading material', 'board games']],
            ],
            'Heating & Cooling' => [
                ['Air conditioning', 'fa-snowflake', ['air conditioning', 'air conditioner', 'ac', 'central air', 'cooling']],
                ['Heating', 'fa-temperature-high', ['heating', 'heater', 'central heating']],
                ['Fireplace', 'fa-fire', ['fireplace', 'wood stove', 'log fire']],
                ['Ceiling fan', 'fa-fan', ['ceiling fan', 'fan']],
            ],
            'Pool & Spa' => [
                ['Pool', 'fa-person-swimming', ['pool', 'swimming pool', 'heated pool', 'salt water pool']],
                ['Hot tub', 'fa-bath', ['hot tub', 'jacuzzi', 'spa']],
                ['Sauna', 'fa-temperature-high', ['sauna']],
            ],
            'Outdoor' => [
                ['Patio or balcony', 'fa-archway', ['patio', 'balcony', 'deck', 'terrace', 'porch', 'veranda']],
                ['Backyard', 'fa-tree', ['backyard', 'garden', 'yard', 'lawn']],
                ['Fire pit', 'fa-fire', ['fire pit', 'firepit', 'bonfire']],
                ['Outdoor furniture', 'fa-chair', ['outdoor furniture', 'patio furniture', 'adirondack']],
                ['Outdoor dining', 'fa-utensils', ['outdoor dining']],
                ['Outdoor lighting', 'fa-lightbulb', ['outdoor lighting']],
                ['Beach access', 'fa-umbrella-beach', ['beach access', 'beachfront', 'private beach', 'waterfront']],
                ['Bikes', 'fa-bicycle', ['bikes', 'bicycle']],
            ],
            'Parking & Facilities' => [
                ['Free parking', 'fa-square-parking', ['free parking', 'parking', 'garage', 'carport', 'driveway']],
                ['EV charger', 'fa-charging-station', ['ev charger', 'electric vehicle', 'charging']],
                ['Gym', 'fa-dumbbell', ['gym', 'fitness', 'exercise equipment', 'peloton']],
                ['Elevator', 'fa-elevator', ['elevator', 'lift']],
            ],
            'Family & Accessibility' => [
                ['Crib', 'fa-baby', ['crib', 'cot', 'pack n play']],
                ['High chair', 'fa-chair', ['high chair', 'highchair']],
                ["Children's items", 'fa-children', ['children', 'kids', 'toys', 'baby']],
                ['Step-free access', 'fa-wheelchair', ['wheelchair', 'accessible', 'step free']],
            ],
            'Safety' => [
                ['Smoke alarm', 'fa-bell', ['smoke alarm', 'smoke detector']],
                ['Carbon monoxide alarm', 'fa-shield-halved', ['carbon monoxide', 'co alarm', 'co detector']],
                ['Fire extinguisher', 'fa-fire-extinguisher', ['fire extinguisher']],
                ['First aid kit', 'fa-kit-medical', ['first aid']],
                ['Security cameras', 'fa-video', ['security camera', 'cctv', 'surveillance']],
                ['Safety guards', 'fa-shield', ['fireplace guard', 'stair gate', 'safety gate', 'guards']],
                ['Self check-in', 'fa-lock', ['smart lock', 'keypad', 'self check in', 'keyless', 'lockbox']],
            ],
            'Views & Location' => [
                ['Mountain view', 'fa-mountain', ['mountain']],
                ['Water view', 'fa-water', ['water view', 'lake', 'ocean view', 'sea view', 'river']],
                ['City view', 'fa-city', ['city view', 'skyline']],
            ],
        ];
    }

    public static function map(array $raw): array
    {
        $catalog = self::catalog();
        $grouped = [];
        $seen = [];
        $more = [];

        foreach ($raw as $item) {
            $label = is_array($item) ? ($item['label'] ?? ($item['name'] ?? '')) : (string) $item;
            $label = trim($label);
            if ($label === '') {
                continue;
            }

            // Normalize: lowercase, non-alphanumerics → spaces, padded for whole-word matching.
            $norm = ' ' . trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $label)))) . ' ';

            $matched = null;
            $matchedCat = null;
            foreach ($catalog as $cat => $entries) {
                foreach ($entries as $entry) {
                    [$canon, $icon, $syns] = $entry;
                    foreach ($syns as $syn) {
                        if (str_contains($norm, ' ' . trim($syn) . ' ')) {
                            $matched = ['label' => $canon, 'icon' => $icon];
                            $matchedCat = $cat;
                            break 3;
                        }
                    }
                }
            }

            if ($matched) {
                $key = $matchedCat . '|' . $matched['label'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $grouped[$matchedCat][] = $matched;
            } else {
                $k = 'more|' . strtolower($label);
                if (isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                $more[] = ['label' => $label, 'icon' => 'fa-circle-check'];
            }
        }

        // Order categories by catalog order; "More" always last.
        $ordered = [];
        foreach (array_keys($catalog) as $cat) {
            if (!empty($grouped[$cat])) {
                $ordered[$cat] = $grouped[$cat];
            }
        }
        if (!empty($more)) {
            $ordered['More'] = $more;
        }

        $flat = [];
        foreach ($ordered as $items) {
            foreach ($items as $it) {
                $flat[] = $it;
            }
        }

        return ['grouped' => $ordered, 'flat' => $flat, 'count' => count($flat)];
    }
}
