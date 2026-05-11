@extends('layouts.public')

@section('content')
<main class="grid min-h-screen place-items-center bg-[#eef3f7] px-5 py-10">
    <div class="w-full max-w-xl rounded-2xl border border-white/80 bg-white p-8 text-center shadow-2xl shadow-[#0d4250]/10">
        @if ($connection?->status === App\Models\AmoOAuthConnection::STATUS_CONNECTED)
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#d8fff5] text-2xl font-semibold text-[#00a68f]">✓</div>
            <h1 class="mt-5 text-3xl font-semibold text-[#102033]">Интеграция Sonic Expert установлена</h1>
            <p class="mt-3 text-sm leading-6 text-[#617186]">
                Аккаунт {{ $connection->account?->name }} подключен. Команда Sonic Expert продолжит настройку сервиса и аналитики.
            </p>
        @elseif ($connection)
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-amber-50 text-2xl font-semibold text-amber-600">!</div>
            <h1 class="mt-5 text-3xl font-semibold text-[#102033]">Авторизация еще не завершена</h1>
            <p class="mt-3 text-sm leading-6 text-[#617186]">Текущий статус: {{ $connection->status }}.</p>
            @if ($connection->error_message)
                <p class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-left text-sm text-red-800">{{ $connection->error_message }}</p>
            @endif
        @else
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-red-50 text-2xl font-semibold text-red-600">×</div>
            <h1 class="mt-5 text-3xl font-semibold text-[#102033]">Не удалось обработать OAuth callback</h1>
            <p class="mt-3 text-sm leading-6 text-[#617186]">Проверьте, что amoCRM вернула параметры code, referer и state.</p>
        @endif
    </div>
</main>
@endsection
