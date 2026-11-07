<?php

namespace App\Helper;

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
                ['Wifi', 'fa-wifi', ['wifi', 'wi fi', 'wireless internet', 'wireless', 'internet', 'broadband']],
                ['Dedicated workspace', 'fa-laptop', ['workspace', 'work space', 'desk', 'office']],
            ],
            'Kitchen & Dining' => [
                ['Kitchen', 'fa-kitchen-set', ['kitchen', 'kitchenette', 'cooking basics', 'cooking essentials']],
                ['Dishwasher', 'fa-soap', ['dishwasher']],
                ['Refrigerator', 'fa-box', ['refrigerator', 'fridge', 'freezer']],
                ['Microwave', 'fa-bowl-food', ['microwave']],
                ['Oven & stove', 'fa-fire-burner', ['oven', 'stove', 'cooker', 'hob', 'kitchen stove']],
                ['Coffee maker', 'fa-mug-hot', ['coffee', 'espresso', 'keurig', 'nespresso', 'coffee maker', 'coffee machine', 'tea maker']],
                ['Cookware & dishes', 'fa-utensils', ['crockery', 'cutlery', 'cookware', 'dishes', 'dishware', 'utensils', 'wine glasses', 'glasses', 'cooking utensils', 'baking sheet', 'dishes and silverware', 'silverware', 'childrens dinnerware', 'pots pans', 'pots', 'pans']],
                ['BBQ grill', 'fa-fire-burner', ['bbq', 'barbecue', 'grill', 'outdoor grill']],
                ['Toaster', 'fa-bread-slice', ['toaster']],
                ['Blender', 'fa-blender', ['blender']],
                ['Electric kettle', 'fa-mug-hot', ['kettle', 'electric kettle']],
                ['Dining area', 'fa-utensils', ['dining table', 'dining room', 'dining area']],
            ],
            'Bathroom' => [
                ['Hair dryer', 'fa-wind', ['hair dryer', 'hairdryer']],
                ['Shampoo & toiletries', 'fa-pump-soap', ['shampoo', 'body wash', 'toiletries', 'soap', 'conditioner', 'essentials', 'cleaning products']],
                ['Hot water', 'fa-droplet', ['hot water']],
                ['Bathtub', 'fa-bath', ['bathtub', 'bath tub', 'jacuzzi tub']],
                ['Towels', 'fa-bookmark', ['towels', 'towel set']],
                ['Toilet', 'fa-toilet', ['toilet', 'bidet']],
            ],
            'Bedroom & Laundry' => [
                ['Washer', 'fa-soap', ['washer', 'washing machine', 'laundry']],
                ['Dryer', 'fa-wind', ['dryer', 'tumble dryer', 'dryer in common']],
                ['Iron', 'fa-shirt', ['iron', 'ironing']],
                ['Hangers', 'fa-bars', ['hangers']],
                ['Bed linens', 'fa-bed', ['linens', 'bed linen', 'sheets', 'extra pillows', 'blankets', 'bedding', 'fold away bed', 'fold-away bed']],
                ['Beds', 'fa-bed', ['king bed', 'queen bed', 'twin bed', 'double bed', 'single bed', 'bunk bed', 'sofa bed', 'pull out bed', 'sleeper sofa', 'murphy bed']],
                ['Closet', 'fa-door-closed', ['closet', 'wardrobe', 'clothing storage']],
                ['Clothes rack', 'fa-shirt', ['clothes drying rack', 'drying rack']],
            ],
            'Entertainment' => [
                ['Pool table', 'fa-table', ['pool table', 'billiard']],
                ['Game console', 'fa-gamepad', ['game console', 'playstation', 'xbox', 'nintendo']],
                ['TV', 'fa-tv', ['tv', 'television', 'smart tv', 'cable tv', 'roku', 'cinema', 'dvd', 'cddvd', 'cd dvd', 'blu ray', 'home theater', 'projector']],
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
                ['Backyard', 'fa-tree', ['backyard', 'garden', 'yard', 'lawn', 'garden or backyard']],
                ['Fire pit', 'fa-fire', ['fire pit', 'firepit', 'bonfire']],
                ['Outdoor furniture', 'fa-chair', ['outdoor furniture', 'patio furniture', 'adirondack', 'outdoor seating', 'outdoor chairs']],
                ['Outdoor dining', 'fa-utensils', ['outdoor dining']],
                ['Outdoor lighting', 'fa-lightbulb', ['outdoor lighting']],
                ['Beach access', 'fa-umbrella-beach', ['beach access', 'beachfront', 'private beach', 'waterfront', 'beach essentials']],
                ['Beach', 'fa-umbrella-beach', ['beach']],
                ['Bikes', 'fa-bicycle', ['bikes', 'bicycle', 'bicycles available']],
                ['Hammock', 'fa-person', ['hammock']],
            ],
            'Parking & Facilities' => [
                ['Free parking', 'fa-square-parking', ['free parking', 'parking', 'garage', 'carport', 'driveway', 'disabled parking', 'free parking on premises', 'free parking on street']],
                ['Private entrance', 'fa-door-open', ['private entrance', 'private entry', 'keyless entry', 'key entry']],
                ['EV charger', 'fa-charging-station', ['ev charger', 'electric vehicle', 'charging']],
                ['Gym', 'fa-dumbbell', ['gym', 'fitness', 'exercise equipment', 'peloton']],
                ['Elevator', 'fa-elevator', ['elevator', 'lift']],
                ['Long stays', 'fa-calendar-check', ['long term stays', 'long-term stays', 'extended stays', 'monthly stay']],
                ['Boat slip', 'fa-sailboat', ['boat slip', 'boat dock', 'dock']],
            ],
            'Family & Accessibility' => [
                ['Crib', 'fa-baby', ['crib', 'cot', 'pack n play', 'changing table']],
                ['High chair', 'fa-chair', ['high chair', 'highchair']],
                ["Children's items", 'fa-children', ['children', 'kids', 'toys', 'baby', 'infants', 'infant', 'suitable for', 'family', 'children\'s books', 'children\'s dinnerware', 'play area', 'outdoor play']],
                ['Step-free access', 'fa-wheelchair', ['wheelchair', 'accessible', 'step free', 'accessible height', 'flat smooth pathway']],
                ['Babysitter', 'fa-person', ['babysitter']],
            ],
            'Safety' => [
                ['Smoke alarm', 'fa-bell', ['smoke alarm', 'smoke detector']],
                ['Carbon monoxide alarm', 'fa-shield-halved', ['carbon monoxide', 'co alarm', 'co detector']],
                ['Fire extinguisher', 'fa-fire-extinguisher', ['fire extinguisher']],
                ['First aid kit', 'fa-kit-medical', ['first aid']],
                ['Security cameras', 'fa-video', ['security camera', 'cctv', 'surveillance']],
                ['Safety guards', 'fa-shield', ['fireplace guard', 'stair gate', 'safety gate', 'guards']],
                ['Self check-in', 'fa-lock', ['smart lock', 'keypad', 'self check in', 'keyless', 'lockbox', 'deadbolt', 'alarm system', '24 checkin', 'check in option']],
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
        $seen    = [];   // dedup key → true  (keyed by normalised raw label)
        $more    = [];

        foreach ($raw as $item) {
            $label = is_array($item) ? ($item['label'] ?? ($item['name'] ?? '')) : (string) $item;
            $label = trim($label);
            if ($label === '') continue;

            // Dedup by normalised raw label so identical strings from different
            // PMS fields don't appear twice, but distinct amenities that merely
            // share a canonical name (e.g. "Internet" vs "Wireless") are kept.
            $dedupKey = strtolower(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/i', ' ', $label)));
            if (isset($seen[$dedupKey])) continue;
            $seen[$dedupKey] = true;

            // Normalize for matching: lowercase words, padded for whole-word check.
            $norm = ' ' . trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $label)))) . ' ';

            $icon       = 'fa-circle-check';
            $matchedCat = null;
            foreach ($catalog as $cat => $entries) {
                foreach ($entries as $entry) {
                    [, $entryIcon, $syns] = $entry;
                    foreach ($syns as $syn) {
                        if (str_contains($norm, ' ' . trim($syn) . ' ')) {
                            $icon       = $entryIcon;
                            $matchedCat = $cat;
                            break 3;
                        }
                    }
                }
            }

            // Preserve the original PMS label — only borrow icon and category.
            $entry = ['label' => $label, 'icon' => $icon];
            if ($matchedCat) {
                $grouped[$matchedCat][] = $entry;
            } else {
                $more[] = $entry;
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
