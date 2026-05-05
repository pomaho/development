@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold">CRM-аудит: {{ $account->name }}</h1>
        <div class="text-sm text-slate-500">{{ $account->base_domain }}</div>
    </div>
    @can('sync', $account)
        <form method="post" action="{{ route('amo-accounts.crm-audit.sync', $account) }}" class="grid gap-2 rounded border border-slate-200 bg-white p-3 text-sm md:grid-cols-4">
            @csrf
            <label>
                <span class="text-xs text-slate-500">С даты</span>
                <input name="from" type="date" value="{{ now()->subMonths(6)->format('Y-m-d') }}" class="mt-1 w-full rounded border-slate-300">
            </label>
            <label>
                <span class="text-xs text-slate-500">По дату</span>
                <input name="to" type="date" value="{{ now()->format('Y-m-d') }}" class="mt-1 w-full rounded border-slate-300">
            </label>
            <label class="flex items-end gap-2 pb-2">
                <input name="structure_only" value="1" type="checkbox" class="rounded border-slate-300">
                <span>Только структура</span>
            </label>
            <button class="self-end rounded bg-blue-700 px-4 py-2 text-white hover:bg-blue-800">Запустить</button>
        </form>
    @endcan
</div>

<div class="grid gap-4 md:grid-cols-4">
    <x-dashboard.metric label="Воронки" :value="$summary['pipelines']" />
    <x-dashboard.metric label="Этапы" :value="$summary['statuses']" />
    <x-dashboard.metric label="Поля CRM" :value="$summary['custom_fields']" />
    <x-dashboard.metric label="Последняя выгрузка" :value="$summary['last_sync'] ?: 'нет'" />
    <x-dashboard.metric label="Сделки" :value="$summary['leads']" />
    <x-dashboard.metric label="Контакты" :value="$summary['contacts']" />
    <x-dashboard.metric label="События" :value="$summary['events']" />
    <x-dashboard.metric label="Задачи" :value="$summary['tasks']" />
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <x-dashboard.table>
        <h2 class="mb-3 font-semibold">Воронки</h2>
        <table class="w-full text-left text-sm">
            <thead class="text-slate-500"><tr><th class="py-2">ID</th><th>Название</th><th>Главная</th><th>Неразобранное</th></tr></thead>
            <tbody>
                @forelse ($pipelines as $pipeline)
                    <tr class="border-t border-slate-100">
                        <td class="py-2">{{ $pipeline->amo_pipeline_id }}</td>
                        <td>{{ $pipeline->name }}</td>
                        <td>{{ $pipeline->is_main ? 'да' : 'нет' }}</td>
                        <td>{{ $pipeline->is_unsorted_on ? 'да' : 'нет' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-slate-500">Данных пока нет.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-dashboard.table>

    <x-dashboard.table>
        <h2 class="mb-3 font-semibold">Поля CRM</h2>
        <table class="w-full text-left text-sm">
            <thead class="text-slate-500"><tr><th class="py-2">Сущность</th><th>ID</th><th>Название</th><th>Тип</th></tr></thead>
            <tbody>
                @forelse ($fields as $field)
                    <tr class="border-t border-slate-100">
                        <td class="py-2">{{ $field->entity_type }}</td>
                        <td>{{ $field->amo_field_id }}</td>
                        <td>{{ $field->name }}</td>
                        <td>{{ $field->field_type }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-slate-500">Данных пока нет.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-dashboard.table>
</div>

<div class="mt-6">
    <x-dashboard.table>
        <h2 class="mb-3 font-semibold">Последние snapshots</h2>
        <table class="w-full text-left text-sm">
            <thead class="text-slate-500"><tr><th class="py-2">Тип</th><th>ID</th><th>Название</th><th>Pipeline</th><th>Status</th><th>Sync</th><th>Raw</th></tr></thead>
            <tbody>
                @forelse ($recentEntities as $entity)
                    <tr class="border-t border-slate-100 align-top">
                        <td class="py-2">{{ $entity->entity_type }}</td>
                        <td>{{ $entity->external_id }}</td>
                        <td>{{ $entity->name }}</td>
                        <td>{{ $entity->pipeline_id }}</td>
                        <td>{{ $entity->status_id }}</td>
                        <td>{{ $entity->synced_at }}</td>
                        <td><x-json-viewer :data="$entity->raw" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-4 text-slate-500">Данных пока нет.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-dashboard.table>
</div>
@endsection
