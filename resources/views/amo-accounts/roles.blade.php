@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold">Роли: {{ $account->name }}</h1>
    <a href="{{ route('amo-accounts.show', $account) }}" class="text-sm text-blue-700">Назад к аккаунту</a>
</div>

<x-dashboard.table>
    <table class="w-full text-left text-sm">
        <thead class="text-slate-500"><tr><th class="py-2">ID роли</th><th>Название</th><th>Пользователей</th><th>Права</th><th>Sync</th><th>JSON</th></tr></thead>
        <tbody>
            @foreach ($roles as $role)
                <tr class="border-t border-slate-100 align-top">
                    <td class="py-2">{{ $role->amo_role_id }}</td>
                    <td class="font-medium">{{ $role->name }}</td>
                    <td>{{ is_array($role->users) ? count($role->users) : 0 }}</td>
                    <td class="max-w-lg">{{ Str::limit(json_encode($role->rights, JSON_UNESCAPED_UNICODE), 180) }}</td>
                    <td>{{ $role->synced_at }}</td>
                    <td><x-json-viewer :data="$role->rights" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="mt-4">{{ $roles->links() }}</div>
</x-dashboard.table>
@endsection
