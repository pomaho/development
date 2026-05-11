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
    @php
        $navLinkClass = fn (bool $active): string => $active
            ? 'rounded bg-blue-50 px-2 py-1 font-medium text-blue-800'
            : 'rounded px-2 py-1 text-slate-600 hover:bg-slate-100 hover:text-blue-700';
        $routeName = request()->route()?->getName();
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => $layoutCurrentAmoAccount ? route('amo-accounts.dashboard', $layoutCurrentAmoAccount) : route('dashboard')],
        ];

        if ($layoutCurrentAmoAccount) {
            $breadcrumbs[] = ['label' => $layoutCurrentAmoAccount->name, 'url' => route('amo-accounts.show', $layoutCurrentAmoAccount)];
        }

        $breadcrumbMap = [
            'amo-accounts.index' => ['Клиенты', route('amo-accounts.index')],
            'amo-accounts.show' => ['Карточка клиента', null],
            'amo-accounts.edit' => ['Редактирование', null],
            'amo-accounts.dashboard' => ['Dashboard клиента', null],
            'amo-accounts.users' => ['Users audit', null],
            'amo-accounts.leads' => ['Сделки', null],
            'amo-accounts.pipelines.index' => ['Воронки', null],
            'amo-accounts.pipelines.create' => ['Создать воронку', null],
            'amo-accounts.pipelines.show' => ['Настройки воронки', null],
            'amo-accounts.pipelines.clone-form' => ['Клонировать воронку', null],
            'amo-accounts.crm-audit.index' => ['CRM-аудит', null],
            'amo-accounts.integrations' => ['Интеграции', null],
            'amo-accounts.widgets' => ['Dashboard-блоки', null],
            'amo-oauth.external.index' => ['OAuth amoCRM', route('amo-oauth.external.index')],
            'amo-oauth.external.show' => ['OAuth подключение', null],
            'logs.api' => ['API-логи', route('logs.api')],
        ];

        if (isset($breadcrumbMap[$routeName])) {
            [$breadcrumbLabel, $breadcrumbUrl] = $breadcrumbMap[$routeName];
            $breadcrumbs[] = ['label' => $breadcrumbLabel, 'url' => $breadcrumbUrl];
        }
    @endphp
    <div class="min-h-screen">
        @auth
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <a href="{{ route('dashboard') }}" class="text-lg font-semibold">{{ config('app.name') }}</a>
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <select class="rounded border-slate-300 text-sm" onchange="if (this.value) window.location.href = this.value">
                            <option value="{{ route('dashboard') }}" @selected(! $layoutCurrentAmoAccount)>Все аккаунты</option>
                            @foreach ($layoutAmoAccounts as $layoutAccount)
                                <option value="{{ route('amo-accounts.dashboard', $layoutAccount) }}" @selected($layoutCurrentAmoAccount?->is($layoutAccount))>
                                    {{ $layoutAccount->name }}
                                </option>
                            @endforeach
                        </select>
                        @if ($layoutCurrentAmoAccount)
                            <span class="rounded bg-blue-50 px-2 py-1 text-blue-800">{{ $layoutCurrentAmoAccount->base_domain }}</span>
                        @endif
                    </div>
                    <nav class="flex flex-wrap items-center gap-4 text-sm">
                        <a class="{{ $navLinkClass(request()->routeIs('dashboard', 'amo-accounts.dashboard')) }}" href="{{ $layoutCurrentAmoAccount ? route('amo-accounts.dashboard', $layoutCurrentAmoAccount) : route('dashboard') }}">Dashboard</a>
                        <a class="{{ $navLinkClass(request()->routeIs('amo-accounts.index', 'amo-accounts.show', 'amo-accounts.edit')) }}" href="{{ route('amo-accounts.index') }}">Клиенты</a>
                        @if (auth()->user()->isAdmin())
                            <a class="{{ $navLinkClass(request()->routeIs('amo-oauth.external.*')) }}" href="{{ route('amo-oauth.external.index') }}">OAuth amoCRM</a>
                        @endif
                        @if ($layoutCurrentAmoAccount)
                            <a class="{{ $navLinkClass(request()->routeIs('amo-accounts.users')) }}" href="{{ route('amo-accounts.users', $layoutCurrentAmoAccount) }}">Users audit</a>
                            <a class="{{ $navLinkClass(request()->routeIs('amo-accounts.leads')) }}" href="{{ route('amo-accounts.leads', $layoutCurrentAmoAccount) }}">Сделки</a>
                            <a class="{{ $navLinkClass(request()->routeIs('amo-accounts.pipelines.*')) }}" href="{{ route('amo-accounts.pipelines.index', $layoutCurrentAmoAccount) }}">Воронки</a>
                            <a class="{{ $navLinkClass(request()->routeIs('amo-accounts.crm-audit.*')) }}" href="{{ route('amo-accounts.crm-audit.index', $layoutCurrentAmoAccount) }}">CRM-аудит</a>
                            <a class="{{ $navLinkClass(request()->routeIs('amo-accounts.integrations')) }}" href="{{ route('amo-accounts.integrations', $layoutCurrentAmoAccount) }}">Интеграции</a>
                            <a class="{{ $navLinkClass(request()->routeIs('amo-accounts.widgets')) }}" href="{{ route('amo-accounts.widgets', $layoutCurrentAmoAccount) }}">Dashboard-блоки</a>
                        @endif
                        <a class="{{ $navLinkClass(request()->routeIs('logs.api')) }}" href="{{ route('logs.api') }}">API-логи</a>
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
            @auth
                <nav class="mb-4 flex flex-wrap items-center gap-2 text-sm text-slate-500" aria-label="Хлебные крошки">
                    @foreach ($breadcrumbs as $index => $crumb)
                        @if ($index > 0)
                            <span>/</span>
                        @endif
                        @if ($crumb['url'] && $index < count($breadcrumbs) - 1)
                            <a class="text-blue-700 hover:text-blue-900" href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                        @else
                            <span class="{{ $index === count($breadcrumbs) - 1 ? 'font-medium text-slate-700' : '' }}">{{ $crumb['label'] }}</span>
                        @endif
                    @endforeach
                </nav>
            @endauth
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
