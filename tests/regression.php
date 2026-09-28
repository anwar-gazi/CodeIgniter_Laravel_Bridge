<?php

declare(strict_types=1);

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual:   ' . var_export($actual, true)
        );
    }
}

require_once __DIR__ . '/../src/Route.php';
require_once __DIR__ . '/../src/Request.php';
require_once __DIR__ . '/../src/BladeEngine.php';
require_once __DIR__ . '/../src/Env.php';

if (!class_exists('CI_Controller', false)) {
    class CI_Controller
    {
    }
}

require_once __DIR__ . '/../src/LaravelController.php';

use AnwarGazi\CiLaravelSupport\BladeEngine;
use AnwarGazi\CiLaravelSupport\Env;
use AnwarGazi\CiLaravelSupport\LaravelController;
use AnwarGazi\CiLaravelSupport\Request;
use AnwarGazi\CiLaravelSupport\Route;

Route::reset();
Route::get('items/{id:num}', 'Items/show');
Route::post('items/{id:num}', 'Items/update');
$compiled = Route::compile();

assertSameValue('Items/show/$1', $compiled['items/(:num)']['get'] ?? null, 'GET route was not preserved.');
assertSameValue('Items/update/$1', $compiled['items/(:num)']['post'] ?? null, 'POST route was not preserved.');

Route::reset();
Route::any('health', 'Health/check');
assertSameValue(
    ['get', 'head', 'post', 'put', 'patch', 'delete', 'options'],
    array_keys(Route::compile()['health']),
    'Route::any() does not cover all standard HTTP verbs.'
);

class ControllerMethodProbe extends LaravelController
{
    public function extractMethod(string $action, string $fallback): string
    {
        return $this->methodFromRouteAction($action, $fallback);
    }
}

$controllerReflection = new ReflectionClass(ControllerMethodProbe::class);
$controller = $controllerReflection->newInstanceWithoutConstructor();
assertSameValue('update', $controller->extractMethod('admin/Items/update', 'index'), 'Matched route action was not selected.');

$GLOBALS['CI_REQUEST_STUB'] = new class {
    public $input;

    public function __construct()
    {
        $this->input = new class {
            public function get($key = null)
            {
                return $key === null ? ['present_null' => null] : null;
            }

            public function post($key = null)
            {
                return $key === null ? ['posted' => 'value'] : null;
            }
        };
    }
};

function &get_instance()
{
    return $GLOBALS['CI_REQUEST_STUB'];
}

$request = new Request();
assertSameValue('fallback', $request->input('missing', 'fallback'), 'Missing input did not use its default.');
assertSameValue(true, $request->has('present_null'), 'A present null-valued input was reported missing.');
assertSameValue(false, $request->has('missing'), 'A missing input was reported present.');
assertSameValue('value', $request->input('posted'), 'POST input did not take precedence.');

$template = '<x-alert title="Bob' . chr(39) . 's order">Hi</x-alert>';
$compiledTemplate = BladeEngine::compileTags($template);
$compiledFile = tempnam(sys_get_temp_dir(), 'ci-laravel-blade-');
file_put_contents($compiledFile, $compiledTemplate);
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($compiledFile) . ' 2>&1', $lintOutput, $lintCode);
unlink($compiledFile);
assertSameValue(0, $lintCode, 'A quoted Blade attribute generated invalid PHP: ' . implode(PHP_EOL, $lintOutput));

$environmentFile = tempnam(sys_get_temp_dir(), 'ci-laravel-env-');
$externalKey = 'CI_LARAVEL_EXTERNAL_' . strtoupper(bin2hex(random_bytes(4)));
$localKey = 'CI_LARAVEL_LOCAL_' . strtoupper(bin2hex(random_bytes(4)));
putenv("{$externalKey}=host");
$_ENV[$externalKey] = 'host';
file_put_contents($environmentFile, "{$externalKey}=file\n{$localKey}=loaded\nINVALID-NAME=ignored\n");
Env::load($environmentFile);
unlink($environmentFile);

assertSameValue('host', getenv($externalKey), 'The .env file overwrote a host-provided value.');
assertSameValue('loaded', getenv($localKey), 'A valid local environment value was not loaded.');
assertSameValue(false, getenv('INVALID-NAME'), 'An invalid environment key was loaded.');

putenv($externalKey);
putenv($localKey);
unset($_ENV[$externalKey], $_SERVER[$externalKey], $_ENV[$localKey], $_SERVER[$localKey]);

$responseFile = realpath(__DIR__ . '/../src/LaravelResponseBuilder.php');
$responseProbe = 'require ' . var_export($responseFile, true) . ';'
    . 'register_shutdown_function(function(){fwrite(STDERR,"status=".http_response_code());});'
    . '(new \\AnwarGazi\\CiLaravelSupport\\LaravelResponseBuilder())->status(422)->json(["error"=>true]);';
exec(
    escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($responseProbe) . ' 2>&1',
    $responseOutput,
    $responseCode
);
assertSameValue(0, $responseCode, 'The JSON response probe failed.');
assertSameValue(true, strpos(implode(PHP_EOL, $responseOutput), 'status=422') !== false, 'Fluent response status was ignored.');

require_once __DIR__ . '/../src/EloquentEngine.php';
require_once __DIR__ . '/../src/CacheEngine.php';
require_once __DIR__ . '/../src/ComponentAttributeBag.php';
require_once __DIR__ . '/../src/LaravelResponseBuilder.php';
require_once __DIR__ . '/../src/View.php';
require_once __DIR__ . '/../src/helpers.php';
assertSameValue(true, class_exists('Laravel_Controller'), 'The documented Laravel_Controller alias is unavailable.');

echo "All regression checks passed.\n";
