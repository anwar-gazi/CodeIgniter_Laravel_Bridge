# Configuration reference

There are two configuration surfaces: Laravel package configuration used at
boot/build time, and the front-controller array used before either framework
boots. Their `manifest_path` values must identify the same file.

## Laravel package configuration

Publish `config/ci_laravel_bridge.php` with:

```bash
php artisan vendor:publish --tag=ci-laravel-bridge-config
```

### Options

| Option | Default | Behavior |
| --- | --- | --- |
| `manifest_path` | `bootstrap/cache/ci-laravel-owned-routes.php` | Destination for generated ownership. Relative paths resolve from Laravel's base path. |
| `claim_method_mismatches` | `true` | Sends a matching URI/host/scheme to Laravel even if the method is unsupported, preserving Laravel `405`. |
| `allow_fallback_routes` | `false` | Rejects fallback routes while CI fallthrough is needed. |
| `route_files` | one optional `routes/routes_laravel.php` group | Laravel-only route files and their group attributes. |

The manifest writer creates a missing directory with mode `0755`, writes a
temporary file in it, changes that file to `0644`, then renames it over the
destination. The build user needs create/rename permission; the web user needs
read permission.

Turning `claim_method_mismatches` off permits method-level split ownership
between frameworks, but an unsupported or forgotten method may then execute a
CI route. Test every verb before using it.

Keep `allow_fallback_routes` false until CI owns nothing. Usually the bridge
should be removed instead of enabling it at the end.

### Route-file definitions

```php
'route_files' => [
    [
        'path' => 'routes/routes_laravel.php',
        'middleware' => ['web'],
        'required' => true,
    ],
    [
        'path' => 'routes/bridge_api.php',
        'middleware' => ['api'],
        'prefix' => 'api',
        'domain' => '{account}.example.com',
        'as' => 'bridge-api.',
        'required' => true,
    ],
],
```

| Key | Required | Meaning |
| --- | --- | --- |
| `path` | Yes | String path; relative paths resolve from Laravel's base path. |
| `required` | No | A missing truthy-required file fails boot/generation; an optional missing file is skipped. |
| `middleware` | No | Middleware applied to the route group. |
| `prefix` | No | URI prefix applied to the group. |
| `domain` | No | Host pattern compiled into ownership. |
| `as` | No | Route-name prefix. |

The last four attributes are passed to Laravel's router group; null values are
omitted. Route files are loaded only when Laravel's native routes are not
cached, preventing duplicate registration.

## Front-controller configuration

Define `$ciLaravelBridge` at the top of CI's public `index.php`.

### Required paths

| Key | Purpose |
| --- | --- |
| `matcher_path` | Absolute path to `RouteOwnershipMatcher.php`, loaded before Composer. |
| `manifest_path` | Absolute path to the generated manifest. |
| `laravel_autoload_path` | Laravel host's `vendor/autoload.php`. |
| `laravel_bootstrap_path` | Laravel host's `bootstrap/app.php`. |

Each must be a non-empty string. Matcher and manifest are checked on every
request; Laravel files are checked only after ownership matches.

### `base_uri`

Optional public mount prefix. With `'/legacy'`, Laravel route `/orders` can
own `/legacy/orders`, but not `/orders` or `/legacy-other/orders`. Leading
and trailing slashes are normalized.

### `scheme` and `host`

Each may be a fixed string or a callback receiving `$_SERVER`:

```php
'scheme' => static function (array $server) {
    return strtolower($server['HTTP_X_FORWARDED_PROTO'] ?? 'http');
},
'host' => 'www.example.com',
```

Without overrides, scheme comes from `HTTPS` and host from `HTTP_HOST`.
Only read forwarded headers when the web server strips untrusted client values
and accepts them exclusively from known proxies. These values determine
ownership for domain- and scheme-constrained routes.

Host matching ignores case and a trailing numeric port. Bracketed IPv6 retains
the address through its closing bracket. Scheme matching ignores case.

## Generated manifest

Format version `1` includes:

| Field | Meaning |
| --- | --- |
| `format` | Schema version. |
| `generated_at` | UTC ISO 8601 build timestamp. |
| `route_count` | Informational route count. |
| `claim_method_mismatches` | Build-time method policy. |
| `source_hash` | SHA-256 over sorted configured source paths and contents. |
| `routes` | Compiled route metadata and regexes. |
| `checksum` | SHA-256 over routes, method policy, and source hash. |

Do not edit the manifest. Use `ci-bridge:cache-routes`.

## Cache order

```bash
php artisan config:cache
php artisan route:cache
php artisan ci-bridge:cache-routes
```

Rebuild after changes to routes, providers, route-group configuration, or any
environment value affecting route registration.
