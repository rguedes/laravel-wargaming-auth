<?php namespace Rguedes\LaravelWargamingAuth;

use Illuminate\Support\ServiceProvider;

class WargamingServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([__DIR__ . '/../config/config.php' => config_path('wargaming-auth.php')]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('wargamingauth', function () {
            return new WargamingAuth($this->app->request);
        });
    }

}
