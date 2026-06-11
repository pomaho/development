import DashboardMetric from '../../Components/DashboardMetric';
import PlainActionForm from '../../Components/PlainActionForm';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
    account_id: number | null;
    is_active: boolean;
    auth_status: string | null;
    auth_type: string | null;
    last_successful_sync_at: string | null;
    settings: {
        company_name?: string;
        timezone?: string;
        currency?: string;
    };
};

type ApiLog = {
    id: number;
    created_at: string | null;
    method: string;
    status_code: number | null;
    url: string;
    error_message: string | null;
};

type Props = {
    account: Account;
    summary: {
        users_count: number;
        admins_count: number;
    };
    logs: ApiLog[];
    can: {
        sync: boolean;
        update: boolean;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        current_account: {
            dashboard: string;
            show: string;
            edit: string;
            test: string;
            sync: string;
            deactivate: string;
            users: string;
            roles: string;
            leads: string;
            pipelines: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        };
    };
};

export default function AmoAccountShow({ account, summary, logs, can, links }: Props) {
    const accountLinks = links.current_account;
    const settings = account.settings || {};
    const secondaryButtonClass = 'inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500';
    const primaryButtonClass = 'inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600';
    const quickLinkClass = 'inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500';

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-theme-sm font-medium text-brand-600">Client profile</p>
                    <h1 className="mt-1 text-2xl font-semibold text-gray-900">{account.name}</h1>
                    <div className="mt-1 text-theme-sm text-gray-500">{account.base_domain}</div>
                </div>
                <div className="flex flex-wrap gap-2">
                    {can.sync ? (
                        <>
                            <PlainActionForm action={accountLinks.test} buttonClassName={secondaryButtonClass} label="Проверить соединение" />
                            <PlainActionForm action={accountLinks.sync} buttonClassName={primaryButtonClass} label="Синхронизировать" />
                        </>
                    ) : null}
                    {can.update ? (
                        <>
                            <a className={secondaryButtonClass} href={accountLinks.edit}>Редактировать</a>
                            <PlainActionForm action={accountLinks.deactivate} buttonClassName={secondaryButtonClass} label="Деактивировать" />
                        </>
                    ) : null}
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <DashboardMetric label="Auth type" value={account.auth_type || '-'} />
                <DashboardMetric label="Auth status" value={account.auth_status || '-'} />
                <DashboardMetric label="Пользователи" value={summary.users_count} />
                <DashboardMetric label="Администраторы" value={summary.admins_count} />
            </div>

            {Object.keys(settings).length > 0 ? (
                <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <DashboardMetric label="Компания" value={settings.company_name || account.name} />
                    <DashboardMetric label="Часовой пояс" value={settings.timezone || '-'} />
                    <DashboardMetric label="Валюта" value={settings.currency || '-'} />
                    <DashboardMetric label="amoCRM ID" value={account.account_id || '-'} />
                </div>
            ) : null}

            <div className="mt-6 flex flex-wrap gap-3">
                <a className={quickLinkClass} href={accountLinks.dashboard}>Dashboard клиента</a>
                <a className={quickLinkClass} href={accountLinks.leads}>Сделки</a>
                <a className={quickLinkClass} href={accountLinks.users}>Пользователи</a>
                <a className={quickLinkClass} href={accountLinks.roles}>Роли</a>
                <a className={quickLinkClass} href={accountLinks.pipelines}>Воронки</a>
                <a className={quickLinkClass} href={accountLinks.crm_audit}>CRM-аудит</a>
                <a className={quickLinkClass} href={accountLinks.integrations}>Интеграции</a>
                <a className={quickLinkClass} href={accountLinks.widgets}>Dashboard-блоки</a>
            </div>

            <div className="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="border-b border-gray-200 px-5 py-4">
                    <h2 className="text-lg font-semibold text-gray-900">Последние API-логи</h2>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-theme-sm">
                        <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th className="px-5 py-3">Дата</th>
                                <th className="px-5 py-3">Метод</th>
                                <th className="px-5 py-3">Status</th>
                                <th className="px-5 py-3">URL</th>
                                <th className="px-5 py-3">Ошибка</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {logs.length > 0 ? logs.map((log) => (
                                <tr key={log.id}>
                                    <td className="px-5 py-3 text-gray-700">{log.created_at || '-'}</td>
                                    <td className="px-5 py-3 font-medium text-gray-900">{log.method}</td>
                                    <td className="px-5 py-3">
                                        <span className="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-700">
                                            {log.status_code || '-'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-gray-600">{log.url}</td>
                                    <td className="px-5 py-3 text-gray-600">{log.error_message || '-'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="px-5 py-6 text-gray-500" colSpan={5}>Логов пока нет.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
