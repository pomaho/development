<?php

namespace App\Providers;

use App\Models\AmoAccount;
use App\Policies\AmoAccountPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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

        View::composer('layouts.app', function ($view): void {
            $routeAccount = request()->route('amo_account');
            $currentAccount = $routeAccount instanceof AmoAccount ? $routeAccount : null;

            $view->with([
                'layoutAmoAccounts' => AmoAccount::query()->orderBy('name')->get(),
                'layoutCurrentAmoAccount' => $currentAccount,
            ]);
        });
    }
}
