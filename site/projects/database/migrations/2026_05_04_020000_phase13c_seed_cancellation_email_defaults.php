<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $defaults = [
            'cancellation_default_subject' => 'Your booking at {{property_name}} has been cancelled',
            'cancellation_default_body_html' => $this->defaultBody(),
        ];

        foreach ($defaults as $name => $value) {
            $existing = DB::table('basic_settings')->where('name', $name)->first();
            if ($existing && $existing->value !== null && $existing->value !== '') {
                // Don't overwrite if the admin already customized.
                continue;
            }
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        // Non-destructive: keep the seeded rows on rollback.
    }

    private function defaultBody(): string
    {
        return <<<HTML
<p>Hi {{guest_name}},</p>

<p>Your booking at <strong>{{property_name}}</strong> for {{check_in}} → {{check_out}} ({{nights}} nights) has been cancelled on {{cancel_date}}.</p>

<p><strong>Booking summary</strong><br>
Booking ID: #{{booking_id}}<br>
Total: {{total_amount}}</p>

<p>If you have any questions or believe this was made in error, please reach out to us at <a href="mailto:{{site_email}}">{{site_email}}</a> or {{site_phone}}.</p>

<p>We hope to host you in the future.</p>

<p>Best regards,<br>
{{brand}}</p>
HTML;
    }
};
