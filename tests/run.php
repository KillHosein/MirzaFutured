<?php

require __DIR__ . '/../app/lib/app_bootstrap.php';

function assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n\n");
        exit(1);
    }
}

function assert_contains($needle, $haystack, $message)
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: $message\nNeedle: " . var_export($needle, true) . "\nHaystack: " . var_export($haystack, true) . "\n\n");
        exit(1);
    }
}

$cfg = mirza_app_build_config([
    'SCRIPT_NAME' => '/app/index.php',
    'HTTP_HOST' => 'example.com',
    'HTTP_X_FORWARDED_PROTO' => 'https',
]);
assert_same('/app', $cfg['basename'], 'basename for /app/index.php');
assert_same('/app/', $cfg['prefix'], 'prefix for /app/index.php');
assert_same('/app/', $cfg['assetPrefix'], 'assetPrefix for /app/index.php');
assert_same('https://example.com/api', $cfg['apiUrl'], 'apiUrl for /app/index.php');

$cfg = mirza_app_build_config([
    'SCRIPT_NAME' => '/index.php',
    'HTTP_HOST' => 'localhost:8080',
    'REQUEST_SCHEME' => 'http',
]);
assert_same('/', $cfg['basename'], 'basename for /index.php');
assert_same('/', $cfg['prefix'], 'prefix for /index.php');
assert_same('/', $cfg['assetPrefix'], 'assetPrefix for /index.php');
assert_same('http://localhost:8080/api', $cfg['apiUrl'], 'apiUrl for /index.php');

$cfg = mirza_app_build_config([
    'SCRIPT_NAME' => '/foo/bar/index.php',
    'HTTP_HOST' => 'example.com',
    'HTTP_X_FORWARDED_PROTO' => 'https, http',
]);
assert_same('/foo/bar', $cfg['basename'], 'basename for /foo/bar/index.php');
assert_same('/foo/bar/', $cfg['prefix'], 'prefix for /foo/bar/index.php');
assert_same('https://example.com/foo/api', $cfg['apiUrl'], 'apiUrl for nested path');

$nonce = 'abc123';
$csp = mirza_app_build_csp($nonce);
assert_contains("nonce-$nonce", $csp, 'CSP contains nonce');
assert_contains("default-src 'self'", $csp, 'CSP has default-src self');
assert_contains("object-src 'none'", $csp, 'CSP blocks objects');

fwrite(STDOUT, "OK\n");

