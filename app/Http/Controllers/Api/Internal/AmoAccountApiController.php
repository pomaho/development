<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Jobs\SyncAmoUsersAndRolesJob;
use App\Models\AmoAccount;
use App\Services\Amo\AmoFallbackHttpClient;
use Illuminate\Http\JsonResponse;

class AmoAccountApiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(AmoAccount::query()->latest()->get());
    }

    public function users(AmoAccount $amoAccount): JsonResponse
    {
        return response()->json($amoAccount->usersSnapshots()->latest('synced_at')->paginate(100));
    }

    public function roles(AmoAccount $amoAccount): JsonResponse
    {
        return response()->json($amoAccount->rolesSnapshots()->latest('synced_at')->paginate(100));
    }

    public function syncUsers(AmoAccount $amoAccount): JsonResponse
    {
        $this->authorize('sync', $amoAccount);
        SyncAmoUsersAndRolesJob::dispatch($amoAccount->id);

        return response()->json(['status' => 'queued']);
    }

    public function testConnection(AmoAccount $amoAccount, AmoFallbackHttpClient $http): JsonResponse
    {
        $this->authorize('sync', $amoAccount);

        return response()->json($http->get($amoAccount, '/api/v4/account'));
    }
}
