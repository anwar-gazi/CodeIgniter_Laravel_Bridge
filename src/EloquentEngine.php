<?php
namespace AnwarGazi\CiLaravelSupport;

/**
 * EloquentEngine
 *
 * A singleton wrapper around illuminate/database (Capsule) that integrates
 * Laravel's Eloquent Query Builder into a CodeIgniter 3 application.
 *
 * Connection credentials are read directly from environment variables via
 * the global env() helper (already loaded by index.php before this fires).
 *
 * Usage in repositories:
 *   use Illuminate\Database\Capsule\Manager as DB;
 *   DB::table('restaurant')->where('active', 1)->get();
 *
 * Eloquent models (App\Models\*) can be added on demand and will work
 * automatically once this class has been booted.
 */
class EloquentEngine
{
    /** @var \Illuminate\Database\Capsule\Manager|null */
    private static $capsule = null;

    /**
     * Bootstrap Capsule (idempotent — safe to call multiple times).
     */
    public static function boot(): void
    {
        if (static::$capsule !== null) {
            return;
        }

        $capsule = new \Illuminate\Database\Capsule\Manager();

        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => env('DB_HOSTNAME', 'localhost'),
            'database'  => env('DB_DATABASE', ''),
            'username'  => env('DB_USERNAME', ''),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8',
            'collation' => 'utf8_general_ci',
            'prefix'    => '',
        ]);

        // Register the Dispatcher so Eloquent model events work when models are added.
        $capsule->setEventDispatcher(new \Illuminate\Events\Dispatcher());

        // Make this Capsule instance globally accessible as DB::table() etc.
        $capsule->setAsGlobal();

        // Boot Eloquent ORM (enables model static methods, relationships, etc.)
        $capsule->bootEloquent();

        static::$capsule = $capsule;
    }

    /**
     * Expose the underlying Capsule instance (for advanced use).
     */
    public static function capsule(): ?\Illuminate\Database\Capsule\Manager
    {
        return static::$capsule;
    }
}
