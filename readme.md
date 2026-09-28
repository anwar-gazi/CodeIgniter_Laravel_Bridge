# CI to Laravel 8 bridge

This package lets a legacy CodeIgniter 3 application and a real Laravel 8
application share one public front controller, one Apache/Nginx virtual host,
and one PHP-FPM service while routes are migrated incrementally.

Laravel-owned requests execute Laravel's native HTTP kernel. All Laravel
middleware, controllers, dependency injection, validation, Eloquent, Blade,
authentication, authorization, queues, events, notifications, and exception
handling therefore behave as Laravel features—not as CodeIgniter emulations.
Requests not owned by Laravel continue into the unchanged CI3 bootstrap.

## Runtime contract

- PHP: exactly `7.3.x` (`7.3.33` is used for dependency resolution and tests)
- Laravel Framework: exactly `8.83.29`
- CommonMark: exactly `1.4.3`, with bounded-input security mitigation
- Web processes: one
- Frameworks booted for each request: one

PHP 7.3 and Laravel 8 are end-of-life. This is a bounded migration runtime, not
a recommendation to start a new PHP 7.3 application. See [SECURITY.md](SECURITY.md)
before deployment. Composer also reports Laravel 8's SwiftMailer dependency as
abandoned; that residual constraint cannot be removed without leaving the
Laravel 8/PHP 7.3 compatibility target.

## Request lifecycle

1. The CI front controller loads the dependency-free bridge dispatcher before
   either Composer autoloader or framework.
2. The dispatcher reads a cached manifest compiled from Laravel's real route
   collection.
3. A Laravel-owned request loads only the Laravel Composer tree and runs
   Laravel's HTTP kernel.
4. Any other request returns from the dispatcher and continues into CI3.

No `artisan serve`, second PHP process, reverse proxy, or network hop is used.
Separate Composer trees are strongly recommended so Laravel's global helpers do
not collide with legacy CI helpers.

## Recommended layout

```text
legacy-project/
├── index.php                 # existing CI front controller plus bridge prelude
├── application/              # existing CI3 application
├── system/                   # existing CI3 framework
├── vendor/                   # existing CI dependencies, if any
└── laravel/                  # standard Laravel 8 application
    ├── artisan
    ├── bootstrap/app.php
    ├── composer.json
    ├── routes/routes_laravel.php
    └── vendor/
```

The web server continues pointing to the legacy project's existing public
entrypoint. The `laravel` directory is not served as a second site.

## Installation

### 1. Prepare the Laravel application's root Composer configuration

Composer blocks the pinned end-of-life dependencies before a package can be
installed. Copy both files from this repository's `patches/` directory into
`laravel/patches/`, then merge
[`host-composer-fragment.json`](resources/composer/host-composer-fragment.json)
into `laravel/composer.json` before resolving dependencies.

The host configuration deliberately:

- permits only six named advisory IDs;
- leaves global advisory blocking enabled;
- applies the two supplied patches;
- makes a patch failure fatal; and
- allows only the required Composer patch plugin.

Run Composer from the Laravel directory:

```bash
composer update --with-all-dependencies --no-interaction
php artisan ci-bridge:verify-runtime
```

The service provider also verifies this baseline whenever Laravel boots. A
missing patch, different framework version, or different CommonMark version
fails closed.

### 2. Publish configuration and the front-controller example

```bash
php artisan vendor:publish --tag=ci-laravel-bridge-config
php artisan vendor:publish --tag=ci-laravel-bridge-dispatcher
```

The second command publishes a reference snippet at
`laravel/bridge/index.php.stub`. Copy that block to the very top of the existing
CI `index.php`, before CI constants, CI bootstrap files, or either Composer
autoload file. Adjust its four absolute paths for the deployment layout.

For a subdirectory deployment, set `base_uri`, for example `/legacy`. If a
trusted reverse proxy terminates TLS, configure `scheme` and optionally `host`
as fixed strings or callbacks. Only trust forwarded headers when the web server
accepts them exclusively from trusted proxies:

```php
'scheme' => static function (array $server) {
    return isset($server['HTTP_X_FORWARDED_PROTO'])
        ? strtolower($server['HTTP_X_FORWARDED_PROTO'])
        : 'http';
},
```

### 3. Register native Laravel routes

The default configuration loads `laravel/routes/routes_laravel.php` in the
`web` middleware group. Configuration paths are resolved from the Laravel
application root unless they are absolute, so they remain portable across
release directories and work with Laravel's configuration cache:

```php
<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/orders/{order}', [OrderController::class, 'show'])
    ->where('order', '[0-9]+')
    ->name('orders.show');
```

The file is loaded by Laravel only. Do not include a native Laravel route file
from `application/config/routes.php`.

If retaining the existing CI-side filename is important, point the published
configuration at it while removing the CI `require_once`:

```php
'route_files' => [
    [
        'path' => dirname(base_path()) . '/application/config/routes_laravel.php',
        'middleware' => ['web'],
        'required' => true,
    ],
],
```

During migration, keep old shim-based definitions in a CI-loaded file and move
routes one at a time into the Laravel-only native file. The same file cannot
safely execute once in CI and once in Laravel because those are different
framework lifecycles.

### 4. Compile route ownership

```bash
php artisan route:clear
php artisan ci-bridge:cache-routes
```

The command compiles Laravel's actual route collection, including route
parameters, domains, schemes, methods, and constraints, then writes the
manifest atomically to `bootstrap/cache/ci-laravel-owned-routes.php`.

By default, a matching URI/domain is Laravel-owned even for an unsupported HTTP
method, so Laravel returns its native `405 Method Not Allowed` response instead
of leaking the request into CI. Laravel fallback routes are rejected while CI
still owns routes because a fallback would claim every request.

Regenerate the manifest after every route or route-provider change. A missing,
invalid, or checksum-damaged manifest returns `503` instead of guessing which
framework owns the request.

## Deployment sequence

Use this order for each release:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan ci-bridge:verify-runtime
php artisan config:cache
php artisan route:cache
php artisan ci-bridge:cache-routes
```

Publish the new code and ownership manifest atomically where possible. Do not
switch traffic to a release whose runtime verification or route compilation
failed.

## Migration workflow

For each migrated endpoint:

1. Implement it as a normal Laravel route/controller/request/service/view.
2. Add Laravel feature tests for middleware, validation, authorization, and the
   response contract.
3. Remove the equivalent route from CI ownership.
4. Recompile the ownership manifest.
5. Verify both the migrated route and representative remaining CI routes.

Laravel and CI receive the same incoming headers and cookies, but their session
formats, authentication state, service containers, configuration, and logs are
independent. Point Laravel at shared domain data explicitly and implement a
project-specific authentication handoff when migrated routes must recognize an
existing CI login. The bridge intentionally does not deserialize arbitrary
legacy session formats.

After the final CI route moves, Laravel may own the public front controller
directly. At that point remove the dispatcher, enable Laravel fallback routes if
needed, and retire the CI bootstrap and its Composer tree.

## Commands

- `php artisan ci-bridge:verify-runtime` verifies exact dependency versions and
  installed security patch markers.
- `php artisan ci-bridge:cache-routes` atomically regenerates route ownership.
- `php artisan ci-bridge:cache-routes --path=/absolute/path.php` overrides the
  configured manifest destination for controlled deployments.

## Development verification

```bash
composer install --no-interaction
composer test
composer check-platform-reqs
composer audit --locked
```

The audit lists the six explicitly ignored advisories with their reasons; it
must not report any unignored advisory. SwiftMailer remains visible as an
abandoned-package report but does not make the audit fail.
