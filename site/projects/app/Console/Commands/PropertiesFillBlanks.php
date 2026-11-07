<?php

namespace App\Console\Commands;

use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Walks every active property and fills user-visible blank fields with
 * sensible test content so manual testing isn't blocked by missing data.
 * Idempotent — only fills empty/zero fields, never overwrites admin edits.
 *
 *   php artisan properties:fill-blanks
 */
class PropertiesFillBlanks extends Command
{
    protected $signature = 'properties:fill-blanks {--id= : restrict to one property id}';
    protected $description = 'Fill empty user-visible fields on properties so manual testing has full data.';

    public function handle(): int
    {
        $q = Property::where('status', 'true');
        if ($id = $this->option('id')) $q->where('id', $id);
        $props = $q->get();

        if ($props->isEmpty()) {
            $this->warn('No active properties found.');
            return self::SUCCESS;
        }

        $changedTotal = 0;
        foreach ($props as $p) {
            $changed = $this->fill($p);
            if ($changed > 0) {
                $this->info("  ✓ #{$p->id} {$p->name} — filled $changed field(s)");
                $changedTotal += $changed;
            } else {
                $this->line("  · #{$p->id} {$p->name} — already complete");
            }
        }
        $this->info("\nFilled $changedTotal field(s) across {$props->count()} property/properties.");
        return self::SUCCESS;
    }

    private function fill(Property $p): int
    {
        $now = now();
        // Vary content slightly by id so admin can spot per-row differences.
        $idx = $p->id;
        $hostFirst = ['Maria', 'Carlos', 'Elena', 'Diego', 'Sofia', 'Luis', 'Camila'][$idx % 7];

        $defaults = [
            // Check-in / out narrative + safe-to-test placeholders.
            'detail_check_in_instructions' => "Self check-in with keypad. Door code provided 24h before arrival. If you arrive early, luggage drop-off is available — just message us.",
            'checkin_arrival_window'       => '3:00 PM – 9:00 PM',
            'checkin_door_code'            => str_pad((string) (1234 + $idx * 11 % 8000), 4, '0', STR_PAD_LEFT),
            'checkin_wifi_name'            => 'CasaWifi-' . sprintf('%03d', $idx),
            'checkin_wifi_password'        => 'paradise' . (2025 + ($idx % 5)),
            'checkin_parking_instructions' => 'Free parking on the property — pull up to the gate, the keypad opens it automatically when in range.',
            'checkin_host_name'            => $hostFirst . ' (your local concierge)',
            'checkin_host_phone'           => '+506 8' . str_pad((string)(100 + $idx * 17 % 9000), 4, '0', STR_PAD_LEFT) . '-' . str_pad((string)($idx * 31 % 10000), 4, '0', STR_PAD_LEFT),
            'checkin_emergency_phone'      => '+506 911',

            'checkout_departure_time'      => '11:00 AM',
            'checkout_key_return'          => 'Leave the keypad disarmed — no key to return.',
            'checkout_cleaning_checklist'  => "• Strip used towels into the laundry basket\n• Place trash in the labelled bin outside\n• Turn off A/C and ceiling fans\n• Lock all doors and windows",
            'checkout_notes'               => 'Coffee, tea, sugar, salt, oil are restocked between stays — feel free to use what\'s here.',
            'checkout_day'                 => '0',

            // Capacity / pet defaults
            'pet_fee'                      => '25.00',
            'max_pet'                      => '2',
            'guest_fee'                    => '20.00',
            'heating_pool_fee'             => '0.00',

            // Public-facing detail-page copy
            'bedroom_text'                 => trim(($p->bedroom ?? 0) . ' bedroom' . ((($p->bedroom ?? 0) === 1) ? '' : 's')),
            'bathroom_text'                => trim(($p->bathroom ?? ($p->bedroom ?? 1)) . ' bathroom' . ((($p->bathroom ?? 1) === 1) ? '' : 's')),
            'beds_text'                    => trim(($p->beds ?? max(1, $p->bedroom ?? 1)) . ' bed' . ((($p->beds ?? 1) === 1) ? '' : 's')),
            'sleeps_text'                  => 'Sleeps up to ' . max(2, ($p->sleeps ?? $p->no_of_guest ?? 4)),

            'get_quote_description'        => 'Tell us your dates and party size — we\'ll come back within 24 hours with a tailored quote, including any discounts you qualify for.',
            'review_text'                  => 'Loved by every guest who stayed.',

            'box_heading'                  => $p->name ? $p->name : 'Your private retreat',
            'box_description'              => 'Comfortable for families and groups, fully equipped, and surrounded by some of the most beautiful scenery in the area.',

            'security_deposit_amount'      => '300.00',
            'security_deposit_text'        => 'A refundable $300 hold is placed on your card at check-in. Released within 48 hours of checkout, assuming the property is left in good condition.',

            'visitor_rate_info'            => '$15 per additional day-visitor over the included party size. Limit 4 visitors at a time.',
            'total_amount_include_text'    => 'Price includes nightly rate, taxes, and cleaning fee. Optional add-ons (pet, hot tub heating, extra cleaning) are listed separately at checkout.',

            'rating'                       => 4.8 + (($idx % 3) * 0.05),  // 4.80 / 4.85 / 4.90

            'detail_share_label'           => 'Share',
            'detail_booking_disclaimer'    => 'Rates and availability are real-time and may change. Final price confirmed at checkout.',
            'detail_map_heading'           => 'Where you\'ll be',
            'detail_map_iframe_src'        => $p->map ?: null,  // mirror the map column if set

            'max_stay'                     => '30',

            'rental_agreement_house_rules_html' =>
                "<h4>Quick house rules</h4>"
              . "<ul>"
              . "<li>Check-in 3 PM, check-out 11 AM</li>"
              . "<li>No smoking inside the home</li>"
              . "<li>Quiet hours 10 PM – 7 AM</li>"
              . "<li>Pets allowed with prior approval (\$25/pet, max 2)</li>"
              . "<li>Events allowed with host approval — please ask in advance</li>"
              . "</ul>",
        ];

        $update = [];
        foreach ($defaults as $col => $value) {
            if (!\Schema::hasColumn('properties', $col)) continue;
            if ($value === null) continue;
            $current = $p->$col;
            $isBlank = ($current === null || $current === '' || (is_string($current) && trim($current) === '')
                        || ($current === '0' && in_array($col, ['pet_fee','max_pet','guest_fee','heating_pool_fee','security_deposit_amount','rating','max_stay','checkout_day'], true)));
            if ($isBlank) {
                $update[$col] = $value;
            }
        }
        if (!empty($update)) {
            $update['updated_at'] = $now;
            DB::table('properties')->where('id', $p->id)->update($update);
        }
        return count($update);
    }
}
