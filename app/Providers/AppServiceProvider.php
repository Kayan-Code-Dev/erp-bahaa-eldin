<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Configure Carbon to use MySQL format for JSON serialization
        Carbon::serializeUsing(function ($date) {
            return $date->format('Y-m-d H:i:s');
        });

        // Map enum values to class names for polymorphic relationships
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'branch' => \App\Models\Branch::class,
            'workshop' => \App\Models\Workshop::class,
            'factory' => \App\Models\Factory::class,
            // Also support full class names for backward compatibility
            'App\\Models\\Branch' => \App\Models\Branch::class,
            'App\\Models\\Workshop' => \App\Models\Workshop::class,
            'App\\Models\\Factory' => \App\Models\Factory::class,
        ]);

        // Register API error middleware alias so routes can use ->middleware('api.error')
        if ($this->app->bound('router')) {
            $this->app['router']->aliasMiddleware('api.error', \App\Http\Middleware\ApiExceptionMiddleware::class);
            $this->app['router']->aliasMiddleware('force.json', \App\Http\Middleware\ForceJson::class);
        }
    }
}
