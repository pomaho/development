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
    const actionLinkClass = 'inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500';

    return (
        <AuthenticatedLayout title="amo Integrator Hub" breadcrumbs={breadcrumbs} links={links}>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-theme-sm font-medium text-brand-600">Operations overview</p>
                    <h1 className="mt-1 text-2xl font-semibold text-gray-900">
                        {currentAccount ? `Dashboard: ${currentAccount.name}` : 'Dashboard: все аккаунты'}
                    </h1>
                    {currentAccount ? <div className="mt-1 text-theme-sm text-gray-500">{currentAccount.base_domain}</div> : null}
                </div>
            </div>

            {currentAccount && accountLinks ? (
                <div className="mb-6 flex flex-wrap gap-3">
                    <a className={actionLinkClass} href={accountLinks.show}>Карточка клиента</a>
                    <a className={actionLinkClass} href={accountLinks.users}>Пользователи</a>
                    <a className={actionLinkClass} href={accountLinks.roles}>Роли</a>
                    <a className={actionLinkClass} href={accountLinks.pipelines}>Воронки</a>
                    <a className={actionLinkClass} href={accountLinks.crm_audit}>CRM-аудит</a>
                    <a className={actionLinkClass} href={accountLinks.integrations}>Интеграции</a>
                    <a className={actionLinkClass} href={accountLinks.widgets}>Dashboard-блоки</a>
                </div>
            ) : null}

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <DashboardMetric label="Подключено аккаунтов" value={summary.accounts_count} />
                <DashboardMetric label="Активные аккаунты" value={summary.active_accounts_count} />
                <DashboardMetric label="Последняя синхронизация" value={summary.last_sync || 'нет'} />
                <DashboardMetric label="Пользователи" value={summary.users_count} />
                <DashboardMetric label="Администраторы" value={summary.admins_count} />
                <DashboardMetric label="Dashboard-блоки" value={widgets.length} />
            </div>

            <div className="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="border-b border-gray-200 px-5 py-4">
                    <h2 className="text-lg font-semibold text-gray-900">Последние ошибки API</h2>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-theme-sm">
                        <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th className="px-5 py-3">Дата</th>
                                <th className="px-5 py-3">Аккаунт</th>
                                <th className="px-5 py-3">Status</th>
                                <th className="px-5 py-3">Ошибка</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {recentErrors.length > 0 ? recentErrors.map((log) => (
                                <tr key={log.id}>
                                    <td className="px-5 py-3 text-gray-700">{log.created_at || '-'}</td>
                                    <td className="px-5 py-3 font-medium text-gray-900">{log.account_name || '-'}</td>
                                    <td className="px-5 py-3">
                                        <span className="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-theme-xs font-medium text-red-600">
                                            {log.status_code || '-'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-gray-600">{log.error_message || '-'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="px-5 py-6 text-gray-500" colSpan={4}>Ошибок пока нет.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
