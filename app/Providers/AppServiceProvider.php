<?php

namespace App\Providers;

use App\Models\AmoAccount;
use App\Policies\AmoAccountPolicy;
use Illuminate\Support\Facades\Gate;
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
    }
}
