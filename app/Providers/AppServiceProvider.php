<?php

namespace App\Providers;

use App\Events\AmoAccountSynced;
use App\Events\AmoAccountSyncQueued;
use App\Listeners\InvalidateInertiaAccountsCacheOnSync;
use App\Models\AmoAccount;
use App\Policies\AmoAccountPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(AmoAccount::class, AmoAccountPolicy::class);
        $this->configureRateLimiting();
        $this->configureEvents();
    }

    private function configureEvents(): void
    {
        $listener = InvalidateInertiaAccountsCacheOnSync::class;

        Event::listen(AmoAccountSynced::class, [$listener, 'handleSynced']);
        Event::listen(AmoAccountSyncQueued::class, [$listener, 'handleSyncQueued']);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(60)->by($request->route('webhookKey') ?: $request->ip());
        });

        RateLimiter::for('amo-sync', function (Request $request) {
            return Limit::perMinute(5)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('amo-test-connection', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('amo-write', function (Request $request) {
            return Limit::perMinute(20)->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }
}
