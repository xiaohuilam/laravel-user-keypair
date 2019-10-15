<?php
namespace Xiaohuilam\LaravelUserKeypair;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Xiaohuilam\LaravelUserKeypair\Auth\ApiGuard;

class LaravelUserKeypairServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->publishes([
            dirname(__DIR__) . '/migrations/2019_09_04_031852_create_access_keys.php' => database_path('/migrations/2019_09_04_031852_create_access_keys.php'),
            dirname(__DIR__) . '/config/keypair.php' => config_path('/keypair.php'),
            dirname(__DIR__) . '/src/Http/Middleware/Partner.stub' => app_path('/Http/Middleware/Partner.php'),
            dirname(__DIR__) . '/src/Models/AccessKey.stub' => app_path('/AccessKey.php'),
        ], 'laravel-user-keypair');

        $this->mergeConfigFrom(dirname(__DIR__) . '/config/keypair.php', '');

        Auth::extend('api', function (Application $application, $name, $config) {
            $conf = config('auth.providers.' . $config['provider']);
            /**
             * @var ApiGuard $guard_class
             */
            $guard_class = '\\' . config('keypair.guard');
            $userProvider = new EloquentUserProvider($this->app['hash'], $conf['model']);
            return new $guard_class($userProvider, request());
        });
    }
}
