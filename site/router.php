<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

// AdminLTE (and its jQuery/Bootstrap/Font Awesome plugins) live in Composer
// vendor. Publish copies them to site/vendor; this maps the same URLs when
// those copies are missing so php -S can still serve them from the package.
$adminlte = __DIR__ . '/projects/vendor/almasaeed2010/adminlte';
$vendorMap = [
    '/vendor/adminlte/' => $adminlte . '/',
    '/vendor/fontawesome-free/' => $adminlte . '/plugins/fontawesome-free/',
    '/vendor/jquery/' => $adminlte . '/plugins/jquery/',
    '/vendor/bootstrap/' => $adminlte . '/plugins/bootstrap/',
    '/vendor/overlayScrollbars/' => $adminlte . '/plugins/overlayScrollbars/',
    '/vendor/popper/' => $adminlte . '/plugins/popper/',
];

foreach ($vendorMap as $urlPrefix => $fsPrefix) {
    if (!str_starts_with($path, $urlPrefix)) {
        continue;
    }

    $mapped = $fsPrefix . substr($path, strlen($urlPrefix));
    $realBase = realpath($fsPrefix);
    $realFile = realpath($mapped);
    if ($realBase === false || $realFile === false || !is_file($realFile)) {
        break;
    }

    $baseNorm = strtolower(str_replace('\\', '/', $realBase));
    $fileNorm = strtolower(str_replace('\\', '/', $realFile));
    if (!str_starts_with($fileNorm, $baseNorm . '/') && $fileNorm !== $baseNorm) {
        break;
    }

    $ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'map', 'json' => 'application/json',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($realFile));
    readfile($realFile);
    exit;
}

require __DIR__ . '/index.php';
