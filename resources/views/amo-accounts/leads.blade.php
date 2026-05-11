@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold">Сделки: {{ $account->name }}</h1>
    <div class="flex flex-wrap gap-3 text-sm">
        <a href="{{ route('amo-accounts.show', $account) }}" class="text-blue-700">Назад к аккаунту</a>
        <a href="{{ route('amo-accounts.leads.export', array_merge(['amo_account' => $account], request()->query())) }}" class="text-blue-700">Экспорт в Excel</a>
    </div>
</div>

<form method="get" class="mb-4 grid gap-3 rounded border border-slate-200 bg-white p-4 text-sm md:grid-cols-6">
    <input name="search" value="{{ request('search') }}" placeholder="Название или ID" class="rounded border-slate-300">
    <select name="pipeline_id" class="rounded border-slate-300">
        <option value="">Все воронки</option>
        @foreach ($pipelines as $pipeline)
            <option value="{{ $pipeline->amo_pipeline_id }}" @selected((string) request('pipeline_id') === (string) $pipeline->amo_pipeline_id)>{{ $pipeline->name }}</option>
        @endforeach
    </select>
    <select name="status_id" class="rounded border-slate-300">
        <option value="">Все этапы</option>
        @foreach ($statuses as $status)
            <option value="{{ $status->amo_status_id }}" @selected((string) request('status_id') === (string) $status->amo_status_id)>
                {{ $status->name }} ({{ $status->amo_status_id }})
            </option>
        @endforeach
    </select>
    <select name="responsible_user_id" class="rounded border-slate-300">
        <option value="">Все ответственные</option>
        @foreach ($responsibles as $responsibleId)
            <option value="{{ $responsibleId }}" @selected((string) request('responsible_user_id') === (string) $responsibleId)>{{ $responsibleId }}</option>
        @endforeach
    </select>
    <input name="created_from" type="date" value="{{ request('created_from') }}" class="rounded border-slate-300">
    <input name="created_to" type="date" value="{{ request('created_to') }}" class="rounded border-slate-300">
    <div class="flex flex-wrap gap-2 md:col-span-6">
        <button class="rounded bg-blue-700 px-3 py-2 text-white">Фильтр</button>
        <a href="{{ route('amo-accounts.leads', $account) }}" class="rounded border border-slate-300 px-3 py-2">Сбросить</a>
    </div>
</form>

<x-dashboard.table>
    <table class="w-full text-left text-xs">
        <thead class="text-slate-500">
            <tr><th class="py-2">ID</th><th>Название</th><th>Воронка</th><th>Этап</th><th>Ответственный</th><th>Создана</th><th>Обновлена</th><th>Бюджет</th><th>Поля</th><th>Raw</th></tr>
        </thead>
        <tbody>
            @forelse ($leads as $lead)
                @php
                    $pipeline = $pipelines->firstWhere('amo_pipeline_id', (int) $lead->pipeline_id);
                    $status = $statuses->where('amo_pipeline_id', (int) $lead->pipeline_id)->firstWhere('amo_status_id', (int) $lead->status_id);
                    $raw = $lead->raw ?? [];
                @endphp
                <tr class="border-t border-slate-100 align-top">
                    <td class="py-2">{{ $lead->external_id }}</td>
                    <td class="font-medium">{{ $lead->name }}</td>
                    <td>{{ $pipeline?->name ?? $lead->pipeline_id }}</td>
                    <td>{{ $status?->name ?? $lead->status_id }}</td>
                    <td>{{ $lead->responsible_user_id }}</td>
                    <td>{{ $lead->entity_created_at }}</td>
                    <td>{{ $lead->entity_updated_at }}</td>
                    <td>{{ $raw['price'] ?? '-' }}</td>
                    <td class="max-w-lg">{{ Str::limit(json_encode($lead->custom_fields_values, JSON_UNESCAPED_UNICODE), 180) }}</td>
                    <td><x-json-viewer :data="$lead->raw" /></td>
                </tr>
            @empty
                <tr><td colspan="10" class="py-4 text-slate-500">Сделки не найдены. Запустите CRM-аудит за нужный период.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $leads->links() }}</div>
</x-dashboard.table>
@endsection
