# Installation

All Composer and Artisan commands in this guide run from the Laravel application
directory.

## Prerequisites

- PHP `7.3.x` (the resolution target is `7.3.33`);
- a standard Laravel `8.83.29` application;
- an editable CI3 public front controller;
- filesystem access from that controller to Laravel's package, bootstrap, and
  cache files; and
- acceptance of the end-of-life constraints in [SECURITY.md](../SECURITY.md).

Use separate Composer trees. Sharing `vendor/` can make Laravel's global
helpers and dependency graph collide with the legacy application.

## 1. Create the layout

```text
/var/www/legacy/
|-- index.php
|-- application/
|-- system/
|-- vendor/
+-- laravel/
    |-- artisan
    |-- bootstrap/app.php
    |-- composer.json
    +-- vendor/
```

Keep the web server pointed at the legacy entrypoint. Do not expose Laravel as
a second site.

## 2. Add the package and security patches

Copy both repository patch files into the host Laravel application's
`patches/` directory. Merge
[`resources/composer/host-composer-fragment.json`](../resources/composer/host-composer-fragment.json)
into the host's `composer.json`, preserving unrelated host configuration.

The merged file must retain the package at `^3.0`, patch plugin `1.7.3`,
permission for that plugin, fatal patch failures, both patch mappings, and the
six named advisory exceptions. Do not disable advisory blocking globally.

```bash
composer update --with-all-dependencies --no-interaction
php artisan ci-bridge:verify-runtime
```

Verification must pass before integration proceeds.

## 3. Publish resources

```bash
php artisan vendor:publish --tag=ci-laravel-bridge-config
php artisan vendor:publish --tag=ci-laravel-bridge-dispatcher
```

This publishes `config/ci_laravel_bridge.php` and
`bridge/index.php.stub`. Publishing the stub does not activate the bridge.

## 4. Add Laravel-only routes

Create `routes/routes_laravel.php` or configure another route file:

```php
<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/orders/{order}', [OrderController::class, 'show'])
    ->where('order', '[0-9]+')
    ->name('orders.show');
```

The default group uses `web` middleware. Never include this native file from
CI's route configuration. If it must remain outside Laravel, use an absolute
path and remove the CI `require_once`. See [Configuration](configuration.md).

## 5. Add the dispatcher prelude

Copy the published stub block to the top of CI's `index.php`, after `<?php`
but before constants, bootstrap files, or either Composer autoloader. Adjust all
paths:

```php
$ciLaravelBridge = [
    'matcher_path' => __DIR__ . '/laravel/vendor/anwargazi/ci-laravel-support/src/Bridge/RouteOwnershipMatcher.php',
    'manifest_path' => __DIR__ . '/laravel/bootstrap/cache/ci-laravel-owned-routes.php',
    'laravel_autoload_path' => __DIR__ . '/laravel/vendor/autoload.php',
    'laravel_bootstrap_path' => __DIR__ . '/laravel/bootstrap/app.php',
    'base_uri' => '',
];

require __DIR__ . '/laravel/vendor/anwargazi/ci-laravel-support/resources/bridge/dispatch.php';

// Existing CI index.php continues here.
```

For a subdirectory deployment, set `base_uri`, for example `/legacy`.

Behind a trusted TLS proxy, configure fixed or callback `scheme`/`host`
values. Only read forwarded headers after the web server has removed client
values and accepts replacements solely from trusted proxies.

## 6. Build ownership

For initial development:

```bash
php artisan route:clear
php artisan route:list
php artisan ci-bridge:cache-routes
```

Confirm the route list, reported count, and destination. The default generated
file is `bootstrap/cache/ci-laravel-owned-routes.php`; do not edit it.

For production artifacts:

```bash
php artisan config:cache
php artisan route:cache
php artisan ci-bridge:cache-routes
```

## 7. Verify the boundary

Test:

1. a Laravel-owned route and its middleware;
2. an unsupported method on that URI, expecting Laravel `405`;
3. a representative CI route;
4. a missing route, expecting existing CI behavior;
5. any host, scheme, parameter, or base-URI constraints; and
6. authentication/CSRF through the explicit application-specific handoff.

Check Laravel and CI logs independently.
