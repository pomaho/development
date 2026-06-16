<?php

namespace App\Http\Middleware;

use App\Models\AmoAccount;
use App\Support\InertiaAccountsCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $routeAccount = $request->route('amo_account');
        $currentAccount = $routeAccount instanceof AmoAccount ? $routeAccount : null;

        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                ] : null,
            ],
            'amoAccounts' => fn () => $request->user()
                ? Cache::remember(
                    InertiaAccountsCache::keyFor($request->user()->id),
                    300,
                    fn () => AmoAccount::query()
                        ->orderBy('name')
                        ->get(['id', 'name', 'base_domain', 'is_active'])
                        ->map(fn (AmoAccount $account): array => [
                            'id' => $account->id,
                            'name' => $account->name,
                            'base_domain' => $account->base_domain,
                            'is_active' => $account->is_active,
                            'dashboard_url' => route('amo-accounts.dashboard', $account),
                        ])
                        ->all()
                )
                : [],
            'currentAmoAccount' => $currentAccount ? [
                'id' => $currentAccount->id,
                'name' => $currentAccount->name,
                'base_domain' => $currentAccount->base_domain,
                'is_active' => $currentAccount->is_active,
                'dashboard_url' => route('amo-accounts.dashboard', $currentAccount),
            ] : null,
        ];
    }
}
