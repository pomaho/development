@extends('layouts.app')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-semibold">amoCRM аккаунты</h1>
    @can('create', App\Models\AmoAccount::class)
        <div class="flex gap-2">
            <a href="{{ route('amo-oauth.external.index') }}" class="rounded border border-blue-700 px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">Подключить через OAuth</a>
            <a href="{{ route('amo-accounts.create') }}" class="rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800">Добавить вручную</a>
        </div>
    @endcan
</div>
<x-dashboard.table>
    <table class="w-full text-left text-sm">
        <thead class="text-slate-500">
            <tr><th class="py-2">Название</th><th>Домен</th><th>Auth</th><th>Активен</th><th>Статус</th><th>Sync</th><th>Действия</th></tr>
        </thead>
        <tbody>
            @forelse ($accounts as $account)
                <tr class="border-t border-slate-100">
                    <td class="py-2 font-medium">{{ $account->name }}</td>
                    <td>{{ $account->base_domain }}</td>
                    <td>{{ $account->credentials?->auth_type ?? '-' }}</td>
                    <td>{{ $account->is_active ? 'да' : 'нет' }}</td>
                    <td>{{ $account->auth_status ?? '-' }}</td>
                    <td>{{ $account->last_successful_sync_at ?? '-' }}</td>
                    <td class="flex flex-wrap gap-2 py-2">
                        <a class="text-blue-700" href="{{ route('amo-accounts.show', $account) }}">открыть</a>
                        @can('sync', $account)
                            <form method="post" action="{{ route('amo-accounts.test', $account) }}">@csrf<button class="text-blue-700">проверить</button></form>
                            <form method="post" action="{{ route('amo-accounts.sync', $account) }}">@csrf<button class="text-blue-700">синхр.</button></form>
                        @endcan
                        @can('update', $account)<a class="text-blue-700" href="{{ route('amo-accounts.edit', $account) }}">ред.</a>@endcan
                        @can('delete', $account)
                            <form method="post" action="{{ route('amo-accounts.destroy', $account) }}">@csrf @method('delete')<button class="text-red-700">удалить</button></form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-4 text-slate-500">Подключений пока нет.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $accounts->links() }}</div>
</x-dashboard.table>
@endsection
