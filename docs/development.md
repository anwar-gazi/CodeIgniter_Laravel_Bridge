# Package development

Use PHP `7.3.x` for the complete suite. Composer resolves for `7.3.33`, and
the runtime baseline rejects PHP 7.4+ even if files can otherwise load.

```bash
composer install --no-interaction
composer test
composer check-platform-reqs
composer audit --locked
git diff --check
```

`composer test` runs PHPUnit first and `php tests/regression.php` second.
Use `composer test:phpunit` or `composer test:legacy` while iterating.
SwiftMailer may be reported as abandoned; any unignored advisory is a failure.

## Repository map

| Path | Purpose |
| --- | --- |
| `config/` | Published package defaults. |
| `patches/` | Reproducible security backports. |
| `resources/bridge/` | Dependency-free dispatcher and published stub. |
| `resources/composer/` | Host Composer fragment. |
| `src/Bridge/` | Path resolution, manifest generation/writing/matching. |
| `src/Console/` | Artisan commands. |
| `src/Security/` | Runtime and patch verification. |
| `src/CiLaravelBridgeServiceProvider.php` | Laravel integration point. |
| `src/*.php`, `src/helpers.php` | Retained legacy API. |
| `tests/Unit/` | Bridge and security behavior. |
| `tests/Integration/` | Subprocess dispatch boundary. |
| `tests/regression.php` | Dependency-free legacy regressions. |

## Change responsibilities

- **Matcher:** keep it dependency-free. Test normalization, base URI, encoded
  paths, methods, hosts/ports/IPv6, schemes, regex errors, schema, and checksum.
- **Generator:** test ordinary and compiled route collections, constraints,
  domains, schemes, fallback/condition rejection, and source hashes.
- **Manifest schema:** deliberately bump format and update matcher/generator
  together. Old manifests must fail closed.
- **Dispatcher:** preserve dependency-free CI fallthrough and generic public
  errors. Prove Laravel dispatch and CI fallthrough in the subprocess test.
- **Provider/commands:** test cached/uncached routes, optional/required files,
  absolute/relative paths, config cache, and write failures.
- **Security:** update patches, marker checks, tests, `composer.json`, host
  fragment, and `SECURITY.md` together. Never broaden ignores just to resolve.
- **Legacy API:** add focused cases to `tests/regression.php`.

## Coding constraints

- Maintain PHP 7.3 syntax: no typed properties, arrow functions, union types,
  attributes, constructor promotion, or `match`.
- Keep ownership selection free from Laravel/Symfony dependencies.
- Fail closed when ownership cannot be trusted.
- Do not hand-edit generated manifests.
- Keep paths portable across releases and Windows tests.
- Preserve native Laravel behavior after selection; middleware/controller logic
  does not belong in the dispatcher.

## Host integration verification

For integration changes, also test an official Laravel 8 host skeleton:

```bash
php artisan ci-bridge:verify-runtime
php artisan route:list
php artisan route:clear
php artisan ci-bridge:cache-routes
php artisan route:cache
php artisan ci-bridge:cache-routes
```

Exercise native responses, middleware, constraints, domains, Laravel `405`,
and CI fallthrough through the shared front controller.

Update the owning guide with behavior changes: architecture, installation,
configuration, migration, deployment, troubleshooting, legacy API, security
policy, and changelog as applicable.
