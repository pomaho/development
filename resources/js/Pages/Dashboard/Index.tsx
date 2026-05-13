import { Link } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import DashboardMetric from '../../Components/DashboardMetric';
import type { AmoAccountSummary } from '../../types';

type ApiError = {
    id: number;
    created_at: string | null;
    account_name: string | null;
    status_code: number | null;
    error_message: string | null;
};

type DashboardWidget = {
    id: number;
    name: string;
};

type Props = {
    accounts: AmoAccountSummary[];
    currentAccount: AmoAccountSummary | null;
    selectedAccountId: number | null;
    widgets: DashboardWidget[];
    summary: {
        accounts_count: number;
        active_accounts_count: number;
        last_sync: string | null;
        users_count: number;
        admins_count: number;
    };
    recentErrors: ApiError[];
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        current_account: {
            dashboard: string;
            show: string;
            users: string;
            roles: string;
            leads: string;
            pipelines: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        } | null;
    };
};

export default function DashboardIndex({ currentAccount, widgets, summary, recentErrors, links }: Props) {
    const breadcrumbs = currentAccount
        ? [
            { label: 'Dashboard', href: links.dashboard },
            { label: currentAccount.name, href: links.current_account?.show },
            { label: 'Dashboard клиента' },
        ]
        : [{ label: 'Dashboard' }];

    const accountLinks = links.current_account;

    return (
        <AuthenticatedLayout title="amo Integrator Hub" breadcrumbs={breadcrumbs} links={links}>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">
                        {currentAccount ? `Dashboard: ${currentAccount.name}` : 'Dashboard: все аккаунты'}
                    </h1>
                    {currentAccount ? <div className="text-sm text-slate-500">{currentAccount.base_domain}</div> : null}
                </div>
            </div>

            {currentAccount && accountLinks ? (
                <div className="mb-6 flex flex-wrap gap-3 text-sm">
                    <Link className="rounded border border-slate-300 bg-white px-3 py-2 hover:border-blue-400" href={accountLinks.show}>Карточка клиента</Link>
                    <Link className="rounded border border-slate-300 bg-white px-3 py-2 hover:border-blue-400" href={accountLinks.users}>Пользователи</Link>
                    <Link className="rounded border border-slate-300 bg-white px-3 py-2 hover:border-blue-400" href={accountLinks.roles}>Роли</Link>
                    <Link className="rounded border border-slate-300 bg-white px-3 py-2 hover:border-blue-400" href={accountLinks.pipelines}>Воронки</Link>
                    <Link className="rounded border border-slate-300 bg-white px-3 py-2 hover:border-blue-400" href={accountLinks.crm_audit}>CRM-аудит</Link>
                    <Link className="rounded border border-slate-300 bg-white px-3 py-2 hover:border-blue-400" href={accountLinks.integrations}>Интеграции</Link>
                    <Link className="rounded border border-slate-300 bg-white px-3 py-2 hover:border-blue-400" href={accountLinks.widgets}>Dashboard-блоки</Link>
                </div>
            ) : null}

            <div className="grid gap-4 md:grid-cols-3">
                <DashboardMetric label="Подключено аккаунтов" value={summary.accounts_count} />
                <DashboardMetric label="Активные аккаунты" value={summary.active_accounts_count} />
                <DashboardMetric label="Последняя синхронизация" value={summary.last_sync || 'нет'} />
                <DashboardMetric label="Пользователи" value={summary.users_count} />
                <DashboardMetric label="Администраторы" value={summary.admins_count} />
                <DashboardMetric label="Dashboard-блоки" value={widgets.length} />
            </div>

            <div className="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 className="mb-3 font-semibold">Последние ошибки API</h2>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-2">Дата</th>
                                <th>Аккаунт</th>
                                <th>Status</th>
                                <th>Ошибка</th>
                            </tr>
                        </thead>
                        <tbody>
                            {recentErrors.length > 0 ? recentErrors.map((log) => (
                                <tr className="border-t border-slate-100" key={log.id}>
                                    <td className="py-2">{log.created_at || '-'}</td>
                                    <td>{log.account_name || '-'}</td>
                                    <td>{log.status_code || '-'}</td>
                                    <td>{log.error_message || '-'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="py-4 text-slate-500" colSpan={4}>Ошибок пока нет.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
