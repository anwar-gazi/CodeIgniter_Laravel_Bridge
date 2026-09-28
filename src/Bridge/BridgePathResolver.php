<?php

namespace AnwarGazi\CiLaravelSupport\Bridge;

class BridgePathResolver
{
    /** @var string */
    private $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    public function resolve(string $path): string
    {
        if ($this->isAbsolute($path)) {
            return $path;
        }

        return $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    private function isAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}
