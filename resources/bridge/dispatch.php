<?php

// This file is intentionally dependency-free until Laravel owns the request.
// Define $ciLaravelBridge in the CI front controller before requiring it.
$bridgeFail = static function (string $message): void {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
};

if (!isset($ciLaravelBridge) || !is_array($ciLaravelBridge)) {
    $bridgeFail('CI Laravel bridge configuration is unavailable.');
}

$requiredPaths = [
    'matcher_path',
    'manifest_path',
    'laravel_autoload_path',
    'laravel_bootstrap_path',
];

foreach ($requiredPaths as $key) {
    if (empty($ciLaravelBridge[$key]) || !is_string($ciLaravelBridge[$key])) {
        $bridgeFail("CI Laravel bridge path [{$key}] is unavailable.");
    }
}

if (!is_file($ciLaravelBridge['matcher_path'])) {
    $bridgeFail('Laravel route ownership matcher is unavailable.');
}

if (!is_file($ciLaravelBridge['manifest_path'])) {
    $bridgeFail('Laravel route ownership manifest is unavailable.');
}

require_once $ciLaravelBridge['matcher_path'];

$resolveRequestValue = static function ($configured, string $fallback) {
    if (is_callable($configured)) {
        return (string) $configured($_SERVER);
    }

    return is_string($configured) && $configured !== '' ? $configured : $fallback;
};

$https = isset($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== ''
    && strtolower((string) $_SERVER['HTTPS']) !== 'off';

$scheme = $resolveRequestValue($ciLaravelBridge['scheme'] ?? null, $https ? 'https' : 'http');
$host = $resolveRequestValue($ciLaravelBridge['host'] ?? null, $_SERVER['HTTP_HOST'] ?? '');

try {
    $manifest = require $ciLaravelBridge['manifest_path'];
    if (!is_array($manifest)) {
        throw new \RuntimeException('The route ownership manifest did not return an array.');
    }

    $laravelOwnsRequest = (new \AnwarGazi\CiLaravelSupport\Bridge\RouteOwnershipMatcher())->matches(
        $manifest,
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        $host,
        $_SERVER['REQUEST_URI'] ?? '/',
        $scheme,
        (string) ($ciLaravelBridge['base_uri'] ?? '')
    );
} catch (\Throwable $exception) {
    $bridgeFail('Laravel route ownership manifest is invalid.');
}

if (!$laravelOwnsRequest) {
    unset($bridgeFail, $requiredPaths, $resolveRequestValue, $https, $scheme, $host, $manifest, $laravelOwnsRequest);

    return;
}

if (!is_file($ciLaravelBridge['laravel_autoload_path'])
    || !is_file($ciLaravelBridge['laravel_bootstrap_path'])) {
    $bridgeFail('Laravel runtime bootstrap is unavailable.');
}

require $ciLaravelBridge['laravel_autoload_path'];

$app = require $ciLaravelBridge['laravel_bootstrap_path'];
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
exit;
