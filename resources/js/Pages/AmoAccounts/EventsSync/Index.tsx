import { Database } from 'lucide-react';
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
                    <div className="mt-1 text-theme-sm text-gray-500">{account.name} · {account.base_domain}</div>
                </div>
            </div>

            <section className="mb-6 grid gap-3 md:grid-cols-4">
                <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                    <div className="text-theme-sm text-gray-500">Событий в базе</div>
                    <div className="mt-1 text-3xl font-semibold">{coverage.events_count}</div>
                </div>
                <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                    <div className="text-theme-sm text-gray-500">Самое раннее событие</div>
                    <div className="mt-1 text-lg font-semibold">{coverage.period_from || '-'}</div>
                </div>
                <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                    <div className="text-theme-sm text-gray-500">Самое позднее событие</div>
                    <div className="mt-1 text-lg font-semibold">{coverage.period_to || '-'}</div>
                </div>
                <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                    <div className="text-theme-sm text-gray-500">Incremental cursor</div>
                    <div className="mt-1 text-lg font-semibold">{coverage.cursor || '-'}</div>
                </div>
            </section>

            <section className="mb-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="font-semibold">Настройки отчетов по событиям</h2>
                        <div className="mt-1 text-theme-sm text-gray-500">
                            Если список групп пустой, запустите синхронизацию пользователей или введите group_id вручную.
                        </div>
                    </div>
                </div>
                <form action={links.events_sync_settings} className="mt-4 grid gap-3 text-sm md:grid-cols-[1fr_220px_auto]" method="post">
                    <input name="_token" type="hidden" value={csrf} />
                    <label className="block">
                        <span>Отдел для отчета “Авито рекрутинг”</span>
                        <select className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" defaultValue={reportSettings.avito_recruiting_group_id || ''} name="avito_recruiting_group_id">
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
                            className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10"
                            min="1"
                            name="avito_recruiting_group_id_manual"
                            placeholder="например 12345"
                            type="number"
                        />
                    </label>
                    <div className="flex items-end">
                        <button
                            className="rounded bg-gray-900 px-4 py-2 text-white hover:bg-gray-800 disabled:opacity-50"
                            disabled={! can.sync}
                            type="submit"
                        >
                            Сохранить
                        </button>
                    </div>
                </form>
            </section>

            <section className="mb-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <div className="flex items-center gap-2">
                    <Database className="h-5 w-5 text-brand-600" />
                    <h2 className="font-semibold">Покрытие загруженных событий</h2>
                </div>
                <div className="mt-2 text-theme-sm text-gray-600">
                    Отчеты строятся по локальной базе. Hourly sync догружает новые события от курсора с overlap, а ночной safety refresh обновляет последние 3 дня.
                </div>
                <div className="mt-1 text-theme-sm text-gray-500">Последняя запись событий в БД: {coverage.last_synced_at || '-'}</div>
            </section>

            <section className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <h2 className="font-semibold">Последние запуски синхронизации</h2>
                <div className="mt-3 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-gray-500">
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
                                <tr className="align-top border-t border-gray-100" key={run.id}>
                                    <td className="py-3">{run.created_at || '-'}</td>
                                    <td>{run.period_from || '-'} - {run.period_to || '-'}</td>
                                    <td>{statusLabel(run.status)}</td>
                                    <td>{run.finished_at || '-'}</td>
                                    <td className="max-w-md text-red-700">{run.error_message || '-'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="py-4 text-gray-500" colSpan={5}>Синхронизаций пока нет.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
