<?php
// Test-only router for `php -S`: the built-in server hands every environment variable to the app (artisan serve does not), and the public
// stock pictures get a CORS header so the apps' web builds can load them. Never use this in production.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$public = dirname(__DIR__, 3) . '/public';
if ($path !== '/' && is_file($public . $path)) {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: ' . (str_ends_with($path, '.png') ? 'image/png' : 'application/octet-stream'));
    readfile($public . $path);

    return true;
}
require $public . '/index.php';
