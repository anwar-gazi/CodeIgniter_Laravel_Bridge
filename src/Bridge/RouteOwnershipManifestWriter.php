<?php

namespace AnwarGazi\CiLaravelSupport\Bridge;

class RouteOwnershipManifestWriter
{
    public function write(string $path, array $manifest): void
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create route manifest directory [{$directory}].");
        }

        if (!is_writable($directory)) {
            throw new \RuntimeException("Route manifest directory is not writable [{$directory}].");
        }

        $temporaryPath = tempnam($directory, '.ci-laravel-routes-');
        if ($temporaryPath === false) {
            throw new \RuntimeException("Unable to create a temporary route manifest in [{$directory}].");
        }

        $contents = "<?php\n\nreturn " . var_export($manifest, true) . ";\n";

        try {
            if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
                throw new \RuntimeException("Unable to write temporary route manifest [{$temporaryPath}].");
            }

            chmod($temporaryPath, 0644);

            if (!rename($temporaryPath, $path)) {
                throw new \RuntimeException("Unable to publish route manifest [{$path}].");
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
