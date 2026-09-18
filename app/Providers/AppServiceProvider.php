<?php

namespace App\Providers;

use App\Mail\Transport\BrevoApiTransport;
use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

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
        Order::observe(OrderObserver::class);

        Mail::extend('brevo', function () {
            return new BrevoApiTransport(config('services.brevo.api_key'));
        });
    }
}
