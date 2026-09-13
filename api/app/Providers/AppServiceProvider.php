<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            \Fleetbase\FleetOps\Http\Controllers\Api\v1\OrderController::class,
            \App\Http\Controllers\OverriddenOrderController::class
        );
        $this->app->bind(
            \Fleetbase\FleetOps\Http\Controllers\Internal\v1\LiveController::class,
            \App\Http\Controllers\OverriddenLiveController::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
