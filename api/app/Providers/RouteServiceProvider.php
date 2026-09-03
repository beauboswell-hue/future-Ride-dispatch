<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->routes(
            function () {
                Route::get(
                    '/health',
                    function (Request $request) {
                        return response()->json(
                            [
                                'status' => 'ok',
                                'time' => microtime(true) - $request->attributes->get('request_start_time')
                            ]
                        );
                    }
                );

                Route::get(
                    'int/v1/installer/initialize',
                    function () {
                        return response()->json([
                            'shouldInstall' => false,
                            'shouldOnboard' => false,
                            'defaultTheme' => null
                        ]);
                    }
                );

                Route::get(
                    'int/v1/onboard/should-onboard',
                    function () {
                        return response()->json([
                            'should_onboard' => false
                        ]);
                    }
                );

                Route::post(
                    'int/v1/onboard/create-account',
                    function () {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Registration is disabled on this instance.'
                        ], 403);
                    }
                );

                Route::post(
                    'api/v1/webhooks/twilio/sms',
                    [\App\Http\Controllers\WebhookController::class, 'handleTwilioSms']
                );

                Route::post(
                    'api/v1/webhooks/booking',
                    [\App\Http\Controllers\WebhookController::class, 'handleWebBooking']
                );
            }
        );
    }
}
