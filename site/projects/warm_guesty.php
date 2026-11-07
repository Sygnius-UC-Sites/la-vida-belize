<?php
// Run with: php artisan tinker --execute="require base_path('warm_guesty.php');"

$cid = DB::table('basic_settings')->where('name', 'guesty_client_id')->value('value');
$cs  = DB::table('basic_settings')->where('name', 'guesty_api_key')->value('value');

echo "client_id: $cid\n";
echo "secret: " . substr($cs, 0, 10) . "...\n";

$r = Illuminate\Support\Facades\Http::asForm()->post('https://open-api.guesty.com/oauth2/token', [
    'grant_type'    => 'client_credentials',
    'client_id'     => $cid,
    'client_secret' => $cs,
    'scope'         => 'open-api',
]);

$t = $r->json()['access_token'] ?? null;

if ($t) {
    Cache::put('guesty_access_token_' . md5($cs), $t, 7200);
    echo "Token cached OK (expires in 2 hours)\n";
} else {
    echo "FAILED: " . json_encode($r->json()) . "\n";
}
