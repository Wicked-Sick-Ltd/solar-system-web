<?php

namespace App\Providers;

use App\Services\Mailchimp\MailchimpClient;
use App\Services\SolarApi\SolarApiClient;
use App\Support\Seo;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One gateway to the backend, shared for the whole request so its
        // per-request health probe and caches aren't duplicated.
        $this->app->singleton(SolarApiClient::class);

        // Per-request page metadata, read by the layout's <head>.
        $this->app->scoped(Seo::class);

        // Newsletter signups. Built from config each request so tests can
        // toggle the credentials with config().
        $this->app->bind(MailchimpClient::class, fn () => MailchimpClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Canonical URLs, OG tags and the sitemap must use https on the public
        // domain regardless of the proxy in front of the app.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
