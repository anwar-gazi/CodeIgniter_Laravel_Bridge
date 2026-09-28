<?php

$root = dirname(__DIR__, 2);
$path = isset($argv[1]) ? $argv[1] : '/legacy';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'example.test';
$_SERVER['REQUEST_URI'] = $path;

$ciLaravelBridge = [
    'matcher_path' => $root . '/src/Bridge/RouteOwnershipMatcher.php',
    'manifest_path' => __DIR__ . '/dispatcher_manifest.php',
    'laravel_autoload_path' => $root . '/vendor/autoload.php',
    'laravel_bootstrap_path' => __DIR__ . '/dispatcher_bootstrap.php',
    'base_uri' => '',
];

require $root . '/resources/bridge/dispatch.php';

echo 'ci-owned';
