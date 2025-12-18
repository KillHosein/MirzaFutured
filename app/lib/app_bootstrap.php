<?php

function mirza_app_build_config(array $server): array
{
    $scriptName = $server['SCRIPT_NAME'] ?? '';
    $scriptDir = str_replace('\\', '/', dirname($scriptName));
    if ($scriptDir === '.' || $scriptDir === '') {
        $scriptDir = '';
    } elseif ($scriptDir !== '/') {
        $scriptDir = '/' . ltrim($scriptDir, '/');
        $scriptDir = rtrim($scriptDir, '/');
    } else {
        $scriptDir = '/';
    }
    $basename = $scriptDir === '' ? '/' : $scriptDir;
    $prefix = $basename === '/' ? '/' : $basename . '/';
    $assetPrefix = $prefix;

    $rootForApi = $basename === '/' ? '/' : rtrim(dirname($basename), '/');
    if ($rootForApi === '' || $rootForApi === '.') {
        $rootForApi = '/';
    }
    $apiPath = $rootForApi === '/' ? '/api' : $rootForApi . '/api';

    $forwardedProto = $server['HTTP_X_FORWARDED_PROTO'] ?? '';
    if (is_string($forwardedProto) && $forwardedProto !== '') {
        $scheme = explode(',', $forwardedProto)[0];
    } elseif (!empty($server['REQUEST_SCHEME'])) {
        $scheme = $server['REQUEST_SCHEME'];
    } else {
        $https = $server['HTTPS'] ?? '';
        $scheme = (!empty($https) && $https !== 'off') ? 'https' : 'http';
    }
    $host = $server['HTTP_HOST'] ?? 'localhost';
    $apiUrl = rtrim($scheme . '://' . $host, '/') . $apiPath;

    return [
        'basename' => $basename,
        'prefix' => $prefix,
        'apiUrl' => $apiUrl,
        'assetPrefix' => $assetPrefix,
    ];
}

function mirza_app_create_nonce(): string
{
    return base64_encode(random_bytes(16));
}

function mirza_app_build_csp(string $nonce): string
{
    return implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "script-src 'self' 'nonce-$nonce'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob: https:",
        "font-src 'self' data:",
        "connect-src 'self' https:",
        "form-action 'self'",
    ]);
}

function mirza_app_send_headers(string $csp): void
{
    if (headers_sent()) {
        return;
    }
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Content-Security-Policy: ' . $csp);
}

