@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold">Создать воронку: {{ $account->name }}</h1>
    <div class="text-sm text-slate-500">{{ $account->base_domain }}</div>
</div>

<form method="post" action="{{ route('amo-accounts.pipelines.store', $account) }}" class="space-y-6 rounded border border-slate-200 bg-white p-5">
    @csrf

    <div class="grid gap-4 md:grid-cols-2">
        <label class="block text-sm">
            <span>Название воронки</span>
            <input name="name" value="{{ old('name', 'Продажи B2B') }}" required class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span>Сортировка</span>
            <input name="sort" type="number" min="1" max="10000" value="{{ old('sort', 20) }}" required class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input name="is_main" value="1" type="checkbox" @checked(old('is_main')) class="rounded border-slate-300">
            <span>Сделать главной</span>
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input name="is_unsorted_on" value="1" type="checkbox" @checked(old('is_unsorted_on', true)) class="rounded border-slate-300">
            <span>Включить неразобранное</span>
        </label>
    </div>

    <div>
        <h2 class="mb-3 font-semibold">Этапы</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-slate-500">
                    <tr><th class="py-2">Системный ID</th><th>Название</th><th>Сортировка</th><th>Цвет</th></tr>
                </thead>
                <tbody>
                    @foreach ($defaultStatuses as $index => $status)
                        <tr class="border-t border-slate-100">
                            <td class="py-2">
                                @if (isset($status['id']))
                                    <input type="hidden" name="statuses[{{ $index }}][id]" value="{{ $status['id'] }}">
                                    <span class="rounded bg-slate-100 px-2 py-1 text-xs">{{ $status['id'] }}</span>
                                @else
                                    <span class="text-slate-400">обычный</span>
                                @endif
                            </td>
                            <td>
                                <input name="statuses[{{ $index }}][name]" value="{{ old("statuses.$index.name", $status['name']) }}" class="w-full rounded border-slate-300">
                            </td>
                            <td>
                                @if (! isset($status['id']))
                                    <input name="statuses[{{ $index }}][sort]" type="number" min="1" max="9999" value="{{ old("statuses.$index.sort", $status['sort']) }}" class="w-28 rounded border-slate-300">
                                @else
                                    <span class="text-slate-400">системная</span>
                                @endif
                            </td>
                            <td>
                                @if (! isset($status['id']))
                                    <input name="statuses[{{ $index }}][color]" type="color" value="{{ old("statuses.$index.color", $status['color']) }}" class="h-9 w-16 rounded border-slate-300">
                                @else
                                    <span class="text-slate-400">amoCRM</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @for ($index = count($defaultStatuses); $index < count($defaultStatuses) + 4; $index++)
                        <tr class="border-t border-slate-100">
                            <td class="py-2"><span class="text-slate-400">обычный</span></td>
                            <td><input name="statuses[{{ $index }}][name]" value="{{ old("statuses.$index.name") }}" placeholder="Дополнительный этап" class="w-full rounded border-slate-300"></td>
                            <td><input name="statuses[{{ $index }}][sort]" type="number" min="1" max="9999" value="{{ old("statuses.$index.sort", ($index + 1) * 10) }}" class="w-28 rounded border-slate-300"></td>
                            <td><input name="statuses[{{ $index }}][color]" type="color" value="{{ old("statuses.$index.color", '#99ccff') }}" class="h-9 w-16 rounded border-slate-300"></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="rounded bg-blue-700 px-4 py-2 text-white hover:bg-blue-800">Создать в amoCRM</button>
        <a href="{{ route('amo-accounts.pipelines.index', $account) }}" class="rounded border border-slate-300 px-4 py-2">Отмена</a>
    </div>
</form>
@endsection
