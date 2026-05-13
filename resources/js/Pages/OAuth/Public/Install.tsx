import { useEffect, useRef } from 'react';

type Props = {
    connection: {
        state: string;
        redirect_uri: string;
        secrets_uri: string;
    };
    external: {
        logo_url: string | null;
        scopes: string[];
    };
};

export default function PublicInstall({ connection, external }: Props) {
    const buttonRef = useRef<HTMLDivElement>(null);
    const hasHttpsUrls = connection.redirect_uri.startsWith('https://') && connection.secrets_uri.startsWith('https://');

    useEffect(() => {
        if (! buttonRef.current) {
            return;
        }

        buttonRef.current.innerHTML = '';
        const script = document.createElement('script');
        script.className = 'amocrm_oauth';
        script.charset = 'utf-8';
        script.dataset.name = 'Sonic Expert';
        script.dataset.description = 'Интеграция Sonic Expert для подключения amoCRM.';
        script.dataset.redirect_uri = connection.redirect_uri;
        script.dataset.secrets_uri = connection.secrets_uri;
        script.dataset.scopes = external.scopes.join(',');
        script.dataset.title = 'Установить интеграцию';
        script.dataset.compact = 'false';
        script.dataset.className = 'amo-install-button';
        script.dataset.color = 'default';
        script.dataset.state = connection.state;
        script.dataset.mode = 'popup';
        if (external.logo_url) {
            script.dataset.logo = external.logo_url;
        }
        script.src = 'https://www.amocrm.ru/auth/button.min.js';
        buttonRef.current.appendChild(script);
    }, [connection, external]);

    return (
        <main className="min-h-screen overflow-hidden bg-[radial-gradient(circle_at_top_left,#d8fff5_0,#eef3f7_34%,#f7fafc_70%)]">
            <div className="mx-auto grid min-h-screen max-w-6xl items-center gap-10 px-5 py-10 lg:grid-cols-[1fr_440px]">
                <section className="max-w-2xl">
                    <div className="mb-8 inline-flex items-center gap-2 rounded-full border border-[#cde5df] bg-white/75 px-4 py-2 text-sm font-medium text-[#0b7f75] shadow-sm">
                        <span className="h-2 w-2 rounded-full bg-[#00c2a8]" />
                        Интеграция Sonic Expert
                    </div>

                    <h1 className="text-4xl font-semibold leading-tight tracking-normal text-[#102033] md:text-6xl">
                        Подключите amoCRM к Sonic Expert
                    </h1>

                    <p className="mt-6 max-w-xl text-lg leading-8 text-[#516173]">
                        Интеграция Sonic Expert поможет безопасно подключить ваш аккаунт amoCRM и передать необходимые данные для настройки сервисов, аналитики и рабочих процессов.
                    </p>

                    <div className="mt-8 grid gap-3 text-sm text-[#516173] sm:grid-cols-3">
                        {['Нажмите кнопку установки', 'Подтвердите доступ в amoCRM', 'Аккаунт появится в системе'].map((text, index) => (
                            <div className="rounded-lg border border-white/80 bg-white/70 p-4 shadow-sm" key={text}>
                                <div className="text-2xl font-semibold text-[#102033]">{index + 1}</div>
                                <div className="mt-1">{text}</div>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="rounded-2xl border border-white/80 bg-white p-6 shadow-2xl shadow-[#0d4250]/10">
                    <div className="mb-6 rounded-xl bg-[#102033] p-5 text-white">
                        <div className="text-sm text-[#9fe7dd]">Установка интеграции Sonic Expert</div>
                        <div className="mt-2 text-2xl font-semibold">Sonic Expert</div>
                        <p className="mt-3 text-sm leading-6 text-[#d9e8ee]">Нажмите кнопку ниже и подтвердите установку в вашем аккаунте amoCRM.</p>
                    </div>

                    {! hasHttpsUrls ? (
                        <div className="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Сейчас страница открыта в локальном режиме. Для реальной установки используйте публичную HTTPS-ссылку Sonic Expert.
                        </div>
                    ) : null}

                    <div className="amo-install-button-wrap" ref={buttonRef} />
                </section>
            </div>
        </main>
    );
}
