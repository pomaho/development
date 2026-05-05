@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold">OAuth-подключение amoCRM</h1>
        <p class="mt-1 text-sm text-slate-600">Вариант без ручного создания приватной интеграции в аккаунте клиента.</p>
    </div>
    <form method="post" action="{{ route('amo-oauth.external.store') }}" class="flex gap-2">
        @csrf
        <input name="name" class="rounded border-slate-300 text-sm" placeholder="Название клиента">
        <button class="rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800">Создать подключение</button>
    </form>
</div>

<x-dashboard.table>
    <table class="w-full text-left text-sm">
        <thead class="text-slate-500">
            <tr>
                <th class="py-2">Клиент</th>
                <th>Домен</th>
                <th>Статус</th>
                <th>Создано</th>
                <th>Аккаунт</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($connections as $connection)
                <tr class="border-t border-slate-100">
                    <td class="py-2 font-medium">{{ $connection->name ?? '-' }}</td>
                    <td>{{ $connection->base_domain ?? '-' }}</td>
                    <td>{{ $connection->status }}</td>
                    <td>{{ $connection->created_at }}</td>
                    <td>
                        @if ($connection->account)
                            <a class="text-blue-700" href="{{ route('amo-accounts.show', $connection->account) }}">{{ $connection->account->name }}</a>
                        @else
                            -
                        @endif
                    </td>
                    <td><a class="text-blue-700" href="{{ route('amo-oauth.external.show', $connection) }}">открыть</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-4 text-slate-500">OAuth-подключений пока нет.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $connections->links() }}</div>
</x-dashboard.table>
@endsection
