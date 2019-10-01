<?php

namespace Waynestate\Api;

use Waynestate\Api\News;
use Illuminate\Support\ServiceProvider;

class NewsServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/waynestatenews.php', 'waynestatenews');

        $this->app->bind(News::class, function ($app) {
            return new News(config('waynestatenews'));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/waynestatenews.php' => config_path('waynestatenews.php'),
        ], 'waynestatenews');
    }
}
