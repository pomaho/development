@extends('layouts.public')

@section('content')
<main class="min-h-screen overflow-hidden bg-[radial-gradient(circle_at_top_left,#d8fff5_0,#eef3f7_34%,#f7fafc_70%)]">
    <div class="mx-auto grid min-h-screen max-w-6xl items-center gap-10 px-5 py-10 lg:grid-cols-[1fr_440px]">
        <section class="max-w-2xl">
            <div class="mb-8 inline-flex items-center gap-2 rounded-full border border-[#cde5df] bg-white/75 px-4 py-2 text-sm font-medium text-[#0b7f75] shadow-sm">
                <span class="h-2 w-2 rounded-full bg-[#00c2a8]"></span>
                Интеграция Sonic Expert
            </div>

            <h1 class="text-4xl font-semibold leading-tight tracking-normal text-[#102033] md:text-6xl">
                Подключите amoCRM к Sonic Expert
            </h1>

            <p class="mt-6 max-w-xl text-lg leading-8 text-[#516173]">
                Интеграция Sonic Expert поможет безопасно подключить ваш аккаунт amoCRM и передать необходимые данные для настройки сервисов, аналитики и рабочих процессов.
            </p>

            <div class="mt-8 grid gap-3 text-sm text-[#516173] sm:grid-cols-3">
                <div class="rounded-lg border border-white/80 bg-white/70 p-4 shadow-sm">
                    <div class="text-2xl font-semibold text-[#102033]">1</div>
                    <div class="mt-1">Нажмите кнопку установки</div>
                </div>
                <div class="rounded-lg border border-white/80 bg-white/70 p-4 shadow-sm">
                    <div class="text-2xl font-semibold text-[#102033]">2</div>
                    <div class="mt-1">Подтвердите доступ в amoCRM</div>
                </div>
                <div class="rounded-lg border border-white/80 bg-white/70 p-4 shadow-sm">
                    <div class="text-2xl font-semibold text-[#102033]">3</div>
                    <div class="mt-1">Аккаунт появится в системе</div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-white/80 bg-white p-6 shadow-2xl shadow-[#0d4250]/10">
            <div class="mb-6 rounded-xl bg-[#102033] p-5 text-white">
                <div class="text-sm text-[#9fe7dd]">Установка интеграции Sonic Expert</div>
                <div class="mt-2 text-2xl font-semibold">Sonic Expert</div>
                <p class="mt-3 text-sm leading-6 text-[#d9e8ee]">Нажмите кнопку ниже и подтвердите установку в вашем аккаунте amoCRM.</p>
            </div>

            @if (! str_starts_with((string) $connection->redirect_uri, 'https://') || ! str_starts_with((string) $connection->secrets_uri, 'https://'))
                <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Сейчас страница открыта в локальном режиме. Для реальной установки используйте публичную HTTPS-ссылку Sonic Expert.
                </div>
            @endif

            <div class="amo-install-button-wrap">
                <script
                    class="amocrm_oauth"
                    charset="utf-8"
                    data-name="Sonic Expert"
                    data-description="Интеграция Sonic Expert для подключения amoCRM."
                    data-redirect_uri="{{ $connection->redirect_uri }}"
                    data-secrets_uri="{{ $connection->secrets_uri }}"
                    @if ($external['logo_url']) data-logo="{{ $external['logo_url'] }}" @endif
                    data-scopes="{{ implode(',', $external['scopes']) }}"
                    data-title="Установить интеграцию"
                    data-compact="false"
                    data-class-name="amo-install-button"
                    data-color="default"
                    data-state="{{ $connection->state }}"
                    data-mode="popup"
                    src="https://www.amocrm.ru/auth/button.min.js"
                ></script>
            </div>
        </section>
    </div>
</main>
@endsection
