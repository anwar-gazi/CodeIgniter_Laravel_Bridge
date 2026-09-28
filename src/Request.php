<?php
namespace AnwarGazi\CiLaravelSupport;

/**
 * Request Class
 *
 * Laravel-style HTTP request wrapper for CodeIgniter 3.
 */
class Request
{
    /**
     * Get a request parameter value from input (GET/POST).
     *
     * @param  string|null $key
     * @param  mixed       $default
     * @return mixed
     */
    public function input(string $key = null, $default = null)
    {
        $CI =& get_instance();
        $input = array_merge($CI->input->get() ?: [], $CI->input->post() ?: []);

        if ($key === null) {
            return $input;
        }

        return array_key_exists($key, $input) ? $input[$key] : $default;
    }

    /**
     * Check if request has key.
     *
     * @param  string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->input());
    }

    /**
     * Get a query parameter value.
     *
     * @param  string|null $key
     * @param  mixed       $default
     * @return mixed
     */
    public function query(string $key = null, $default = null)
    {
        $CI =& get_instance();
        $query = $CI->input->get() ?: [];

        if ($key === null) {
            return $query;
        }

        return array_key_exists($key, $query) ? $query[$key] : $default;
    }

    /**
     * Get segment value.
     *
     * @param  int   $index
     * @param  mixed $default
     * @return mixed
     */
    public function segment(int $index, $default = null)
    {
        $CI =& get_instance();
        $val = $CI->uri->segment($index);
        return $val !== null ? $val : $default;
    }

    /**
     * Get all input parameters.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->input();
    }
}
