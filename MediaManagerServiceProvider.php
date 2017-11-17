<?php

namespace Webelightdev\LaravelMediaManager;

use Illuminate\Support\ServiceProvider;

class MediaManagerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.    
     *
     * @return void
     */
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__.'/src/resources/lang/en/mediaManager/message', 'MediaManager');

        $this->publishes([__DIR__.'/src/resources/leng/en' => resource_path('lang/en/mediaManager/messages')]);
        // Config
        $this->publishes([__DIR__.'/config/mediaManager.php' => config_path('mediaManager.php')]);
        // Migration
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
       include __DIR__.'/routes.php';
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
       $this->app->bind('laravel-mediaManager', function () {
           return new MediaManagerClass();
       });

       $this->app->make('Webelightdev\LaravelMediaManager\src\Controllers\MediaController');
       $this->loadViewsFrom(__DIR__.'/src/resources/views/', 'MediaManager');
    }
}
