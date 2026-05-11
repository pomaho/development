@extends('layouts.app')

@section('content')
<div class="mb-6">
    <a class="text-sm text-blue-700 hover:text-blue-900" href="{{ route('amo-accounts.pipelines.index', $account) }}">← Все воронки</a>
    <h1 class="mt-2 text-2xl font-semibold">Клонировать воронку</h1>
    <div class="text-sm text-slate-500">{{ $account->name }} · {{ $account->base_domain }}</div>
</div>

@if ($error)
    <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Не удалось загрузить исходную воронку: {{ $error }}
    </div>
@endif

<form method="post" action="{{ route('amo-accounts.pipelines.clone', [$account, $pipelineId]) }}" class="space-y-6 rounded border border-slate-200 bg-white p-5">
    @csrf

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <div class="text-sm text-slate-500">Исходная воронка</div>
            <div class="mt-1 text-lg font-semibold">{{ $pipeline['name'] ?? 'Воронка '.$pipelineId }}</div>
            <div class="mt-1 text-sm text-slate-500">ID {{ $pipeline['id'] ?? $pipelineId }} · этапов: {{ count($statuses) }}</div>
        </div>
        <label class="block text-sm">
            <span>Название новой воронки</span>
            <input name="name" value="{{ old('name', 'Копия: '.($pipeline['name'] ?? 'воронка '.$pipelineId)) }}" required class="mt-1 w-full rounded border-slate-300">
        </label>
    </div>

    <div>
        <h2 class="mb-3 font-semibold">Этапы, которые будут скопированы</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-slate-500">
                    <tr><th class="py-2">ID</th><th>Название</th><th>Сортировка</th><th>Цвет</th><th>Тип</th></tr>
                </thead>
                <tbody>
                    @forelse ($statuses as $status)
                        <tr class="border-t border-slate-100">
                            <td class="py-2">{{ $status['id'] ?? '-' }}</td>
                            <td class="font-medium">{{ $status['name'] ?? '-' }}</td>
                            <td>{{ $status['sort'] ?? '-' }}</td>
                            <td>
                                <span class="inline-block h-5 w-10 rounded border border-slate-200" style="background: {{ $status['color'] ?? '#94a3b8' }}"></span>
                            </td>
                            <td>{{ $status['type'] ?? 'regular' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-slate-500">Этапы не загружены.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        Будет создана новая воронка с теми же этапами, цветами, сортировкой и настройкой неразобранного. Главной новая воронка не назначается автоматически.
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="rounded bg-blue-700 px-4 py-2 text-white hover:bg-blue-800">Создать копию в amoCRM</button>
        <a href="{{ route('amo-accounts.pipelines.index', $account) }}" class="rounded border border-slate-300 px-4 py-2">Отмена</a>
    </div>
</form>
@endsection
