<?php

namespace App\Services\Amo\Structure;

use App\Models\AmoAccount;
use App\Services\Amo\Client\AmoFallbackHttpClient;
use App\Models\AmoRolesSnapshot;
use App\Models\AmoUsersSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AmoUsersService
{
    public function __construct(private readonly AmoFallbackHttpClient $http)
    {
    }

    public function fetchUsers(AmoAccount $account): array
    {
        return $this->fetchPaginated($account, '/api/v4/users', [
            'with' => 'role,group,uuid,amojo_id,user_rank,phone_number',
            'limit' => 250,
        ], 'users');
    }

    public function fetchRoles(AmoAccount $account): array
    {
        return $this->fetchPaginated($account, '/api/v4/roles', [
            'with' => 'users',
            'limit' => 250,
        ], 'roles');
    }

    public function syncUsersAndRoles(AmoAccount $account): void
    {
        $syncedAt = now();
        $users = $this->fetchUsers($account);
        $roles = $this->fetchRoles($account);

        DB::transaction(function () use ($account, $users, $roles, $syncedAt): void {
            foreach ($users as $user) {
                AmoUsersSnapshot::query()->updateOrCreate(
                    ['amo_account_id' => $account->id, 'amo_user_id' => $user['id']],
                    $this->userSnapshotPayload($user, $syncedAt)
                );
            }

            foreach ($roles as $role) {
                AmoRolesSnapshot::query()->updateOrCreate(
                    ['amo_account_id' => $account->id, 'amo_role_id' => $role['id']],
                    $this->roleSnapshotPayload($role, $syncedAt)
                );
            }

            $account->forceFill([
                'last_successful_sync_at' => $syncedAt,
                'auth_status' => 'ok',
            ])->save();
        });
    }

    private function fetchPaginated(AmoAccount $account, string $path, array $query, string $embeddedKey): array
    {
        $page = 1;
        $items = [];

        do {
            $payload = $this->http->get($account, $path, [...$query, 'page' => $page]);
            $items = array_merge($items, $payload['_embedded'][$embeddedKey] ?? []);

            $currentPage = (int) ($payload['_page'] ?? $page);
            $pageCount = (int) ($payload['_page_count'] ?? $currentPage);
            $page++;
        } while ($currentPage < $pageCount);

        return $items;
    }

    private function userSnapshotPayload(array $user, Carbon $syncedAt): array
    {
        $rights = $user['rights'] ?? [];

        return [
            'name' => (string) ($user['name'] ?? ''),
            'email' => $user['email'] ?? null,
            'lang' => $user['lang'] ?? null,
            'rights' => $rights,
            'role_id' => $user['role_id']
                ?? $user['_embedded']['role']['id']
                ?? $user['role']['id']
                ?? null,
            'group_id' => $user['group_id']
                ?? $user['_embedded']['group']['id']
                ?? $user['group']['id']
                ?? null,
            'is_admin' => (bool) ($rights['is_admin'] ?? $user['is_admin'] ?? false),
            'is_active' => (bool) ($user['is_active'] ?? true),
            'raw' => $user,
            'synced_at' => $syncedAt,
        ];
    }

    private function roleSnapshotPayload(array $role, Carbon $syncedAt): array
    {
        return [
            'name' => (string) ($role['name'] ?? ''),
            'rights' => $role['rights'] ?? [],
            'users' => $role['_embedded']['users'] ?? $role['users'] ?? null,
            'raw' => $role,
            'synced_at' => $syncedAt,
        ];
    }
}
