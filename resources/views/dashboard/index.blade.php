@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold">{{ $currentAccount ? 'Dashboard: '.$currentAccount->name : 'Dashboard: все аккаунты' }}</h1>
        @if ($currentAccount)
            <div class="text-sm text-slate-500">{{ $currentAccount->base_domain }}</div>
        @endif
    </div>
    <div class="flex items-center gap-2 text-sm">
        <select class="rounded border-slate-300" onchange="if (this.value) window.location.href = this.value">
            <option value="{{ route('dashboard') }}" @selected(! $selectedAccountId)>Все аккаунты</option>
            @foreach ($accounts as $account)
                <option value="{{ route('amo-accounts.dashboard', $account) }}" @selected($selectedAccountId === $account->id)>{{ $account->name }}</option>
            @endforeach
        </select>
    </div>
</div>

@if ($currentAccount)
    <div class="mb-6 flex flex-wrap gap-3 text-sm">
        <a class="rounded border border-slate-300 bg-white px-3 py-2 hover:border-blue-400" href="{{ route('amo-accounts.show', $currentAccount) }}">Карточка клиента</a>
        <a class="rounded border border-slate-300 bg-white px-3 py-2 hover:border-blue-400" href="{{ route('amo-accounts.users', $currentAccount) }}">Пользователи</a>
        <a class="rounded border border-slate-300 bg-white px-3 py-2 hover:border-blue-400" href="{{ route('amo-accounts.roles', $currentAccount) }}">Роли</a>
        <a class="rounded border border-slate-300 bg-white px-3 py-2 hover:border-blue-400" href="{{ route('amo-accounts.pipelines.index', $currentAccount) }}">Воронки</a>
        <a class="rounded border border-slate-300 bg-white px-3 py-2 hover:border-blue-400" href="{{ route('amo-accounts.integrations', $currentAccount) }}">Интеграции</a>
        <a class="rounded border border-slate-300 bg-white px-3 py-2 hover:border-blue-400" href="{{ route('amo-accounts.widgets', $currentAccount) }}">Dashboard-блоки</a>
    </div>
@endif

<div class="grid gap-4 md:grid-cols-3">
    <x-dashboard.metric label="Подключено аккаунтов" :value="$summary['accounts_count']" />
    <x-dashboard.metric label="Активные аккаунты" :value="$summary['active_accounts_count']" />
    <x-dashboard.metric label="Последняя синхронизация" :value="$summary['last_sync'] ?: 'нет'" />
    <x-dashboard.metric label="Пользователи" :value="$summary['users_count']" />
    <x-dashboard.metric label="Администраторы" :value="$summary['admins_count']" />
    <x-dashboard.metric label="Dashboard-блоки" :value="$widgets->count()" />
</div>

<div class="mt-6">
    <x-dashboard.table>
        <h2 class="mb-3 font-semibold">Последние ошибки API</h2>
        <table class="w-full text-left text-sm">
            <thead class="text-slate-500">
                <tr><th class="py-2">Дата</th><th>Аккаунт</th><th>Status</th><th>Ошибка</th></tr>
            </thead>
            <tbody>
                @forelse ($recentErrors as $log)
                    <tr class="border-t border-slate-100">
                        <td class="py-2">{{ $log->created_at }}</td>
                        <td>{{ $log->account?->name ?? '-' }}</td>
                        <td>{{ $log->status_code ?? '-' }}</td>
                        <td>{{ $log->error_message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-slate-500">Ошибок пока нет.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-dashboard.table>
</div>
@endsection
