@extends('layouts.app')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Подключение amoCRM через OAuth</h1>
        <p class="mt-1 text-sm text-slate-600">Передайте клиенту эту страницу или откройте ее вместе с ним.</p>
    </div>
    <a href="{{ route('amo-oauth.external.index') }}" class="text-sm text-blue-700">Все OAuth-подключения</a>
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <section class="rounded border border-slate-200 bg-white p-4 lg:col-span-2">
        <h2 class="mb-3 text-lg font-semibold">Кнопка авторизации</h2>
        @if (! str_starts_with((string) $connection->redirect_uri, 'https://') || ! str_starts_with((string) $connection->secrets_uri, 'https://'))
            <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                amoCRM принимает только публичные HTTPS URL для Redirect URI и Secrets URI. Для локальной разработки используйте HTTPS-туннель и задайте его в APP_URL или AMO_EXTERNAL_REDIRECT_URI / AMO_EXTERNAL_SECRETS_URI.
            </div>
        @endif

        <div class="mb-4">
            <script
                class="amocrm_oauth"
                charset="utf-8"
                data-name="{{ $external['name'] }}"
                data-description="{{ $external['description'] }}"
                data-redirect_uri="{{ $connection->redirect_uri }}"
                data-secrets_uri="{{ $connection->secrets_uri }}"
                @if ($external['logo_url']) data-logo="{{ $external['logo_url'] }}" @endif
                data-scopes="{{ implode(',', $external['scopes']) }}"
                data-title="Подключить amoCRM"
                data-compact="false"
                data-class-name="amo-external-oauth-button"
                data-color="default"
                data-state="{{ $connection->state }}"
                data-mode="popup"
                src="https://www.amocrm.ru/auth/button.min.js"
            ></script>
        </div>

        <dl class="grid gap-3 text-sm">
            <div>
                <dt class="text-slate-500">Redirect URI</dt>
                <dd class="break-all font-mono">{{ $connection->redirect_uri }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Secrets URI</dt>
                <dd class="break-all font-mono">{{ $connection->secrets_uri }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">State</dt>
                <dd class="break-all font-mono">{{ $connection->state }}</dd>
            </div>
        </dl>
    </section>

    <section class="rounded border border-slate-200 bg-white p-4">
        <h2 class="mb-3 text-lg font-semibold">Статус</h2>
        <dl class="space-y-3 text-sm">
            <div>
                <dt class="text-slate-500">Текущий статус</dt>
                <dd class="font-medium">{{ $connection->status }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Домен</dt>
                <dd>{{ $connection->base_domain ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Действует до</dt>
                <dd>{{ $connection->expires_at ?? '-' }}</dd>
            </div>
            @if ($connection->account)
                <div>
                    <dt class="text-slate-500">Аккаунт в сервисе</dt>
                    <dd><a class="text-blue-700" href="{{ route('amo-accounts.show', $connection->account) }}">{{ $connection->account->name }}</a></dd>
                </div>
            @endif
            @if ($connection->error_message)
                <div>
                    <dt class="text-slate-500">Ошибка</dt>
                    <dd class="text-red-700">{{ $connection->error_message }}</dd>
                </div>
            @endif
        </dl>
    </section>
</div>
@endsection
