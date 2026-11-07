<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            // NEW (Phase 2D)
            'square_environment'                       => 'sandbox',
            'authorize_environment'                    => 'sandbox',
            'AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY'    => '',
            // Ensured present (empty if missing — owner fills via admin)
            'enabled_payment_gateways'                 => '',
            'stripe_publish_key'                       => '',
            'stripe_secret_key'                        => '',
            'paypal_access_token'                      => '',
            'square_ACCESS_TOKEN'                      => '',
            'square_Application_id'                    => '',
            'square_location_id'                       => '',
            'AUTHORIZED_MERCHANT_LOGIN_ID'             => '',
            'AUTHORIZED_MERCHANT_TRANSACTION_KEY'      => '',
        ];

        foreach ($defaults as $name => $value) {
            $exists = DB::table('basic_settings')->where('name', $name)->exists();
            if (!$exists) {
                DB::table('basic_settings')->insert([
                    'name'       => $name,
                    'value'      => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('basic_settings')->whereIn('name', [
            'square_environment',
            'authorize_environment',
            'AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY',
        ])->delete();
    }
};
