<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = <<<'HTML'
<h2>1. Parties</h2>
<p>This Rental Agreement (the "Agreement") is entered into between <strong>{{owner_name}}</strong> (the "Owner"), {{owner_company}}, and <strong>{{guest_name}}</strong> (the "Guest"), email <a href="mailto:{{guest_email}}">{{guest_email}}</a>.</p>

<h2>2. Property and Term</h2>
<p>The Owner agrees to rent to the Guest the property known as <strong>{{property_name}}</strong>, located at {{property_address}}, for the period from <strong>{{check_in}}</strong> through <strong>{{check_out}}</strong>, a total of <strong>{{nights}}</strong> night(s).</p>
<p>Occupancy: {{adults}} adult(s), {{children}} child(ren), {{pets}} pet(s).</p>

<h2>3. Total Charges and Damage Deposit</h2>
<p>Total rental amount: <strong>{{currency}} {{total_amount}}</strong>.</p>
<p>Refundable damage deposit: {{currency}} {{damage_deposit}}, held against any damages or excessive cleaning required after the Guest's stay.</p>

<h2>4. Cancellation</h2>
<p>Cancellation is subject to the cancellation policy displayed and accepted by the Guest at the time of booking. Refer to the booking confirmation for the applicable terms.</p>

<h2>5. House Rules</h2>
<p>The House Rules section that follows the Agreement is incorporated by reference and is binding on the Guest. The Guest acknowledges having read the House Rules prior to signing.</p>

<h2>6. Liability</h2>
<p>The Guest accepts the property in its present condition and assumes all risk associated with the use of the premises and its amenities. The Owner is not liable for personal injury, loss, or damage to personal property except in the case of gross negligence on the Owner's part.</p>

<h2>7. Damage Policy</h2>
<p>The Guest is responsible for any damage caused to the property or its contents during the rental period beyond normal wear and tear. The damage deposit may be applied toward the cost of repairs; damages exceeding the deposit will be billed to the Guest.</p>

<h2>8. Governing Law</h2>
<p>This Agreement is governed by the laws of the Republic of Costa Rica. Any dispute arising out of or related to this Agreement shall be subject to the exclusive jurisdiction of the courts of San Jose, Costa Rica.</p>

<h2>9. Electronic Signature</h2>
<p>The Guest agrees that the electronic signature affixed below is the legal equivalent of a handwritten signature and constitutes the Guest's intent and acceptance of the terms of this Agreement under applicable law.</p>
HTML;

        $houseRules = <<<'HTML'
<ul>
  <li><strong>Maximum occupancy:</strong> as agreed at booking. No additional overnight guests without prior written approval from the Owner.</li>
  <li><strong>No smoking</strong> inside the property. Smoking is permitted only in designated outdoor areas, if any.</li>
  <li><strong>No parties or events</strong> without prior written approval from the Owner.</li>
  <li><strong>Quiet hours:</strong> 10:00 PM to 8:00 AM. Please respect neighbors and surrounding wildlife.</li>
  <li><strong>Pets:</strong> permitted only when explicitly approved at booking. Owners are responsible for cleaning up after their pets.</li>
  <li><strong>Pool, hot tub, and outdoor amenities:</strong> use at your own risk. Children must be supervised at all times.</li>
  <li><strong>Trash and recycling:</strong> follow the instructions left at the property.</li>
  <li><strong>Departure:</strong> please leave the property in the condition in which you found it. Excessive cleaning beyond standard turnover may be charged against the damage deposit.</li>
</ul>
HTML;

        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'rental_agreement_template_html'],
            ['value' => $template, 'updated_at' => now()]
        );
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'rental_agreement_house_rules_html'],
            ['value' => $houseRules, 'updated_at' => now()]
        );
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'rental_agreement_default_enabled'],
            ['value' => '0', 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('basic_settings')->whereIn('name', [
            'rental_agreement_template_html',
            'rental_agreement_house_rules_html',
            'rental_agreement_default_enabled',
        ])->delete();
    }
};
