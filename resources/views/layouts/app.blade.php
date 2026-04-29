<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900">
    <div class="min-h-screen">
        @auth
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <a href="{{ route('dashboard') }}" class="text-lg font-semibold">{{ config('app.name') }}</a>
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <select class="rounded border-slate-300 text-sm" onchange="if (this.value) window.location.href = this.value">
                            <option value="{{ route('dashboard') }}" @selected(! $layoutCurrentAmoAccount)>Все аккаунты</option>
                            @foreach ($layoutAmoAccounts as $account)
                                <option value="{{ route('amo-accounts.dashboard', $account) }}" @selected($layoutCurrentAmoAccount?->is($account))>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                        @if ($layoutCurrentAmoAccount)
                            <span class="rounded bg-blue-50 px-2 py-1 text-blue-800">{{ $layoutCurrentAmoAccount->base_domain }}</span>
                        @endif
                    </div>
                    <nav class="flex flex-wrap items-center gap-4 text-sm">
                        <a class="hover:text-blue-700" href="{{ $layoutCurrentAmoAccount ? route('amo-accounts.dashboard', $layoutCurrentAmoAccount) : route('dashboard') }}">Dashboard</a>
                        <a class="hover:text-blue-700" href="{{ route('amo-accounts.index') }}">Клиенты</a>
                        @if ($layoutCurrentAmoAccount)
                            <a class="hover:text-blue-700" href="{{ route('amo-accounts.users', $layoutCurrentAmoAccount) }}">Users audit</a>
                            <a class="hover:text-blue-700" href="{{ route('amo-accounts.integrations', $layoutCurrentAmoAccount) }}">Интеграции</a>
                            <a class="hover:text-blue-700" href="{{ route('amo-accounts.widgets', $layoutCurrentAmoAccount) }}">Dashboard-блоки</a>
                        @endif
                        <a class="hover:text-blue-700" href="{{ route('logs.api') }}">API-логи</a>
                        <span class="rounded bg-slate-100 px-2 py-1">{{ auth()->user()->role }}</span>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="text-slate-600 hover:text-red-700">Выйти</button>
                        </form>
                    </nav>
                </div>
            </header>
        @endauth

        <main class="mx-auto max-w-7xl px-4 py-6">
            @if (session('status'))
                <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
