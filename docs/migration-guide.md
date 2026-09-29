# Route migration guide

Migrate one coherent endpoint or workflow at a time. The bridge changes request
ownership; it does not automatically move state, security, or side effects.

## Per-route workflow

### 1. Capture the existing contract

Record methods, URI variants, constraints, validation, authentication,
authorization, CSRF, statuses, headers, cookies, redirects, database behavior,
mail/queue/event effects, external calls, and failure/retry behavior. Add
characterization tests for behavior that must remain stable.

### 2. Build the native Laravel endpoint

Use ordinary Laravel routes, middleware, form requests, controllers, services,
models, policies, jobs, events, and views/resources. Do not build new endpoints
on the legacy compatibility classes.

Add feature tests for success, validation, authorization, unsupported methods,
and meaningful failures.

### 3. Resolve shared state explicitly

Both frameworks see incoming cookies, but their session formats and CSRF tokens
are independent. Choose and test a deliberate authentication strategy such as
re-authentication, a short-lived signed one-time token, or a shared identity
service. Do not deserialize arbitrary legacy session payloads.

For a shared database, verify connection charset, table and timestamp
conventions, soft deletes, model events, and transaction boundaries. A
transaction in one request/framework does not extend into another.

### 4. Move ownership

Add the native route to a Laravel-only configured route file. Remove or disable
the equivalent CI route. Never execute one route file in both lifecycles.

### 5. Rebuild artifacts

Local, uncached iteration:

```bash
php artisan route:clear
php artisan route:list
php artisan ci-bridge:cache-routes
```

Production:

```bash
php artisan config:cache
php artisan route:cache
php artisan ci-bridge:cache-routes
```

### 6. Verify

Exercise every supported method, one unsupported method, valid/invalid
constraints, intended host/scheme, relevant encoded paths, middleware and
authorization, a representative CI route, and a similar URI that must remain
with CI.

### 7. Observe

Watch bridge `503`, `404`/`405`, authentication redirects, CSRF errors,
wrong-framework log entries, queue/mail failures, and latency.

## Design constraints

### Same URI, different methods

With `claim_method_mismatches=true`, any method for a matching Laravel URI
goes to Laravel, so CI cannot keep `POST /orders` after Laravel owns
`GET /orders`. Setting it false permits split ownership, but forgotten methods
can then execute CI behavior. Test every verb.

### Fallback and broad routes

A Laravel fallback is rejected during coexistence. Broad ordinary routes such as
`/{path}` may also capture much of CI's URI space; constrain and test them.

### Domains, schemes, and route cache

Domain/scheme ownership depends on pre-framework `host`/`scheme` resolution,
including trusted proxy configuration. Build Laravel's route cache before the
ownership manifest so both describe the same collection.

## Completion checklist

- [ ] Existing contract is characterized.
- [ ] Native implementation and feature tests pass.
- [ ] Authentication, CSRF, and shared data are explicit.
- [ ] CI no longer claims the endpoint.
- [ ] Config/route caches and ownership manifest are current.
- [ ] Supported and unsupported methods are verified.
- [ ] Representative CI fallthrough still works.
- [ ] Monitoring covers both applications.

## Retiring the bridge

After the last CI route moves, point the web server to Laravel's public
entrypoint, remove the prelude, add a Laravel fallback only if needed, retire CI
after rollback needs expire, and upgrade PHP/Laravel rather than retaining the
pinned runtime.
