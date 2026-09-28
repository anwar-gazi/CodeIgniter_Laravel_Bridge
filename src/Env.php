<?php
namespace AnwarGazi\CiLaravelSupport;

/**
 * Env
 *
 * Lightweight .env file loader for CodeIgniter 3.
 * Parses KEY=VALUE pairs and populates getenv(), $_ENV and $_SERVER.
 *
 * Usage (in index.php, after Composer autoloader):
 *   \AnwarGazi\CiLaravelSupport\Env::load(__DIR__ . '/.env');
 */
class Env
{
    protected static $vars = [];

    public static function load($file)
    {
        if (!file_exists($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new \RuntimeException("Unable to read environment file [{$file}].");
        }

        foreach ($lines as $line) {

            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim($value);

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                continue;
            }

            // Host/server-provided values have precedence over .env values.
            if (getenv($name) !== false || array_key_exists($name, $_ENV) || array_key_exists($name, $_SERVER)) {
                continue;
            }

            // Strip surrounding quotes: 'value' or "value"
            if (preg_match('/^([\'"])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            self::$vars[$name] = $value;
            putenv("{$name}={$value}");
            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
        }
    }

    public static function get($key, $default = '')
    {
        return env($key, $default);
    }
}
