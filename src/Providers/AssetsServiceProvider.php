<?php

namespace Botble\Assets\Providers;

use Botble\Assets\Assets;
use Botble\Assets\HtmlBuilder;
use Illuminate\Support\ServiceProvider;

/**
 * @since 22/07/2015 11:23 PM
 */
class AssetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merged here rather than in boot() so the config is available to anything
        // resolving Assets during the container registration phase.
        $this->mergeConfigFrom(__DIR__ . '/../../config/assets.php', 'assets');

        // Without these bindings every app(Assets::class) / constructor injection built a
        // fresh instance, re-reading the whole config and losing all queued assets. The
        // facade hid this behind its own static cache.
        $this->app->singleton(HtmlBuilder::class);
        $this->app->singleton(Assets::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'assets');

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../../config/assets.php' => config_path('assets.php')], 'config');
            $this->publishes([__DIR__ . '/../../resources/views' => resource_path('views/vendor/assets')], 'views');
        }
    }
}
