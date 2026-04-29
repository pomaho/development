@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold">Dashboard-блоки: {{ $account->name }}</h1>
    <div class="text-sm text-slate-500">{{ $account->base_domain }}</div>
</div>

<x-dashboard.table>
    <table class="w-full text-left text-sm">
        <thead class="text-slate-500">
            <tr><th class="py-2">Код</th><th>Название блока</th><th>Компонент</th><th>Порядок</th><th>Статус</th></tr>
        </thead>
        <tbody>
            @foreach ($widgets as $widget)
                <tr class="border-t border-slate-100">
                    <td class="py-2 font-mono text-xs">{{ $widget->code }}</td>
                    <td>{{ $widget->name }}</td>
                    <td>{{ $widget->component_key }}</td>
                    <td>{{ $widget->sort_order }}</td>
                    <td>{{ $widget->is_enabled ? 'enabled' : 'disabled' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-dashboard.table>
@endsection
