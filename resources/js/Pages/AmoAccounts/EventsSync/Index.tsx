import { Database, RefreshCw } from 'lucide-react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Coverage = {
    events_count: number;
    period_from: string | null;
    period_to: string | null;
    last_synced_at: string | null;
    cursor: string | null;
};

type Run = {
    id: number;
    status: string;
    period_from: string | null;
    period_to: string | null;
    created_at: string | null;
    finished_at: string | null;
    error_message: string | null;
};

type Group = {
    id: number;
    name: string;
    users_count: number;
};

type Props = {
    account: Account;
    coverage: Coverage;
    reportSettings: {
        avito_recruiting_group_id: number | string | null;
    };
    groups: Group[];
    runs: Run[];
    can: {
        sync: boolean;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        events_sync_start: string;
        events_sync_settings: string;
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

const statusLabel = (status: string) => {
    if (status === 'pending') return 'в очереди';
    if (status === 'running') return 'выполняется';
    if (status === 'completed') return 'завершено';
    if (status === 'failed') return 'ошибка';

    return status;
};

export default function EventsSyncIndex({ account, coverage, reportSettings, groups, runs, can, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'События amoCRM' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold">События amoCRM</h1>
                    <div className="mt-1 text-sm text-slate-500">{account.name} · {account.base_domain}</div>
                </div>
            </div>

            <section className="mb-6 grid gap-3 md:grid-cols-4">
                <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="text-sm text-slate-500">Событий в базе</div>
                    <div className="mt-1 text-3xl font-semibold">{coverage.events_count}</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="text-sm text-slate-500">Самое раннее событие</div>
                    <div className="mt-1 text-lg font-semibold">{coverage.period_from || '-'}</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="text-sm text-slate-500">Самое позднее событие</div>
                    <div className="mt-1 text-lg font-semibold">{coverage.period_to || '-'}</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="text-sm text-slate-500">Incremental cursor</div>
                    <div className="mt-1 text-lg font-semibold">{coverage.cursor || '-'}</div>
                </div>
            </section>

            <section className="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="font-semibold">Настройки отчетов по событиям</h2>
                        <div className="mt-1 text-sm text-slate-500">
                            Если список групп пустой, запустите синхронизацию пользователей или введите group_id вручную.
                        </div>
                    </div>
                </div>
                <form action={links.events_sync_settings} className="mt-4 grid gap-3 text-sm md:grid-cols-[1fr_220px_auto]" method="post">
                    <input name="_token" type="hidden" value={csrf} />
                    <label className="block">
                        <span>Отдел для отчета “Авито рекрутинг”</span>
                        <select className="mt-1 w-full rounded border-slate-300" defaultValue={reportSettings.avito_recruiting_group_id || ''} name="avito_recruiting_group_id">
                            <option value="">Автоопределение по названию группы</option>
                            {groups.map((group) => (
                                <option key={group.id} value={group.id}>
                                    {group.name} · ID {group.id} · пользователей {group.users_count}
                                </option>
                            ))}
                        </select>
                        {groups.length === 0 && (
                            <span className="mt-1 block text-xs text-amber-700">Группы пока не найдены в snapshots пользователей.</span>
                        )}
                    </label>
                    <label className="block">
                        <span>group_id вручную</span>
                        <input
                            className="mt-1 w-full rounded border-slate-300"
                            min="1"
                            name="avito_recruiting_group_id_manual"
                            placeholder="например 12345"
                            type="number"
                        />
                    </label>
                    <div className="flex items-end">
                        <button
                            className="rounded bg-slate-900 px-4 py-2 text-white hover:bg-slate-800 disabled:opacity-50"
                            disabled={! can.sync}
                            type="submit"
                        >
                            Сохранить
                        </button>
                    </div>
                </form>
            </section>

            <section className="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <Database className="h-5 w-5 text-blue-700" />
                            <h2 className="font-semibold">Покрытие загруженных событий</h2>
                        </div>
                        <div className="mt-2 text-sm text-slate-600">
                            Отчеты строятся по локальной базе. Hourly sync догружает новые события от курсора с overlap, а ночной safety refresh обновляет последние 3 дня.
                        </div>
                        <div className="mt-1 text-sm text-slate-500">Последняя запись событий в БД: {coverage.last_synced_at || '-'}</div>
                    </div>

                    <form action={links.events_sync_start} method="post">
                        <input name="_token" type="hidden" value={csrf} />
                        <button
                            className="inline-flex items-center gap-2 rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800 disabled:opacity-50"
                            disabled={! can.sync}
                            type="submit"
                        >
                            <RefreshCw size={16} />
                            Синхронизировать 45 дней
                        </button>
                    </form>
                </div>
            </section>

            <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 className="font-semibold">Последние запуски синхронизации</h2>
                <div className="mt-3 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-2">Дата</th>
                                <th>Период</th>
                                <th>Статус</th>
                                <th>Завершено</th>
                                <th>Ошибка</th>
                            </tr>
                        </thead>
                        <tbody>
                            {runs.length > 0 ? runs.map((run) => (
                                <tr className="align-top border-t border-slate-100" key={run.id}>
                                    <td className="py-3">{run.created_at || '-'}</td>
                                    <td>{run.period_from || '-'} - {run.period_to || '-'}</td>
                                    <td>{statusLabel(run.status)}</td>
                                    <td>{run.finished_at || '-'}</td>
                                    <td className="max-w-md text-red-700">{run.error_message || '-'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="py-4 text-slate-500" colSpan={5}>Синхронизаций пока нет.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
