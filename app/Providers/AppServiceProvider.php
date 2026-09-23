<?php

namespace App\Providers;

use App\Mail\Transport\BrevoApiTransport;
use App\Models\Order;
use App\Models\ProductReview;
use App\Observers\OrderObserver;
use App\Observers\ProductReviewObserver;
use App\Services\Storefront\StorefrontTheme;
use App\Services\Storefront\TemplateRegistry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TemplateRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Order::observe(OrderObserver::class);
        ProductReview::observe(ProductReviewObserver::class);

        Mail::extend('brevo', function () {
            return new BrevoApiTransport(config('services.brevo.api_key'));
        });

        // Customizable storefront templates get their resolved theme as $storefrontTheme.
        // Registered per template (never a templates.* wildcard) so bespoke builds such as
        // Maetek's minimal-light are not even visited by this composer.
        foreach (array_keys(config('storefront.templates', [])) as $templateKey) {
            if (!app(TemplateRegistry::class)->isCustomizable($templateKey)) {
                continue;
            }
            View::composer("templates.{$templateKey}.*", function ($view) use ($templateKey) {
                $data = $view->getData();
                if (!isset($data['storefrontTheme']) && isset($data['store'])) {
                    $view->with('storefrontTheme', StorefrontTheme::for($data['store'], $templateKey));
                }
            });
        }
    }
}
