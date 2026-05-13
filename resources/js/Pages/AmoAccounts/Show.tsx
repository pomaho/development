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
                    <h1 className="text-2xl font-semibold">{account.name}</h1>
                    <div className="text-sm text-slate-500">{account.base_domain}</div>
                </div>
                <div className="flex flex-wrap gap-2">
                    {can.sync ? (
                        <>
                            <PlainActionForm action={accountLinks.test} buttonClassName="rounded border border-slate-300 px-3 py-2 text-sm hover:border-blue-400" label="Проверить соединение" />
                            <PlainActionForm action={accountLinks.sync} buttonClassName="rounded bg-blue-700 px-3 py-2 text-sm text-white hover:bg-blue-800" label="Синхронизировать" />
                        </>
                    ) : null}
                    {can.update ? (
                        <>
                            <a className="rounded border border-slate-300 px-3 py-2 text-sm hover:border-blue-400" href={accountLinks.edit}>Редактировать</a>
                            <PlainActionForm action={accountLinks.deactivate} buttonClassName="rounded border border-slate-300 px-3 py-2 text-sm hover:border-blue-400" label="Деактивировать" />
                        </>
                    ) : null}
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-4">
                <DashboardMetric label="Auth type" value={account.auth_type || '-'} />
                <DashboardMetric label="Auth status" value={account.auth_status || '-'} />
                <DashboardMetric label="Пользователи" value={summary.users_count} />
                <DashboardMetric label="Администраторы" value={summary.admins_count} />
            </div>

            {Object.keys(settings).length > 0 ? (
                <div className="mt-6 grid gap-4 md:grid-cols-4">
                    <DashboardMetric label="Компания" value={settings.company_name || account.name} />
                    <DashboardMetric label="Часовой пояс" value={settings.timezone || '-'} />
                    <DashboardMetric label="Валюта" value={settings.currency || '-'} />
                    <DashboardMetric label="amoCRM ID" value={account.account_id || '-'} />
                </div>
            ) : null}

            <div className="mt-6 flex flex-wrap gap-3 text-sm">
                <a className="text-blue-700 hover:text-blue-900" href={accountLinks.dashboard}>Dashboard клиента</a>
                <a className="text-blue-700 hover:text-blue-900" href={accountLinks.leads}>Сделки</a>
                <a className="text-blue-700 hover:text-blue-900" href={accountLinks.users}>Пользователи</a>
                <a className="text-blue-700 hover:text-blue-900" href={accountLinks.roles}>Роли</a>
                <a className="text-blue-700 hover:text-blue-900" href={accountLinks.pipelines}>Воронки</a>
                <a className="text-blue-700 hover:text-blue-900" href={accountLinks.crm_audit}>CRM-аудит</a>
                <a className="text-blue-700 hover:text-blue-900" href={accountLinks.integrations}>Интеграции</a>
                <a className="text-blue-700 hover:text-blue-900" href={accountLinks.widgets}>Dashboard-блоки</a>
            </div>

            <div className="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 className="mb-3 font-semibold">Последние API-логи</h2>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-2">Дата</th>
                                <th>Метод</th>
                                <th>Status</th>
                                <th>URL</th>
                                <th>Ошибка</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.length > 0 ? logs.map((log) => (
                                <tr className="border-t border-slate-100" key={log.id}>
                                    <td className="py-2">{log.created_at || '-'}</td>
                                    <td>{log.method}</td>
                                    <td>{log.status_code || '-'}</td>
                                    <td>{log.url}</td>
                                    <td>{log.error_message || '-'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="py-4 text-slate-500" colSpan={5}>Логов пока нет.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
