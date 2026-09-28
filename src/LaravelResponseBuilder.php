<?php
namespace AnwarGazi\CiLaravelSupport;

/**
 * LaravelResponseBuilder
 *
 * Fluent response builder. Methods output and exit immediately —
 * CI3 has no return-based response pipeline, so all output is sent inline.
 */
class LaravelResponseBuilder
{
    /** @var int HTTP status code */
    private $statusCode = 200;

    /** @var string|null Target redirect URL */
    private $redirectUrl = null;

    /** @var int HTTP redirect status code */
    private $redirectStatus = 302;

    /** @var bool Whether this is a redirect response */
    private $isRedirect = false;

    /** @var bool Whether response has been sent */
    private $sent = false;

    /** @var string|null Raw response content */
    private $rawContent = null;

    /** @var array Custom response headers */
    private $headers = [];

    /**
     * Set the HTTP status code (fluent).
     */
    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Set the raw content for the response.
     *
     * @param  string $content
     * @param  int    $status
     * @return self
     */
    public function setContent(string $content, int $status = 200): self
    {
        $this->rawContent = $content;
        $this->statusCode = $status;
        return $this;
    }

    /**
     * Set a custom response header (fluent).
     *
     * @param  string $key
     * @param  string $value
     * @return self
     */
    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Emit default, fluent and method-specific headers in precedence order.
     */
    private function sendHeaders(array $headers = [], array $defaults = []): void
    {
        foreach (array_merge($defaults, $this->headers, $headers) as $key => $value) {
            header("{$key}: {$value}");
        }
    }

    /**
     * Send a JSON response with Content-Type header, then exit.
     * @param  mixed $data
     * @param  int|null $status
     * @param  int   $options
     * @return void
     */
    public function json($data, ?int $status = null, int $options = 0): void
    {
        $this->sent = true;
        http_response_code($status !== null ? $status : $this->statusCode);
        $this->sendHeaders([], ['Content-Type' => 'application/json; charset=utf-8']);
        echo json_encode($data, $options);
        exit;
    }

    /**
     * Send a plain HTML/text response, then exit.
     *
     * @param  string $content
     * @param  int|null $status
     * @param  array  $headers
     * @return void
     */
    public function make(string $content, ?int $status = null, array $headers = []): void
    {
        $this->sent = true;
        http_response_code($status !== null ? $status : $this->statusCode);
        $this->sendHeaders($headers);
        echo $content;
        exit;
    }

    /**
     * Set redirect target.
     *
     * @param  string $url     Absolute URL or CI site path
     * @param  int    $status  HTTP redirect code (301 or 302)
     * @return self
     */
    public function redirect(string $url, int $status = 302): self
    {
        $this->isRedirect = true;
        $this->redirectUrl = $url;
        $this->redirectStatus = $status;
        return $this;
    }

    /**
     * Redirect to a named route, resolving parameters.
     *
     * @param  string $name
     * @param  array  $parameters
     * @param  int    $status
     * @return self
     */
    public function route(string $name, array $parameters = [], int $status = 302): self
    {
        $url = Route::resolve($name, $parameters);
        return $this->redirect($url, $status);
    }

    /**
     * Redirect back to the HTTP referrer (or a fallback path).
     *
     * @param  string $fallback  Site path to use when no referrer header is present
     * @return self
     */
    public function back(string $fallback = '/'): self
    {
        $url = $_SERVER['HTTP_REFERER'] ?? site_url($fallback);
        return $this->redirect($url, 302);
    }

    /**
     * Add a flash data variable to the session (fluent).
     *
     * @param  string $key
     * @param  mixed  $value
     * @return self
     */
    public function with(string $key, $value): self
    {
        $CI =& get_instance();
        if (isset($CI->session)) {
            $CI->session->set_flashdata($key, $value);
        }
        return $this;
    }

    /**
     * Send a streamed response, then exit.
     *
     * @param  callable $callback
     * @param  int|null $status
     * @param  array    $headers
     * @return void
     */
    public function stream(callable $callback, ?int $status = null, array $headers = []): void
    {
        $this->sent = true;
        http_response_code($status !== null ? $status : $this->statusCode);
        $this->sendHeaders($headers);
        // Disable output buffering
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        $callback();
        exit;
    }

    /**
     * Send the response if it hasn't been sent yet.
     *
     * @return void
     */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }
        $this->sent = true;

        if ($this->isRedirect && $this->redirectUrl !== null) {
            $url = $this->redirectUrl;
            if (!preg_match('/^https?:\/\//i', $url)) {
                $url = site_url($url);
            }
            http_response_code($this->redirectStatus);
            $this->sendHeaders();
            header("Location: {$url}");
            exit;
        }

        if ($this->rawContent !== null) {
            http_response_code($this->statusCode);
            $this->sendHeaders();
            echo $this->rawContent;
            exit;
        }
    }

    /**
     * Send a file download response, then exit.
     *
     * @param  string      $filePath     Absolute path to file
     * @param  string|null $fileName     Download filename shown to user
     * @param  array       $headers      Custom headers
     * @param  string      $disposition  Content disposition attachment/inline
     * @return self
     */
    public function download(string $filePath, string $fileName = null, array $headers = [], string $disposition = 'attachment'): self
    {
        $this->sent = true;
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }
        $fileName = $fileName ?: basename($filePath);

        $safeFileName = addcslashes(str_replace(["\r", "\n"], '', $fileName), '\\"');
        $this->sendHeaders($headers, [
            'Content-Description' => 'File Transfer',
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => $disposition . '; filename="' . $safeFileName . '"',
            'Content-Length' => (string) filesize($filePath),
            'Pragma' => 'public',
        ]);

        if (ob_get_level() > 0) {
            ob_clean();
        }
        flush();
        readfile($filePath);
        exit;
    }

    public function __destruct()
    {
        $this->send();
    }
}
