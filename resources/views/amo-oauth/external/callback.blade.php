@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl rounded border border-slate-200 bg-white p-6">
    @if ($connection?->status === App\Models\AmoOAuthConnection::STATUS_CONNECTED)
        <h1 class="text-2xl font-semibold">amoCRM подключена</h1>
        <p class="mt-2 text-sm text-slate-600">OAuth-токены сохранены в зашифрованном виде. Теперь аккаунт доступен в списке клиентов.</p>
        <a class="mt-4 inline-flex rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800" href="{{ route('amo-accounts.show', $connection->account) }}">Открыть аккаунт</a>
    @elseif ($connection)
        <h1 class="text-2xl font-semibold">Авторизация еще не завершена</h1>
        <p class="mt-2 text-sm text-slate-600">Текущий статус: {{ $connection->status }}.</p>
        @if ($connection->error_message)
            <p class="mt-3 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $connection->error_message }}</p>
        @endif
        <a class="mt-4 inline-flex text-sm text-blue-700" href="{{ route('amo-oauth.external.show', $connection) }}">Вернуться к подключению</a>
    @else
        <h1 class="text-2xl font-semibold">Не удалось обработать OAuth callback</h1>
        <p class="mt-2 text-sm text-slate-600">Проверьте, что amoCRM вернула параметры code, referer и state.</p>
    @endif
</div>
@endsection
