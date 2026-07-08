<?php

namespace CasSystem\LaravelClient;

use Illuminate\Support\ServiceProvider;
use CasSystem\LaravelClient\Services\CasAuthService;

use Illuminate\Support\Facades\Auth;

class CasClientServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cas-client.php', 'cas-client'
        );

        $this->app->singleton(CasAuthService::class, function ($app) {
            return new CasAuthService($app['config']['cas-client']);
        });

        $this->app->bind('cas-client', function ($app) {
            return $app->make(CasAuthService::class);
        });
        
        // Register SignatureClient service
        $this->app->singleton(\CasSystem\LaravelClient\Services\SignatureClient::class, function ($app) {
            return new \CasSystem\LaravelClient\Services\SignatureClient();
        });
        
        $this->app->bind('cas-signature-client', function ($app) {
            return $app->make(\CasSystem\LaravelClient\Services\SignatureClient::class);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration file
        $this->publishes([
            __DIR__.'/../config/cas-client.php' => config_path('cas-client.php'),
        ], 'cas-client-config');

        // Publish middleware
        $this->publishes([
            __DIR__.'/Middleware/' => app_path('Http/Middleware/CasClient/'),
        ], 'cas-client-middleware');

        // Publish routes
        $this->publishes([
            __DIR__.'/../routes/cas-client.php' => base_path('routes/cas-client.php'),
        ], 'cas-client-routes');

        // Load Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');



        // Load routes
        if (config('cas-client.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/cas-client.php');
        }

        // Register Middleware Aliases
        $router = $this->app['router'];
        $router->aliasMiddleware('cas.auth', \CasSystem\LaravelClient\Middleware\CasAuthentication::class);
        $router->aliasMiddleware('cas.role', \CasSystem\LaravelClient\Middleware\CasRole::class);

        // Register Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \CasSystem\LaravelClient\Commands\InstallCommand::class,
            ]);
        }
    }
}