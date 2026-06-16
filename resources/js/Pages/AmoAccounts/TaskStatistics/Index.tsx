import { RefreshCw } from 'lucide-react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Row = {
    responsible_user_id: number;
    responsible_name: string | null;
    completed_count: number;
    completed_overdue_count: number;
    open_count: number;
    open_overdue_count: number;
    overdue_count: number;
    total_count: number;
    overdue_rate: number;
};

type Run = {
    id: number;
    status: string;
    period_from: string | null;
    period_to: string | null;
    completed_found: number;
    completed_synced: number;
    completion_events_found: number;
    completion_events_synced: number;
    open_found: number;
    open_synced: number;
    error_message: string | null;
    created_at: string | null;
    finished_at: string | null;
};

type Props = {
    account: Account;
    rows: Row[];
    runs: Run[];
    filters: {
        from: string;
        to: string;
    };
    can: {
        sync: boolean;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        sync: string;
        export: string;
        reset: string;
        current_account: {
            dashboard: string;
            show: string;
            users: string;
            roles: string;
            leads: string;
            pipelines: string;
            catalogs: string;
            responsibility_redistribution: string;
            task_statistics: string;
            events_sync: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        };
    };
};

const userLabel = (row: Row) => row.responsible_name ? `${row.responsible_name} (${row.responsible_user_id})` : `ID ${row.responsible_user_id}`;

const statusLabel = (status: string) => {
    if (status === 'pending') {
        return 'в очереди';
    }

    if (status === 'running') {
        return 'выполняется';
    }

    if (status === 'completed') {
        return 'завершено';
    }

    if (status === 'failed') {
        return 'ошибка';
    }

    return status;
};

export default function TaskStatisticsIndex({ account, rows, runs, filters, can, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Статистика задач' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold">Статистика задач</h1>
                    <div className="mt-1 text-theme-sm text-gray-500">{account.name} · {account.base_domain}</div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <a className="rounded border border-gray-200 px-3 py-2 text-sm hover:border-brand-300 hover:text-brand-600" href={links.export}>
                        Экспорт
                    </a>
                </div>
            </div>

            <form className="mb-4 grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm md:grid-cols-[1fr_1fr_auto_auto]" method="get">
                <label className="block">
                    <span>Показать статистику с</span>
                    <input className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" defaultValue={filters.from} name="from" type="date" />
                </label>
                <label className="block">
                    <span>Показать статистику по</span>
                    <input className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" defaultValue={filters.to} name="to" type="date" />
                </label>
                <div className="flex items-end">
                    <button className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" type="submit">Фильтр</button>
                </div>
                <div className="flex items-end">
                    <a className="rounded border border-gray-200 px-3 py-2 hover:border-brand-300" href={links.reset}>Сбросить</a>
                </div>
            </form>

            <div className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Отчет считает задачи по событиям `task_completed`: пользователь определяется по полю `created_by` события, период - по времени закрытия задачи.
            </div>

            <section className="mb-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="font-semibold">Синхронизация задач</h2>
                        <div className="mt-1 text-theme-sm text-gray-500">Выберите период, за который нужно вычитать выполненные задачи. Открытые и просроченные задачи обновляются по текущему состоянию.</div>
                    </div>
                </div>
                <form action={links.sync} className="mt-4 grid gap-3 text-sm md:grid-cols-[1fr_1fr_auto]" method="post">
                    <input name="_token" type="hidden" value={csrf} />
                    <label className="block">
                        <span>Синхронизировать с</span>
                        <input className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" defaultValue={filters.from} name="from" type="date" />
                    </label>
                    <label className="block">
                        <span>Синхронизировать по</span>
                        <input className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" defaultValue={filters.to} name="to" type="date" />
                    </label>
                    <div className="flex items-end">
                        <button
                            className="inline-flex items-center gap-2 inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-50"
                            disabled={! can.sync}
                            type="submit"
                        >
                            <RefreshCw size={16} />
                            Запустить
                        </button>
                    </div>
                </form>
            </section>

            <section className="mb-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <h2 className="font-semibold">Последние синхронизации</h2>
                <div className="mt-3 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-gray-500">
                            <tr>
                                <th className="py-2">Дата</th>
                                <th>Период</th>
                                <th>Статус</th>
                                <th>Выполненные</th>
                                <th>События завершения</th>
                                <th>Открытые</th>
                                <th>Ошибка</th>
                            </tr>
                        </thead>
                        <tbody>
                            {runs.length > 0 ? runs.map((run) => (
                                <tr className="align-top border-t border-gray-100" key={run.id}>
                                    <td className="py-3">{run.created_at || '-'}</td>
                                    <td>{run.period_from || '-'} - {run.period_to || '-'}</td>
                                    <td>{statusLabel(run.status)}</td>
                                    <td>нашел {run.completed_found} / синхронизировал {run.completed_synced}</td>
                                    <td>нашел {run.completion_events_found} / синхронизировал {run.completion_events_synced}</td>
                                    <td>нашел {run.open_found} / синхронизировал {run.open_synced}</td>
                                    <td className="max-w-md text-red-700">{run.error_message || '-'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="py-4 text-gray-500" colSpan={7}>Синхронизаций пока нет.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-gray-500">
                            <tr>
                                <th className="py-2">Пользователь</th>
                                <th>Закрыто задач за период</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length > 0 ? rows.map((row) => (
                                <tr className="border-t border-gray-100" key={row.responsible_user_id}>
                                    <td className="py-3 font-medium">{userLabel(row)}</td>
                                    <td>{row.completed_count}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="py-4 text-gray-500" colSpan={2}>Данных пока нет. Запустите синхронизацию задач.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
