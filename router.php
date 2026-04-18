<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$uri = $uri ?: '/';

$root = __DIR__;

// Serve real files directly
$path = $root . $uri;
if ($uri !== '/' && is_file($path)) {
    return false;
}

// "/" -> hub
if ($uri === '/') {
    require $root . '/hub/index.php';
    exit;
}

// Allow /shared static files
if (str_starts_with($uri, '/shared/')) {
    $target = $root . $uri;
    if (is_file($target)) return false; // let php -S serve it
    http_response_code(404);
    echo "Missing: " . htmlspecialchars($uri, ENT_QUOTES);
    exit;
}

// Allow /labs directories + php
if (str_starts_with($uri, '/labs/')) {
    $target = $root . $uri;
    if (is_dir($target)) $target = rtrim($target, '/') . '/index.php';
    if (is_file($target)) { require $target; exit; }
    http_response_code(404);
    echo "Missing: " . htmlspecialchars($uri, ENT_QUOTES);
    exit;
}

// Allow /hub direct access
if (str_starts_with($uri, '/hub/')) {
    $target = $root . $uri;
    if (is_dir($target)) $target = rtrim($target, '/') . '/index.php';
    if (is_file($target)) { require $target; exit; }
    http_response_code(404);
    echo "Missing: " . htmlspecialchars($uri, ENT_QUOTES);
    exit;
}

http_response_code(404);
echo "404: " . htmlspecialchars($uri, ENT_QUOTES);
