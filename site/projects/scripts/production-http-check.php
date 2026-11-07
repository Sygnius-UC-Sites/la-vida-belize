#!/usr/bin/env php
<?php

$baseUrl = rtrim((string) ($argv[1] ?? ''), '/');
if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !str_starts_with($baseUrl, 'https://')) {
    fwrite(STDERR, "Usage: php scripts/production-http-check.php https://your-domain.example\n");
    exit(2);
}

$checks = [];
$add = static function (string $id, bool $passed, string $detail, bool $blocking = true) use (&$checks): void {
    $checks[] = [
        'id' => $id,
        'status' => $passed ? 'PASS' : ($blocking ? 'FAIL' : 'WARN'),
        'detail' => $detail,
    ];
};

$request = static function (string $url, string $method = 'GET', ?string $body = null): array {
    $headers = [];
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'BaseProductionCertification/1.0',
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            $length = strlen($line);
            $line = trim($line);
            if ($line !== '' && str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))][] = trim($value);
            }
            return $length;
        },
    ]);
    if ($body !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        curl_setopt($handle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $responseBody = curl_exec($handle);
    $error = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    return [
        'status' => $status,
        'headers' => $headers,
        'body' => is_string($responseBody) ? $responseBody : '',
        'error' => $error,
    ];
};

$parts = parse_url($baseUrl);
$httpUrl = 'http://' . $parts['host']
    . (isset($parts['port']) ? ':' . $parts['port'] : '')
    . ($parts['path'] ?? '');

$http = $request($httpUrl);
$location = $http['headers']['location'][0] ?? '';
$add(
    'http_redirects_to_https',
    in_array($http['status'], [301, 302, 307, 308], true) && str_starts_with($location, 'https://'),
    "status={$http['status']}, location=" . ($location ?: 'missing')
);

$home = $request($baseUrl . '/');
$add('https_certificate_and_home', $home['error'] === '' && $home['status'] >= 200 && $home['status'] < 400, "status={$home['status']}" . ($home['error'] ? ", error={$home['error']}" : ''));
$add(
    'debug_toolbar_absent',
    preg_match('/phpdebugbar|_debugbar|debugbar/i', $home['body']) !== 1,
    'public home response'
);

$adminlteCss = $request($baseUrl . '/vendor/adminlte/dist/css/adminlte.min.css');
$adminlteJs = $request($baseUrl . '/vendor/adminlte/dist/js/adminlte.min.js');
$add(
    'adminlte_css_asset',
    $adminlteCss['status'] === 200
        && str_contains(strtolower((string) ($adminlteCss['headers']['content-type'][0] ?? '')), 'text/css')
        && str_contains($adminlteCss['body'], '.dropdown-menu'),
    "status={$adminlteCss['status']}, bytes=" . strlen($adminlteCss['body'])
);
$add(
    'adminlte_js_asset',
    $adminlteJs['status'] === 200
        && str_contains(strtolower((string) ($adminlteJs['headers']['content-type'][0] ?? '')), 'javascript')
        && str_contains($adminlteJs['body'], 'AdminLTE'),
    "status={$adminlteJs['status']}, bytes=" . strlen($adminlteJs['body'])
);

$admin = $request($baseUrl . '/admin');
$cookies = $admin['headers']['set-cookie'] ?? [];
$sessionCookie = null;
foreach ($cookies as $cookie) {
    if (str_contains(strtolower($cookie), 'session')) {
        $sessionCookie = $cookie;
        break;
    }
}
$add('session_cookie_present', is_string($sessionCookie), $sessionCookie ? 'present' : 'missing');
if (is_string($sessionCookie)) {
    $lowerCookie = strtolower($sessionCookie);
    $add('session_cookie_secure', str_contains($lowerCookie, '; secure'), 'Secure flag');
    $add('session_cookie_http_only', str_contains($lowerCookie, '; httponly'), 'HttpOnly flag');
    $add(
        'session_cookie_same_site',
        str_contains($lowerCookie, 'samesite=lax') || str_contains($lowerCookie, 'samesite=strict'),
        'SameSite flag'
    );
}

$hsts = $home['headers']['strict-transport-security'][0] ?? '';
$add('hsts_header', $hsts !== '', $hsts ?: 'missing', false);
$add(
    'content_type_options',
    strtolower((string) ($home['headers']['x-content-type-options'][0] ?? '')) === 'nosniff',
    (string) ($home['headers']['x-content-type-options'][0] ?? 'missing'),
    false
);

$stripe = $request($baseUrl . '/stripe/webhook', 'POST', '{}');
$stripeNotConfigured = str_contains($stripe['body'], 'not_configured');
$add(
    'stripe_webhook_rejects_unsigned_request',
    in_array($stripe['status'], [400, 401], true) && !$stripeNotConfigured,
    "status={$stripe['status']}" . ($stripeNotConfigured ? ', webhook secret missing' : '')
);

$pmsEndpoints = [
    'lodgify' => '/lodgify/booking_change',
    'hostaway' => '/webhooks/hostaway',
    'hostfully' => '/webhooks/hostfully',
    'guesty' => '/webhooks/guesty',
];
foreach ($pmsEndpoints as $provider => $path) {
    $response = $request($baseUrl . $path, 'POST', '{}');
    $add(
        "{$provider}_webhook_rejects_unsigned_request",
        $response['status'] === 401,
        "status={$response['status']}"
    );
}

$ready = true;
foreach ($checks as $check) {
    if ($check['status'] === 'FAIL') {
        $ready = false;
        break;
    }
}
foreach ($checks as $check) {
    printf("%-5s %-44s %s\n", $check['status'], $check['id'], $check['detail']);
}
echo PHP_EOL . 'PRODUCTION_HTTP=' . ($ready ? 'PASS' : 'FAIL') . PHP_EOL;

exit($ready ? 0 : 1);
