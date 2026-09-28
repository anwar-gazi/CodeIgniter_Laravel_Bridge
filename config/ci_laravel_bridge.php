<?php

return [
    // Relative paths are resolved from the Laravel application's base path.
    'manifest_path' => 'bootstrap/cache/ci-laravel-owned-routes.php',

    // When true, a matching Laravel URI/domain is owned by Laravel even when
    // the incoming HTTP method is unsupported, preserving Laravel 405 behavior.
    'claim_method_mismatches' => true,

    // Keep disabled while CodeIgniter owns any routes. A Laravel fallback
    // route would otherwise claim every request before CI can handle it.
    'allow_fallback_routes' => false,

    'route_files' => [
        [
            'path' => 'routes/routes_laravel.php',
            'middleware' => ['web'],
            'required' => false,
        ],
    ],
];
