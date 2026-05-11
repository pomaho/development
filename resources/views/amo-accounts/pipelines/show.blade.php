@extends('layouts.app')

@section('content')
@php
    $pipeline = $details['pipeline'] ?? [];
    $statuses = $details['statuses'] ?? [];
    $stageRows = $details['stage_rows'] ?? [];
    $sources = $details['sources'] ?? [];
    $allSources = $details['all_sources'] ?? [];
    $amoWidgets = $details['widgets'] ?? [];
    $websiteButtons = $details['website_buttons'] ?? [];
    $lossReasons = $details['loss_reasons'] ?? [];
    $detailErrors = $details['errors'] ?? [];
    $limitations = $details['limitations'] ?? [];
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <a class="text-sm text-blue-700 hover:text-blue-900" href="{{ route('amo-accounts.pipelines.index', $account) }}">← Все воронки</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ $pipeline['name'] ?? 'Воронка '.$pipelineId }}</h1>
        <div class="text-sm text-slate-500">{{ $account->name }} · {{ $account->base_domain }}</div>
    </div>
    @can('sync', $account)
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('amo-accounts.pipelines.clone-form', [$account, $pipelineId]) }}" class="rounded border border-slate-300 bg-white px-4 py-2 text-sm hover:border-blue-400">Клонировать</a>
            <a href="{{ route('amo-accounts.pipelines.create', $account) }}" class="rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800">Создать воронку</a>
        </div>
    @endcan
</div>

@if ($error)
    <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Не удалось загрузить настройки воронки: {{ $error }}
    </div>
@endif

@if ($detailErrors)
    <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <div class="font-medium">Часть разделов amoCRM не отдала данные.</div>
        <div class="mt-1 text-xs text-amber-700">Страница продолжает показывать все, что удалось получить.</div>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach ($detailErrors as $section => $message)
                <li><span class="font-medium">{{ $section }}:</span> {{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mb-6 grid gap-4 md:grid-cols-5">
    <x-dashboard.metric label="ID воронки" :value="$pipeline['id'] ?? $pipelineId" />
    <x-dashboard.metric label="Этапов" :value="count($statuses)" />
    <x-dashboard.metric label="Источников" :value="count($sources)" />
    <x-dashboard.metric label="Виджетов" :value="count($amoWidgets)" />
    <x-dashboard.metric label="Причин отказа" :value="count($lossReasons)" />
</div>

<div class="mb-6 overflow-x-auto rounded border border-slate-200 bg-white p-4">
    <div class="mb-3 flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Схема этапов</h2>
        <div class="text-sm text-slate-500">
            Главная: {{ ($pipeline['is_main'] ?? false) ? 'да' : 'нет' }} ·
            Неразобранное: {{ ($pipeline['is_unsorted_on'] ?? false) ? 'включено' : 'выключено' }} ·
            Архив: {{ ($pipeline['is_archive'] ?? false) ? 'да' : 'нет' }}
        </div>
    </div>
    <div class="flex min-w-max gap-3">
        @forelse ($statuses as $status)
            <div class="w-56 rounded border border-slate-200 bg-slate-50" style="border-top: 5px solid {{ $status['color'] ?? '#94a3b8' }}">
                <div class="p-3">
                    <div class="font-medium">{{ $status['name'] ?? '-' }}</div>
                    <div class="mt-2 text-xs text-slate-500">ID {{ $status['id'] ?? '-' }} · sort {{ $status['sort'] ?? '-' }}</div>
                    <div class="mt-1 text-xs text-slate-500">type {{ $status['type'] ?? 'regular' }}</div>
                </div>
            </div>
        @empty
            <div class="text-sm text-slate-500">Этапы не загружены.</div>
        @endforelse
    </div>
</div>

<x-dashboard.table>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Настройки этапов</h2>
        <div class="text-sm text-slate-500">Таблица собрана по данным pipeline statuses, descriptions, sources и required_statuses полей сделок.</div>
    </div>
    <table class="w-full text-left text-sm">
        <thead class="text-slate-500">
            <tr>
                <th class="py-2">Порядок</th>
                <th>Цвет</th>
                <th>Этап</th>
                <th>ID</th>
                <th>Тип</th>
                <th>Описание</th>
                <th>Обязательные поля</th>
                <th>Источники</th>
                <th>JSON</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($stageRows as $row)
                @php
                    $status = $row['status'] ?? [];
                    $requiredFields = $row['required_fields'] ?? [];
                    $rowSources = $row['sources'] ?? [];
                @endphp
                <tr class="border-t border-slate-100 align-top">
                    <td class="py-3">{{ $status['sort'] ?? '-' }}</td>
                    <td>
                        <span class="inline-block h-5 w-10 rounded border border-slate-200" style="background: {{ $status['color'] ?? '#94a3b8' }}"></span>
                    </td>
                    <td class="font-medium">{{ $status['name'] ?? '-' }}</td>
                    <td>{{ $status['id'] ?? '-' }}</td>
                    <td>{{ $status['type'] ?? 'regular' }}</td>
                    <td class="max-w-xs text-slate-600">{{ $row['description'] ?? '-' }}</td>
                    <td class="max-w-sm">
                        @forelse ($requiredFields as $field)
                            <div class="mb-1 rounded bg-slate-100 px-2 py-1 text-xs">
                                {{ $field['name'] ?? 'Поле '.$field['id'] }}
                                <span class="text-slate-500">({{ $field['type'] ?? '-' }})</span>
                            </div>
                        @empty
                            <span class="text-slate-400">-</span>
                        @endforelse
                    </td>
                    <td class="max-w-sm">
                        @forelse ($rowSources as $source)
                            <div class="mb-1 rounded bg-slate-100 px-2 py-1 text-xs">
                                {{ $source['name'] ?? $source['type'] ?? 'Источник '.$source['id'] }}
                            </div>
                        @empty
                            <span class="text-slate-400">-</span>
                        @endforelse
                    </td>
                    <td><x-json-viewer :data="$status" /></td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-4 text-slate-500">Настройки этапов не загружены.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-dashboard.table>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <x-dashboard.table>
        <h2 class="mb-3 text-lg font-semibold">Источники воронки</h2>
        <table class="w-full text-left text-sm">
            <thead class="text-slate-500">
                <tr><th class="py-2">ID</th><th>Название</th><th>Тип</th><th>Этап</th><th>JSON</th></tr>
            </thead>
            <tbody>
                @forelse ($sources as $source)
                    <tr class="border-t border-slate-100 align-top">
                        <td class="py-2">{{ $source['id'] ?? '-' }}</td>
                        <td class="font-medium">{{ $source['name'] ?? '-' }}</td>
                        <td>{{ $source['type'] ?? '-' }}</td>
                        <td>{{ $source['status_id'] ?? $source['default_status_id'] ?? '-' }}</td>
                        <td><x-json-viewer :data="$source" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-slate-500">Привязанные к воронке источники не найдены. Всего источников в аккаунте: {{ count($allSources) }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-dashboard.table>

    <x-dashboard.table>
        <h2 class="mb-3 text-lg font-semibold">Причины отказа</h2>
        <table class="w-full text-left text-sm">
            <thead class="text-slate-500">
                <tr><th class="py-2">ID</th><th>Название</th><th>Порядок</th><th>JSON</th></tr>
            </thead>
            <tbody>
                @forelse ($lossReasons as $reason)
                    <tr class="border-t border-slate-100 align-top">
                        <td class="py-2">{{ $reason['id'] ?? '-' }}</td>
                        <td class="font-medium">{{ $reason['name'] ?? '-' }}</td>
                        <td>{{ $reason['sort'] ?? '-' }}</td>
                        <td><x-json-viewer :data="$reason" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-slate-500">Причины отказа не загружены.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-dashboard.table>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <x-dashboard.table>
        <h2 class="mb-3 text-lg font-semibold">Виджеты аккаунта</h2>
        <table class="w-full text-left text-sm">
            <thead class="text-slate-500">
                <tr><th class="py-2">Код</th><th>Название</th><th>Активен</th><th>JSON</th></tr>
            </thead>
            <tbody>
                @forelse ($amoWidgets as $widget)
                    <tr class="border-t border-slate-100 align-top">
                        <td class="py-2">{{ $widget['code'] ?? $widget['id'] ?? '-' }}</td>
                        <td class="font-medium">{{ $widget['name'] ?? '-' }}</td>
                        <td>{{ ($widget['is_active'] ?? $widget['is_enabled'] ?? false) ? 'да' : 'нет' }}</td>
                        <td><x-json-viewer :data="$widget" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-slate-500">Виджеты не загружены или endpoint недоступен.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-dashboard.table>

    <x-dashboard.table>
        <h2 class="mb-3 text-lg font-semibold">Кнопки и CRM Plugin</h2>
        <table class="w-full text-left text-sm">
            <thead class="text-slate-500">
                <tr><th class="py-2">ID</th><th>Название</th><th>Этап</th><th>JSON</th></tr>
            </thead>
            <tbody>
                @forelse ($websiteButtons as $button)
                    <tr class="border-t border-slate-100 align-top">
                        <td class="py-2">{{ $button['id'] ?? '-' }}</td>
                        <td class="font-medium">{{ $button['name'] ?? $button['button_text'] ?? '-' }}</td>
                        <td>{{ $button['status_id'] ?? $button['default_status_id'] ?? '-' }}</td>
                        <td><x-json-viewer :data="$button" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-slate-500">Привязанные к воронке кнопки не найдены.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-dashboard.table>
</div>

@if ($limitations)
    <div class="mt-6 rounded border border-slate-200 bg-white p-4">
        <h2 class="text-lg font-semibold">Что amoCRM не отдает напрямую</h2>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-600">
            @foreach ($limitations as $limitation)
                <li>{{ $limitation }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mt-6 rounded border border-slate-200 bg-white p-4">
    <h2 class="mb-3 text-lg font-semibold">Raw JSON воронки</h2>
    <x-json-viewer :data="$pipeline" />
</div>
@endsection
