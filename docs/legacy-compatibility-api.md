# Legacy compatibility API

Root-level `src/` classes predate the native v2 dispatcher. They provide
Laravel-like conveniences inside a CI3 lifecycle. They remain for existing
integrations and regression tests, but do not run when the bridge sends an owned
request through Laravel's native kernel. New migrated endpoints should use
native Laravel features.

## Loading

Classes use the package PSR-4 mapping. In a shared CI3 Composer tree, the
package automatically binds compatibility URL and redirect services. This
keeps Laravel's globally loaded `asset()` and `redirect()` helpers compatible
with existing CI calls. Existing services are never replaced, so a native
Laravel application remains authoritative.

The package does not autoload `src/helpers.php`. Hosts needing its additional
legacy globals or aliases must explicitly load it after their CI Composer
autoloader:

```php
require_once '/path/to/vendor/anwargazi/ci-laravel-support/src/helpers.php';
```

Review collisions first. `view()`, `response()`, `request()`, `route()`,
`app()`, and `cache()` are defined only when absent, so load order changes
behavior. Do not load this compatibility file in native Laravel requests without
explicit integration testing.

## Routing shim

`Route` supports `get`, `post`, `put`, `patch`, `delete`,
`options`, and `any`. `any` expands to GET, HEAD, POST, PUT, PATCH, DELETE,
and OPTIONS.

```php
Route::get('items/{id:num}', 'Items/show')->name('items.show');
Route::post('items/{id:num}', 'Items/update');
```

Placeholders are `{name}`/`{name:any}`, `{name:num}`, and optional
trailing-`?` variants. `Route::compile()` returns CI nested method maps and
adds CI backreferences when the action has none. `Route::resolve()` fills
named placeholders, appends extra values as a query string, and uses
`site_url()`. Missing parameters throw `InvalidArgumentException`; unknown
names throw `Exception`.

`LaravelController::_remap()` binds named parameters to public action method
arguments, resolves class-typed arguments from the Illuminate container, and
injects the compatibility `Request`. Missing, non-public, or underscore
methods call CI `show_404()`.

## Request and controller returns

`Request::input()` merges GET then POST, so POST wins. `has()` tests key
presence (including null values), `query()` reads GET only, `segment()` reads
CI URI segments, and `all()` returns merged input. It is not
`Illuminate\Http\Request`.

The base controller handles:

| Returned value | Result |
| --- | --- |
| `View` | Render and echo. |
| `LaravelResponseBuilder` | Send. |
| string | Echo. |
| array | JSON. |
| `JsonSerializable` | JSON from `jsonSerialize()`. |
| object with `resolve()` or `toArray()` | JSON. |
| stringable object | Echo cast value. |

## Blade and views

`BladeEngine` searches `application/views`, optional `application/View`
and `application/View/Components`, and
`application/modules/<module>/views`. Compiled files go to
`application/cache/blade`, which must be writable.

Dot notation maps to directories; a first segment matching a module becomes a
view namespace. Resolution also tries PascalCase for the first directory. The
compatibility compiler adds `@once`/`@endonce` and basic anonymous/class
`<x-...>` components with literal, bound, and boolean attributes. It is not a
complete modern Blade component implementation.

`View::layout()` prefers a Blade layout, otherwise passes `blade_body` to a
CI layout. String conversion catches failures, logs them through CI, and returns
an empty string.

`ComponentAttributeBag` escapes rendered names/values, renders true as a
valueless attribute, omits false/null, and appends caller classes to defaults.

## Eloquent, cache, and environment

`EloquentEngine::boot()` creates a global MySQL Capsule from `DB_HOSTNAME`,
`DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`, using `utf8` and
`utf8_general_ci`. It enables events and Eloquent. Prefer native Laravel
database configuration for migrated endpoints.

`CacheEngine::boot($path)` configures an Illuminate file cache manager in the
global container. It supports array config or an object exposing `set()`.
Static calls forward to the store; the default path is
`FCPATH . 'application/cache'`.

`Env::load($file)` parses simple `KEY=VALUE` lines, ignores comments and
invalid names, strips paired quotes, and populates process/`$_ENV`/`$_SERVER`
only when no value exists. Missing files are ignored; unreadable existing files
throw. It does not implement interpolation, export syntax, inline comments, or
multiline values.

## Response builder

| Method | Behavior |
| --- | --- |
| `status()`, `header()` | Configure status/headers fluently. |
| `setContent()` | Store raw content for `send()` or destructor send. |
| `json()`, `make()` | Send immediately and exit. |
| `redirect()`, `route()`, `back()` | Store a redirect for `send()`. |
| `with()` | Set CI flashdata if session is loaded. |
| `stream()` | Clear one buffer level, invoke callback, exit. |
| `download()` | Send file, stripping CR/LF from filename, then exit. |
| `send()` | Emit a stored redirect/content at most once. |

Header precedence is defaults, fluent headers, then method-specific headers.
Later entries win. This is inline CI output, not Laravel's response pipeline.

## Helpers and aliases

Explicitly loading `helpers.php` may define `laravel_view`, `view`,
`laravel_response`, `response`, `laravel_abort`, `laravel_url`,
`laravel_asset`, `laravel_config`, `route`, `request`, `app`, and
`cache`. It also contains historical application-specific image/restaurant
helpers that new generic integrations should avoid.

Aliases are provided for `ComponentAttributeBag`, `Route`, `RouteInstance`,
`EloquentEngine`, `Cache`, and lazily `Laravel_Controller`.

The redirect helper is namespaced to avoid CI's global function:

```php
use function AnwarGazi\CiLaravelSupport\Helpers\redirect;

return redirect()->route('items.show', ['id' => 42]);
```
