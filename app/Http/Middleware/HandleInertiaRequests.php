<?php

namespace App\Http\Middleware;

use App\Models\AmoAccount;
use Illuminate\Http\Request;
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
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                ] : null,
            ],
            'amoAccounts' => fn () => AmoAccount::query()
                ->orderBy('name')
                ->get(['id', 'name', 'base_domain', 'is_active']),
            'currentAmoAccount' => $currentAccount ? [
                'id' => $currentAccount->id,
                'name' => $currentAccount->name,
                'base_domain' => $currentAccount->base_domain,
                'is_active' => $currentAccount->is_active,
            ] : null,
        ];
    }
}
