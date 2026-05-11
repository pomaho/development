@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold">{{ $account->name }}</h1>
        <div class="text-sm text-slate-500">{{ $account->base_domain }}</div>
    </div>
    <div class="flex flex-wrap gap-2">
        @can('sync', $account)
            <form method="post" action="{{ route('amo-accounts.test', $account) }}">@csrf<button class="rounded border border-slate-300 px-3 py-2 text-sm">Проверить соединение</button></form>
            <form method="post" action="{{ route('amo-accounts.sync', $account) }}">@csrf<button class="rounded bg-blue-700 px-3 py-2 text-sm text-white">Синхронизировать</button></form>
        @endcan
        @can('update', $account)
            <a href="{{ route('amo-accounts.edit', $account) }}" class="rounded border border-slate-300 px-3 py-2 text-sm">Редактировать</a>
            <form method="post" action="{{ route('amo-accounts.deactivate', $account) }}">@csrf<button class="rounded border border-slate-300 px-3 py-2 text-sm">Деактивировать</button></form>
        @endcan
    </div>
</div>

<div class="grid gap-4 md:grid-cols-4">
    <x-dashboard.metric label="Auth type" :value="$account->credentials?->auth_type ?? '-'" />
    <x-dashboard.metric label="Auth status" :value="$account->auth_status ?? '-'" />
    <x-dashboard.metric label="Пользователи" :value="$usersCount" />
    <x-dashboard.metric label="Администраторы" :value="$adminsCount" />
</div>

@if ($account->settings)
    <div class="mt-6 grid gap-4 md:grid-cols-4">
        <x-dashboard.metric label="Компания" :value="$account->settings['company_name'] ?? $account->name" />
        <x-dashboard.metric label="Часовой пояс" :value="$account->settings['timezone'] ?? '-'" />
        <x-dashboard.metric label="Валюта" :value="$account->settings['currency'] ?? '-'" />
        <x-dashboard.metric label="amoCRM ID" :value="$account->account_id ?? '-'" />
    </div>
@endif

<div class="mt-6 flex gap-3 text-sm">
    <a class="text-blue-700" href="{{ route('amo-accounts.dashboard', $account) }}">Dashboard клиента</a>
    <a class="text-blue-700" href="{{ route('amo-accounts.users', $account) }}">Пользователи</a>
    <a class="text-blue-700" href="{{ route('amo-accounts.roles', $account) }}">Роли</a>
    <a class="text-blue-700" href="{{ route('amo-accounts.pipelines.index', $account) }}">Воронки</a>
    <a class="text-blue-700" href="{{ route('amo-accounts.crm-audit.index', $account) }}">CRM-аудит</a>
    <a class="text-blue-700" href="{{ route('amo-accounts.integrations', $account) }}">Интеграции</a>
    <a class="text-blue-700" href="{{ route('amo-accounts.widgets', $account) }}">Dashboard-блоки</a>
</div>

<div class="mt-6">
    <x-dashboard.table>
        <h2 class="mb-3 font-semibold">Последние API-логи</h2>
        <table class="w-full text-left text-sm">
            <thead class="text-slate-500"><tr><th class="py-2">Дата</th><th>Метод</th><th>Status</th><th>URL</th><th>Ошибка</th></tr></thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr class="border-t border-slate-100"><td class="py-2">{{ $log->created_at }}</td><td>{{ $log->method }}</td><td>{{ $log->status_code }}</td><td>{{ $log->url }}</td><td>{{ $log->error_message }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </x-dashboard.table>
</div>
@endsection
