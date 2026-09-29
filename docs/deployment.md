# Deployment and operations

The deployment invariant is that Laravel's route cache and the bridge ownership
manifest describe the same release. Build and publish them together.

## Build sequence

Run from the host Laravel application:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan ci-bridge:verify-runtime
php artisan config:cache
php artisan route:cache
php artisan ci-bridge:cache-routes
```

This order installs and patches the locked graph, verifies it, freezes target
configuration, builds Laravel's authoritative route cache, then compiles
ownership from that collection. Do not change routes, providers, or
route-affecting environment after the last step.

An alternate manifest can be built with:

```bash
php artisan ci-bridge:cache-routes --path=/absolute/release/owned-routes.php
```

The front controller must point to the exact same file.

## Publishing

Prefer immutable release directories and an atomic current-release switch.
Publish as one unit:

- Laravel and CI application code;
- installed Laravel `vendor/` with applied patches;
- cached Laravel configuration and routes;
- generated ownership manifest; and
- matching front-controller path configuration.

Do not enable traffic after any failed build step. Atomic manifest writing alone
does not make the whole multi-directory release atomic.

## Permissions

The build user needs to create and rename the manifest in its destination. The
web user needs read access to the matcher, manifest, Laravel autoloader,
bootstrap/application files, and normal Laravel storage/cache paths.

A missing manifest directory is created as `0755`; the manifest is `0644`.
Set parent ownership appropriately instead of making directories world-writable.

## Pre-traffic checklist

- [ ] `ci-bridge:verify-runtime` succeeds.
- [ ] Ownership count/path match expectations.
- [ ] A known Laravel route returns its expected response.
- [ ] Laravel middleware, authentication, and CSRF are active.
- [ ] Unsupported method on an owned URI returns Laravel `405` by default.
- [ ] A representative CI route still works.
- [ ] A similar unowned URI remains with CI.
- [ ] Domain/HTTPS constraints work through the production proxy.
- [ ] Both frameworks write to their expected logs.

Do not use only `/` as a smoke test; it may be CI-owned and proves nothing
about Laravel boot.

## Monitoring

Where possible, add a shared request ID at the web-server layer. Alert on bridge
`503`, changes in `404`/`405`, runtime-baseline failures, manifest/cache
permission errors, auth redirect loops, CSRF failures, queue/mail failures, and
latency changes on migrated routes.

Public dispatcher errors are deliberately generic. Use PHP-FPM, web-server,
deployment, and Laravel logs to find the underlying cause.

## Rollback

Roll back the complete release unit, not only the manifest. Code, route/config
caches, patched dependencies, manifest, and front-controller paths must align.
Switch to the prior complete release, reload PHP-FPM/opcache if required, and
repeat Laravel and CI smoke tests.

Never copy an old manifest into new code. Its checksum may be valid while its
route meaning is stale.

## Emergency route change

If an in-place route change cannot wait for a normal release:

```bash
php artisan route:clear
php artisan route:cache
php artisan ci-bridge:cache-routes
```

Verify both routing paths immediately. A complete immutable release remains
safer and easier to roll back.

## Security operations

Do not suppress runtime verification, blanket-ignore advisories, trust
forwarded headers from arbitrary clients, or expose Laravel as a second public
site. Reassess all exceptions before dependency changes. The authoritative
rationale is [SECURITY.md](../SECURITY.md).
