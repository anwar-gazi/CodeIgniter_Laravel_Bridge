# Troubleshooting

First identify the failing layer: pre-framework dispatch, Laravel boot, Laravel
application behavior, or CI fallthrough.

```bash
php artisan ci-bridge:verify-runtime
php artisan route:list
php artisan ci-bridge:cache-routes
```

Then request one known Laravel route and one known CI route through the shared
front controller. Check web/PHP and Laravel logs separately.

## Dispatcher errors

| Public response | Likely cause | Fix |
| --- | --- | --- |
| `503 CI Laravel bridge configuration is unavailable.` | `$ciLaravelBridge` is missing/not an array. | Put the stub block before the dispatcher require in the same scope. |
| `503 CI Laravel bridge path [...] is unavailable.` | A required path is empty or not a string. | Correct explicit deployment paths. |
| `503 Laravel route ownership matcher is unavailable.` | Package matcher path is wrong/unreadable. | Verify package install, release path, and web-user read permission. |
| `503 Laravel route ownership manifest is unavailable.` | Manifest was not built, deployed elsewhere, or unreadable. | Regenerate it and align both manifest paths. |
| `503 Laravel route ownership manifest is invalid.` | Wrong schema, invalid regex/data, corruption, or checksum failure. | Rebuild from the current Laravel route cache; never repair by hand. |
| `503 Laravel runtime bootstrap is unavailable.` | Owned request matched but Laravel autoloader/bootstrap is missing. | Fix paths, install dependencies, and check permissions. |

A stale shared manifest path can survive a successful release build. Always
compare the command's reported destination with the active front controller.

## Runtime verification fails

The message identifies a violated contract: PHP not 7.3.x, Laravel not
8.83.29, CommonMark not 1.4.3, missing patch markers, or a later class that
invalidates a security non-applicability assumption. Restore the lock and patch
configuration. Do not weaken `SecurityBaseline`; reassess security and tests.

## Required route file not found

A `route_files` entry with `required => true` resolved to a missing file.
Relative paths start at Laravel's base path. Correct/deploy it, or make it
optional only when absence is genuinely valid.

## Route reaches the wrong framework

If a Laravel route reaches CI, check:

1. manifest rebuilt after the latest route/cache change;
2. front controller reading this release's manifest;
3. correct `base_uri`;
4. domain/scheme constraints and pre-framework host/scheme values;
5. route constraint accepting the decoded path; and
6. route registration timing.

Use `route:list`; source presence alone does not prove registration.

If the matcher sends a request to Laravel but Laravel returns `404`, caches and
manifest are usually out of sync or request identity differs. Rebuild route
cache then manifest and compare host, scheme, base URI, encoding, and
constraints.

Laravel `405` on an owned URI is expected with
`claim_method_mismatches=true`. Add the method or read the split-ownership
tradeoff in [the migration guide](migration-guide.md).

If all CI routes reach Laravel, look for a fallback or broad ordinary route such
as `/{path}`. Fallbacks are rejected by default; broad regular routes can
still own large URI spaces.

## Proxy-constrained route does not match

Pre-framework matching occurs before Laravel trusted-proxy middleware. Configure
front-controller `host` and `scheme` from sanitized proxy values and test
through the production proxy, not only by direct local request.

## Manifest generation errors

- **Fallback rejected:** remove it while CI fallthrough is required.
- **Conditional route rejected:** express ownership with supported path, host,
  scheme, and method constraints, or keep it in CI.
- **Cannot write:** give the build user create/rename access to the manifest
  directory; do not use globally writable permissions.

## Composer audit

SwiftMailer abandonment is an acknowledged warning. Any unignored advisory is a
failure. Compare the host config with the supplied fragment and read
[SECURITY.md](../SECURITY.md); never add a blanket exception.

## Legacy helper or alias is undefined

The current v2 Composer manifest PSR-4 autoloads classes but does not register
`src/helpers.php` under `autoload.files`. A legacy CI integration must
explicitly load it after Composer or maintain a host autoload entry. It is not
needed by the native bridge and can collide with Laravel helper names. See
[Legacy compatibility API](legacy-compatibility-api.md).
