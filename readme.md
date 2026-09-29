# CI to Laravel 8 bridge

`anwargazi/ci-laravel-support` lets a legacy CodeIgniter 3 application and a
real Laravel 8 application share one public front controller. It is designed
for route-by-route migrations where running a second PHP service or moving the
whole application at once is not practical.

Laravel-owned requests run Laravel's native HTTP kernel. Requests that are not
owned by Laravel return to the original CodeIgniter bootstrap unchanged. Only
one framework is booted for any request.

> This is a bounded migration runtime. PHP 7.3, Laravel 8, and SwiftMailer are
> end-of-life. Read [the security policy](SECURITY.md) before production use.

## Start here

| If you need to... | Read... |
| --- | --- |
| Understand the design and request flow | [Architecture](docs/architecture.md) |
| Install the bridge in a CI3/Laravel host | [Installation](docs/installation.md) |
| Look up every configuration option | [Configuration reference](docs/configuration.md) |
| Move an endpoint from CI3 to Laravel | [Route migration guide](docs/migration-guide.md) |
| Develop or test this package | [Development guide](docs/development.md) |
| Release, deploy, verify, or roll back | [Deployment and operations](docs/deployment.md) |
| Diagnose a failure | [Troubleshooting](docs/troubleshooting.md) |
| Maintain the older CI-side helper layer | [Legacy compatibility API](docs/legacy-compatibility-api.md) |
| Review security exceptions | [Security policy](SECURITY.md) |
| Review release history | [Changelog](CHANGELOG.md) |

The [documentation index](docs/README.md) provides role-based reading paths.

## Runtime contract

- PHP: exactly `7.3.x`; dependency resolution and tests target `7.3.33`.
- Laravel Framework: exactly `8.83.29`.
- `league/commonmark`: exactly `1.4.3`, with the supplied resource-limit patch.
- One public front controller, virtual host, and PHP-FPM service.
- One framework booted per request.
- Separate Composer dependency trees for CI3 and Laravel are strongly recommended.

The service provider checks this contract whenever Laravel boots. A version
change or missing patch marker fails closed.

## How routing works

```text
HTTP request
    |
legacy index.php
    |
dependency-free dispatcher
    |
    +-- checksummed manifest owns route --> Laravel HTTP kernel --> response
    |
    +-- route is not owned --------------> existing CI3 bootstrap
```

The dispatcher does not load Composer or either framework until it has matched
the request against a cached manifest. The manifest comes from Laravel's real
route collection, preserving path constraints, domains, schemes, methods, and
Laravel's `405 Method Not Allowed` ownership behavior.

## Minimal installation outline

1. Place a standard Laravel 8 application beside the legacy CI3 application.
2. Merge [the host Composer fragment](resources/composer/host-composer-fragment.json)
   into Laravel's `composer.json` and copy both files from [`patches/`](patches/).
3. Resolve dependencies and verify the pinned runtime.
4. Publish the bridge config and front-controller example.
5. Add the dispatcher block at the very top of CI's `index.php`.
6. Add native Laravel routes and compile the ownership manifest.

```bash
composer update --with-all-dependencies --no-interaction
php artisan ci-bridge:verify-runtime
php artisan vendor:publish --tag=ci-laravel-bridge-config
php artisan vendor:publish --tag=ci-laravel-bridge-dispatcher
php artisan route:clear
php artisan ci-bridge:cache-routes
```

Follow the full [installation guide](docs/installation.md) before deployment.

## Commands

| Command | Purpose |
| --- | --- |
| `php artisan ci-bridge:verify-runtime` | Verifies exact dependency versions and security patch markers. |
| `php artisan ci-bridge:cache-routes` | Atomically regenerates route ownership. |
| `php artisan ci-bridge:cache-routes --path=/absolute/file.php` | Uses a controlled alternate manifest path. |
| `composer test` | Runs PHPUnit and the dependency-free legacy regression suite. |

## Important boundaries

- Laravel and CI3 do not automatically share containers, sessions,
  authentication, CSRF tokens, configuration, exception handling, or logs.
- Keep Laravel fallback routes disabled while CI3 owns any route.
- Regenerate the manifest after every route or route-provider change. A missing
  or invalid manifest returns `503`; the bridge never guesses.
- The bridge does not provide cross-framework authentication. Build and test an
  application-specific handoff when Laravel must recognize a CI login.
- The legacy compatibility API is retained for old CI-side integrations; it is
  not part of the native Laravel request path.

## Recommended layout

```text
legacy-project/
|-- index.php                 # existing CI front controller + bridge prelude
|-- application/             # existing CI3 application
|-- system/                  # existing CI3 framework
|-- vendor/                  # legacy dependencies, if any
+-- laravel/                 # standard Laravel 8 application
    |-- artisan
    |-- bootstrap/app.php
    |-- bootstrap/cache/ci-laravel-owned-routes.php
    |-- composer.json
    |-- routes/routes_laravel.php
    +-- vendor/
```

The web root remains the legacy application's entrypoint. The Laravel directory
is not a second site and does not require `artisan serve`.

## Quick verification

```bash
composer install --no-interaction
composer test
composer check-platform-reqs
composer audit --locked
```

In a host application, test a Laravel route, a representative CI route, and an
unsupported method on the Laravel URI. See the complete
[deployment checklist](docs/deployment.md).
