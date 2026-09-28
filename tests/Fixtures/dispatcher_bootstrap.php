<?php

$app = new \Illuminate\Container\Container();
$kernel = new class implements \Illuminate\Contracts\Http\Kernel {
    public function bootstrap()
    {
    }

    public function handle($request)
    {
        return new \Symfony\Component\HttpFoundation\Response('laravel-owned', 200);
    }

    public function terminate($request, $response)
    {
    }

    public function getApplication()
    {
        return null;
    }
};

$app->instance(\Illuminate\Contracts\Http\Kernel::class, $kernel);

return $app;
