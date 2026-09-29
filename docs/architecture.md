# Architecture

## Purpose

The bridge supports incremental migration from CodeIgniter 3 to Laravel 8. It
makes the ownership decision before either application boots, then gives the
selected framework the original request. This avoids emulating Laravel inside
CI, proxying between application servers, or loading both frameworks together.

## Components

| Component | Location | Responsibility |
| --- | --- | --- |
| Service provider | `src/CiLaravelBridgeServiceProvider.php` | Registers runtime checks, route files, commands, and publishable resources. |
| Path resolver | `src/Bridge/BridgePathResolver.php` | Resolves host-relative and absolute paths without config-time Laravel helpers. |
| Manifest generator | `src/Bridge/RouteOwnershipGenerator.php` | Compiles Laravel's real route collection into standalone match data. |
| Manifest writer | `src/Bridge/RouteOwnershipManifestWriter.php` | Publishes the manifest through a same-directory temporary file and rename. |
| Matcher | `src/Bridge/RouteOwnershipMatcher.php` | Validates the manifest and matches a request without framework dependencies. |
| Dispatcher | `resources/bridge/dispatch.php` | Selects Laravel or returns control to CI before either framework boots. |
| Runtime verifier | `src/Security/SecurityBaseline.php` | Enforces dependency versions and patch markers. |

## Request lifecycle

1. The web server sends the request to the existing CI `index.php`. The copied
   bridge prelude runs before constants, Composer, or either framework.
2. The dispatcher validates four paths: matcher, manifest, Laravel autoloader,
   and Laravel bootstrap. Matcher and manifest must exist before matching.
3. It resolves method and URI from `$_SERVER`, host from `HTTP_HOST`, scheme
   from `HTTPS`, and the optional deployment prefix from `base_uri`.
4. It loads the PHP manifest and verifies format, route entry types, and its
   SHA-256 checksum.
5. It normalizes the request and checks compiled path, host, scheme, and method
   rules.
6. An owned request loads Laravel's autoloader and bootstrap, resolves the HTTP
   kernel, then calls `handle`, `send`, and `terminate`. The dispatcher
   exits afterward.
7. An unowned request returns to `index.php`, where the unchanged CI bootstrap
   continues. Laravel is not loaded.

All validation failures in the pre-framework ownership path return a generic
plain-text `503`. Falling through on corrupt state could bypass Laravel
middleware or expose an old CI endpoint, so this behavior is deliberately
fail-closed.

## Matching semantics

The matcher:

1. removes the query string;
2. normalizes blank path to `/` and adds one leading slash;
3. removes `base_uri`, or rejects requests outside that prefix;
4. removes a trailing slash except at root;
5. applies raw URL decoding;
6. lowercases the host and removes a numeric port;
7. lowercases scheme and uppercases method.

Routes are evaluated by compiled path regex, optional host regex, and optional
scheme list. With the default `claim_method_mismatches=true`, a matching
URI/host/scheme goes to Laravel even for an unsupported method, allowing
Laravel's native `405` response. Laravel performs the final route selection;
the dispatcher decides only framework ownership.

The manifest checksum detects incomplete or accidental modification. It is not
a cryptographic signature against an attacker who can rewrite application
files.

## Build-time route lifecycle

When Laravel routes are not cached, the service provider loads configured
`route_files` as router groups. When Laravel's route cache is active, it does
not load them again.

`ci-bridge:cache-routes` accepts Laravel 8's ordinary or compiled route
collection. For each route it records the name, URI, methods, schemes, and
Symfony-compiled path and host regexes. It hashes sorted route-source paths and
contents, calculates a checksum, and publishes the manifest atomically.

The source hash records what built the manifest; the dispatcher does not compare
it with live files on every request. Regeneration is a deployment responsibility.

## Fail-closed conditions

- missing or malformed bridge configuration;
- missing matcher or ownership manifest;
- manifest that does not return an array;
- unsupported format or invalid route entries;
- invalid regular expressions or checksum mismatch; and
- missing Laravel bootstrap files after a route is determined to be owned.

Laravel bootstrap files are checked only for an owned request, so unowned CI
routes can still run if those files are unavailable.

## Isolation boundaries

The applications share the incoming request, cookies, PHP service, and any
infrastructure explicitly configured in both. They do not implicitly share:

- sessions, authentication state, or CSRF tokens;
- dependency-injection containers or configuration;
- exception handling, logs, or request IDs;
- database transaction scope; or
- queues, mail, events, and scheduled work.

Use explicit data contracts. Authentication handoff must be host-specific; the
bridge deliberately does not deserialize arbitrary legacy sessions.

## Unsupported route features

- Laravel fallback routes are rejected while
  `allow_fallback_routes=false`, because they own every remaining URI.
- Symfony route conditions are rejected because the dependency-free matcher
  cannot reproduce arbitrary condition logic.
- Runtime-created route changes are invisible until the manifest is rebuilt.

## End state

After the last CI route moves, point the web server to Laravel's normal public
entrypoint, remove the dispatcher, enable a Laravel fallback if needed, and
retire CI and its Composer tree. The bridge is migration infrastructure, not the
target architecture.
