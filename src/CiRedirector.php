<?php
namespace AnwarGazi\CiLaravelSupport;

/**
 * Redirect service used by Laravel's global helper in a CI3 request.
 *
 * Supports both Laravel's redirect($to, $status, $headers, $secure)
 * and CI3's redirect($uri, $method, $code) calling conventions.
 */
class CiRedirector
{
    /** @var CiUrlGenerator */
    private $url;

    public function __construct(CiUrlGenerator $url)
    {
        $this->url = $url;
    }

    /**
     * @param mixed $status
     * @param mixed $headers
     * @param mixed $secure
     */
    public function to($path, $status = 302, $headers = [], $secure = null): void
    {
        $parameters = $this->parameters($status, $headers);
        $url = $this->url->to($path, [], $secure);

        if ($parameters['method'] === 'refresh') {
            header('Refresh:0;url=' . $url);
        } else {
            header('Location: ' . $url, true, $parameters['code']);
        }

        foreach ($parameters['headers'] as $name => $value) {
            header($name . ': ' . $value, true);
        }

        exit;
    }

    /**
     * Normalize Laravel and legacy CI redirect arguments.
     *
     * @param mixed $status
     * @param mixed $headers
     * @return array{method:string,code:int,headers:array}
     */
    public function parameters($status = 302, $headers = []): array
    {
        if (is_string($status)) {
            $method = strtolower($status);
            if (!in_array($method, ['auto', 'location', 'refresh'], true)) {
                $method = 'auto';
            }

            $code = is_int($headers) ? $headers : 302;
            $headers = is_array($headers) ? $headers : [];

            if ($method === 'auto') {
                $method = isset($_SERVER['SERVER_PROTOCOL']) ? 'location' : 'refresh';
            }

            return ['method' => $method, 'code' => $code, 'headers' => $headers];
        }

        return [
            'method' => 'location',
            'code' => is_int($status) ? $status : 302,
            'headers' => is_array($headers) ? $headers : [],
        ];
    }
}
