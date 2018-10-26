<?php

namespace Botble\Assets\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Class AssetsServiceProvider.
 *
 * @since 22/07/2015 11:23 PM
 */
class AssetsServiceProvider extends ServiceProvider
{
    /**
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    public function boot()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/assets.php', 'assets');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'assets');

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../../config/assets.php' => config_path('assets.php')], 'config');
            $this->publishes([__DIR__ . '/../../resources/views' => resource_path('views/vendor/assets')], 'views');
        }
    }
}
