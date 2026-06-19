<?php

$normalizeOrigin = static function (string $origin): ?string {
    $origin = trim($origin);

    if ($origin === '') {
        return null;
    }

    $parts = parse_url($origin);

    if (! isset($parts['scheme'], $parts['host'])) {
        return rtrim($origin, '/');
    }

    $port = isset($parts['port']) ? ':' . $parts['port'] : '';

    return "{$parts['scheme']}://{$parts['host']}{$port}";
};

$allowedOrigins = array_values(array_unique(array_filter(array_map(
    $normalizeOrigin,
    array_merge(
        [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
        ],
        explode(',', env('FRONTEND_URL', '')),
    ),
))));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
