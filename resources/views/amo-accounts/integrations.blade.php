@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold">Интеграции: {{ $account->name }}</h1>
    <div class="text-sm text-slate-500">{{ $account->base_domain }}</div>
</div>

<div class="grid gap-4 md:grid-cols-2">
    @foreach ($modules as $module)
        <x-dashboard.card>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold">{{ $module->name }}</h2>
                    <div class="mt-1 text-sm text-slate-500">{{ $module->description ?: 'Модуль без описания.' }}</div>
                    <div class="mt-3 text-xs text-slate-500">code: {{ $module->code }}</div>
                </div>
                <span class="rounded px-2 py-1 text-xs {{ $module->is_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                    {{ $module->is_enabled ? 'enabled' : 'disabled' }}
                </span>
            </div>
            @if ($module->code === 'users_audit')
                <div class="mt-4 flex flex-wrap gap-2 text-sm">
                    <a class="rounded border border-slate-300 px-3 py-2" href="{{ route('amo-accounts.users', $account) }}">Таблица прав</a>
                    <a class="rounded border border-slate-300 px-3 py-2" href="{{ route('amo-accounts.roles', $account) }}">Роли</a>
                </div>
            @endif
            @if ($module->code === 'pipelines_builder')
                <div class="mt-4 flex flex-wrap gap-2 text-sm">
                    <a class="rounded border border-slate-300 px-3 py-2" href="{{ route('amo-accounts.pipelines.index', $account) }}">Список воронок</a>
                    @can('sync', $account)
                        <a class="rounded border border-slate-300 px-3 py-2" href="{{ route('amo-accounts.pipelines.create', $account) }}">Создать воронку</a>
                    @endcan
                </div>
            @endif
        </x-dashboard.card>
    @endforeach
</div>
@endsection
