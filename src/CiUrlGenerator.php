<?php
namespace AnwarGazi\CiLaravelSupport;

/**
 * URL service adapter used by Laravel's global helpers in a CI3 request.
 */
class CiUrlGenerator
{
    public function asset($path, $secure = null): string
    {
        return $this->forceScheme(base_url(ltrim((string) $path, '/')), $secure);
    }

    public function to($path, $extra = [], $secure = null): string
    {
        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', (string) $path)) {
            return $this->forceScheme((string) $path, $secure);
        }

        $path = trim((string) $path, '/');
        if (!empty($extra)) {
            $path .= '/' . implode('/', array_map('rawurlencode', (array) $extra));
        }

        return $this->forceScheme(site_url($path), $secure);
    }

    public function route($name, $parameters = [], $absolute = true): string
    {
        $url = Route::resolve((string) $name, (array) $parameters);

        if ($absolute) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $query === null ? $path : $path . '?' . $query;
    }

    private function forceScheme(string $url, $secure): string
    {
        if ($secure === true) {
            return preg_replace('#^http:#i', 'https:', $url);
        }

        if ($secure === false) {
            return preg_replace('#^https:#i', 'http:', $url);
        }

        return $url;
    }
}
