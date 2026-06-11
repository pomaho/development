import { useEffect, useRef } from 'react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Connection = {
    id: number;
    state: string;
    name: string | null;
    base_domain: string | null;
    redirect_uri: string;
    secrets_uri: string;
    scopes: string[];
    status: string;
    error_message: string | null;
    expires_at: string | null;
    account: {
        id: number;
        name: string;
        url: string;
    } | null;
};

type Props = {
    connection: Connection;
    external: {
        name: string;
        description: string;
        logo_url: string | null;
        scopes: string[];
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        current_account: null;
    };
};

export default function OAuthExternalShow({ connection, external, links }: Props) {
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
        script.dataset.name = external.name;
        script.dataset.description = external.description;
        script.dataset.redirect_uri = connection.redirect_uri;
        script.dataset.secrets_uri = connection.secrets_uri;
        script.dataset.scopes = (external.scopes || connection.scopes || []).join(',');
        script.dataset.title = 'Подключить amoCRM';
        script.dataset.compact = 'false';
        script.dataset.className = 'amo-external-oauth-button';
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
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'OAuth amoCRM', href: links.oauth },
                { label: connection.name || `Подключение ${connection.id}` },
            ]}
            links={links}
        >
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Подключение amoCRM через OAuth</h1>
                    <p className="mt-1 text-theme-sm text-gray-600">Передайте клиенту эту страницу или откройте ее вместе с ним.</p>
                </div>
                <a className="text-sm text-brand-600" href={links.oauth}>Все OAuth-подключения</a>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm lg:col-span-2">
                    <h2 className="mb-3 text-lg font-semibold">Кнопка авторизации</h2>
                    {! hasHttpsUrls ? (
                        <div className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            amoCRM принимает только публичные HTTPS URL для Redirect URI и Secrets URI. Для локальной разработки используйте HTTPS-туннель и задайте его в APP_URL или AMO_EXTERNAL_REDIRECT_URI / AMO_EXTERNAL_SECRETS_URI.
                        </div>
                    ) : null}

                    <div className="mb-4" ref={buttonRef} />

                    <dl className="grid gap-3 text-sm">
                        <div>
                            <dt className="text-gray-500">Redirect URI</dt>
                            <dd className="break-all font-mono">{connection.redirect_uri}</dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">Secrets URI</dt>
                            <dd className="break-all font-mono">{connection.secrets_uri}</dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">State</dt>
                            <dd className="break-all font-mono">{connection.state}</dd>
                        </div>
                    </dl>
                </section>

                <section className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                    <h2 className="mb-3 text-lg font-semibold">Статус</h2>
                    <dl className="space-y-3 text-sm">
                        <div>
                            <dt className="text-gray-500">Текущий статус</dt>
                            <dd className="font-medium">{connection.status}</dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">Домен</dt>
                            <dd>{connection.base_domain || '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">Действует до</dt>
                            <dd>{connection.expires_at || '-'}</dd>
                        </div>
                        {connection.account ? (
                            <div>
                                <dt className="text-gray-500">Аккаунт в сервисе</dt>
                                <dd><a className="text-brand-600" href={connection.account.url}>{connection.account.name}</a></dd>
                            </div>
                        ) : null}
                        {connection.error_message ? (
                            <div>
                                <dt className="text-gray-500">Ошибка</dt>
                                <dd className="text-red-700">{connection.error_message}</dd>
                            </div>
                        ) : null}
                    </dl>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
