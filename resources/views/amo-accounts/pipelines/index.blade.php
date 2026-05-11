@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold">Воронки: {{ $account->name }}</h1>
        <div class="text-sm text-slate-500">{{ $account->base_domain }}</div>
    </div>
    @can('sync', $account)
        <a href="{{ route('amo-accounts.pipelines.create', $account) }}" class="rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800">Создать воронку</a>
    @endcan
</div>

@if ($error)
    <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Не удалось загрузить воронки из amoCRM: {{ $error }}
    </div>
@endif

<x-dashboard.table>
    <table class="w-full text-left text-sm">
        <thead class="text-slate-500">
            <tr><th class="py-2">ID</th><th>Название</th><th>Главная</th><th>Неразобранное</th><th>Архив</th><th>Этапов</th><th>Этапы</th><th>Действия</th></tr>
        </thead>
        <tbody>
            @forelse ($pipelines as $pipeline)
                <tr class="border-t border-slate-100 align-top">
                    <td class="py-2">{{ $pipeline['id'] ?? '-' }}</td>
                    <td class="font-medium">
                        @if (isset($pipeline['id']))
                            <a class="text-blue-700 hover:text-blue-900" href="{{ route('amo-accounts.pipelines.show', [$account, $pipeline['id']]) }}">
                                {{ $pipeline['name'] ?? '-' }}
                            </a>
                        @else
                            {{ $pipeline['name'] ?? '-' }}
                        @endif
                    </td>
                    <td>{{ ($pipeline['is_main'] ?? false) ? 'да' : 'нет' }}</td>
                    <td>{{ ($pipeline['is_unsorted_on'] ?? false) ? 'да' : 'нет' }}</td>
                    <td>{{ ($pipeline['is_archive'] ?? false) ? 'да' : 'нет' }}</td>
                    <td>{{ count($pipeline['_embedded']['statuses'] ?? []) }}</td>
                    <td>
                        <div class="flex max-w-2xl flex-wrap gap-2">
                            @foreach (($pipeline['_embedded']['statuses'] ?? []) as $status)
                                <span class="rounded border border-slate-200 px-2 py-1 text-xs">
                                    {{ $status['name'] ?? '-' }}
                                </span>
                            @endforeach
                        </div>
                    </td>
                    <td>
                        @if (isset($pipeline['id']))
                            <div class="flex flex-wrap gap-2 text-sm">
                                <a class="text-blue-700 hover:text-blue-900" href="{{ route('amo-accounts.pipelines.show', [$account, $pipeline['id']]) }}">Настройки</a>
                                @can('sync', $account)
                                    <a class="text-blue-700 hover:text-blue-900" href="{{ route('amo-accounts.pipelines.clone-form', [$account, $pipeline['id']]) }}">Клонировать</a>
                                @endcan
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-4 text-slate-500">Воронки не загружены или пока отсутствуют.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-dashboard.table>
@endsection
