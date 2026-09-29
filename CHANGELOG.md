# Changelog
## v2.0.2 29-Sep-2026 11:56 AM +06
- docs: publish complete developer documentation
  - Reworked the root README into a concise project entry point with a runtime contract, routing overview, installation outline, command reference, migration boundaries, and task-oriented navigation.
  - Added role-based documentation for architecture, installation, configuration, endpoint migration, package development, deployment and operations, and troubleshooting.
  - Documented the full pre-framework dispatch lifecycle, fail-closed ownership model, route-cache and manifest relationship, application isolation boundaries, supported route semantics, and final bridge-retirement path.
  - Added an explicit legacy compatibility API reference covering route shims, controller binding, requests, Blade views and components, Eloquent, cache, environment loading, response handling, helpers, aliases, and current helper-loading behavior.
  - Added operational checklists for immutable releases, permissions, production smoke tests, monitoring, rollback, proxy-derived host and scheme handling, authentication/CSRF boundaries, and common 503/404/405 failure modes.
  - Verified all local documentation links, Markdown whitespace and code-fence integrity, the PHP 7.3 platform requirements, 34 PHPUnit tests with 72 assertions, the legacy regression suite, and the locked Composer audit.

## v2.0.1 28-Sep-2026 08:01 PM +06
- fix(runtime): complete Laravel 8 host discovery, cached-route, and URI matching compatibility
  - Replaced the unavailable Laravel 8 `bootstrap_path()` config helper with portable application-relative paths and centralized absolute-path resolution.
  - Applied the same path resolution to route loading, required-file validation, manifest source hashing, configured manifest destinations, and `--path` overrides.
  - Accepted Laravel's `CompiledRouteCollection` as well as its uncached `RouteCollection`, allowing `ci-bridge:cache-routes` to run after `php artisan route:cache` in the documented deployment order.
  - Aligned pre-framework ownership matching with Laravel 8's raw URL decoding and trailing-slash behavior, including encoded-slash handling.
  - Added regression coverage for helper-free configuration, Unix and Windows paths, compiled route collections, and Laravel-equivalent URI normalization.
  - Verified package auto-discovery, runtime security checks, native route listing, route caching, ownership generation, Laravel-kernel responses, domain routes, constraints, Laravel 405 responses, and CI fallthrough in an official Laravel 8 application skeleton.

## v2.0.0 28-Sep-2026 03:11 PM +06
- feat(runtime): replace the CI-owned Laravel emulation path with a native Laravel 8 HTTP-kernel bridge
  - **Single-process framework dispatch**:
    - Added a dependency-free pre-autoload dispatcher that selects Laravel or CodeIgniter before either framework boots.
    - Added configurable Laravel bootstrap, Composer autoload, ownership manifest, base-URI, scheme, and host resolution paths.
    - Added an end-to-end subprocess test proving Laravel-owned requests execute an HTTP kernel while legacy requests continue to CI.
  - **Native Laravel route ownership**:
    - Added Laravel route collection compilation with methods, schemes, domains, parameters, and Symfony constraint regexes.
    - Added checksum validation, strict manifest schema checks, atomic writes, Laravel-native 405 ownership, and fallback-route rejection during coexistence.
    - Added the `ci-bridge:cache-routes` command, optional/required route-file handling, package configuration, and automatic service-provider discovery.
    - Respected Laravel's native route cache lifecycle to prevent duplicate route registration in cached deployments.
  - **Pinned PHP 7.3 Laravel runtime**:
    - Replaced the Illuminate 6 component set with the complete Laravel Framework 8.83.29 dependency graph.
    - Pinned CommonMark 1.4.3 and PHPUnit 9.6.37 for PHP 7.3.33 compatibility.
    - Added the `ci-bridge:verify-runtime` command and a fail-closed application-boot baseline for exact versions and installed patch markers.
  - **Security maintenance for end-of-life dependencies**:
    - Added a reproducible Laravel email-validation and SwiftMailer address hardening patch.
    - Added core CommonMark document and per-line work bounds for the published parser denial-of-service advisories.
    - Documented two Laravel advisories whose affected later-version implementations are absent from Laravel 8.83.29.
    - Kept global Composer advisory blocking enabled while limiting exceptions to six named IDs with explicit reasons and fatal patch failures.
    - Documented Laravel 8's residual abandoned SwiftMailer dependency and kept it visible as a non-failing audit report while advisory failures remain enabled.
  - **Migration and deployment documentation**:
    - Documented the one-server/two-application layout, separate autoload trees, native route-file transition, deployment order, trusted-proxy handling, manifest regeneration, authentication boundaries, and final CI retirement.
  - **Automated coverage**:
    - Added unit and integration coverage for route matching/generation, host and scheme constraints, base URIs, 405 behavior, fallback rejection, malformed/tampered manifests, security backports, security baseline verification, and framework dispatch.
    - Retained the v1 dependency-free compatibility regression suite as a required Composer test stage.

## v1.0.0 28-Sep-2026 02:03 PM +06
- fix(compatibility): harden Laravel-style routing, requests, responses, Blade rendering, configuration, and release verification for CodeIgniter 3
  - **HTTP Routing and Controller Dispatch ([`Route.php`](src/Route.php) / [`LaravelController.php`](src/LaravelController.php))**:
    - Preserved GET, POST, and other verb-specific actions registered against the same URI by compiling routes into CodeIgniter 3's nested HTTP-method format.
    - Added PUT, PATCH, DELETE, OPTIONS, and complete `Route::any()` verb support, including HEAD.
    - Bound `_remap()` execution to the matched route action instead of the potentially overwritten CodeIgniter method value.
  - **Request Input Semantics ([`Request.php`](src/Request.php))**:
    - Corrected missing input and query values to return their supplied defaults.
    - Corrected `has()` so absent keys return `false` while explicitly present null-valued keys remain detectable.
    - Retained POST precedence when GET and POST contain the same input key.
  - **Response Status, Headers, and Downloads ([`LaravelResponseBuilder.php`](src/LaravelResponseBuilder.php))**:
    - Applied fluent status codes to JSON, text, and streamed responses when no method-level status is supplied.
    - Applied fluent headers consistently to JSON, text, streamed, redirect, raw-content, and download responses.
    - Hardened download filenames against response-header injection and guarded output-buffer cleanup.
  - **Blade and View Safety ([`BladeEngine.php`](src/BladeEngine.php) / [`View.php`](src/View.php))**:
    - Escaped literal component attributes safely when generating compiled PHP, including values containing apostrophes.
    - Replaced exception-driven layout fallback with explicit Blade view existence checks.
    - Prevented rendered view exceptions from disclosing messages, paths, and stack traces to users while retaining CodeIgniter error logging.
  - **Environment, Cache, and Compatibility Aliases ([`Env.php`](src/Env.php) / [`CacheEngine.php`](src/CacheEngine.php) / [`helpers.php`](src/helpers.php))**:
    - Preserved host-provided environment values over `.env` entries, rejected invalid environment keys, and surfaced unreadable environment files.
    - Added cache configuration support for Illuminate configuration repository objects as well as arrays.
    - Restored the documented lazy `Laravel_Controller` compatibility alias and aligned alias documentation with runtime behavior.
  - **Automated Regression Coverage ([`regression.php`](tests/regression.php))**:
    - Added dependency-free coverage for HTTP verb route preservation, route-action dispatch, request defaults, Blade attribute compilation, environment precedence, response status handling, and the legacy controller alias.
    - Added `composer test` as the package-level regression command.
    - Verified all source and test files under PHP 7.3.33 with no syntax errors.
    - Verified the regression suite passes with zero failures and Composer package validation succeeds.
