<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Meilisearch\Client;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('meilisearch', function ($app) {
            return new Client(
                config('scout.meilisearch.host'),
                config('scout.meilisearch.key')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        // Modify rate limiting for APIs to 60 requests per minute
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('attachment', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('restricted', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('high-restricted', function (Request $request) {
            return Limit::perMinutes(5, 5)->by($request->ip());
        });

        RateLimiter::for('very-restricted', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Rate limit auth endpoints to 10 requests per minute by user IP
        RateLimiter::for('extreme', function (Request $request) {
            return Limit::perDay(2)->by($request->ip());
        });

        RateLimiter::for('minute-extreme', function (Request $request) {
            return Limit::perHour(20)->by($request->ip());
        });

        // Rate limit auth endpoints to 10 requests per minute by user IP
        RateLimiter::for('very-high-risk', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Rate limit products endpoints to 30 requests per minute by user ID
        RateLimiter::for('high-risk', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id);
        });

        RateLimiter::for('medium-risk', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id);
        });

        RateLimiter::for('low-risk', function (Request $request) {
            return Limit::perMinute(40)->by($request->user()?->id);
        });


        RateLimiter::for('very-low-risk', function (Request $request) {
            return Limit::perMinute(50)->by($request->user()?->id);
        });
    }
}
