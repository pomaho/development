import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Pipeline = {
    id: number | null;
    name: string;
    is_main: boolean;
    is_unsorted_on: boolean;
    is_archive: boolean;
    statuses: Array<{
        id: number | null;
        name: string;
    }>;
    links: {
        show: string;
        clone: string;
    } | null;
};

type Props = {
    account: Account;
    pipelines: Pipeline[];
    error: string | null;
    filters: {
        activity: string;
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
        export: string;
        reset: string;
        create: string;
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
        };
    };
};

export default function PipelineIndex({ account, pipelines, error, filters, can, links }: Props) {
    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Воронки' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">Воронки: {account.name}</h1>
                    <div className="text-sm text-slate-500">{account.base_domain}</div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <a className="rounded border border-slate-300 bg-white px-4 py-2 text-sm hover:border-blue-400" href={links.export}>Экспорт в Excel</a>
                    {can.sync ? <a className="rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800" href={links.create}>Создать воронку</a> : null}
                </div>
            </div>

            <form className="mb-4 flex flex-wrap gap-3 rounded-lg border border-slate-200 bg-white p-4 text-sm shadow-sm" method="get">
                <select className="rounded border-slate-300" defaultValue={filters.activity} name="activity">
                    <option value="">Все воронки</option>
                    <option value="active">Только активные</option>
                    <option value="archived">Только архивные</option>
                </select>
                <button className="rounded bg-blue-700 px-3 py-2 text-white hover:bg-blue-800" type="submit">Фильтр</button>
                <a className="rounded border border-slate-300 px-3 py-2 hover:border-blue-400" href={links.reset}>Сбросить</a>
            </form>

            {error ? (
                <div className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Не удалось загрузить воронки из amoCRM: {error}
                </div>
            ) : null}

            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-2">ID</th>
                                <th>Название</th>
                                <th>Главная</th>
                                <th>Неразобранное</th>
                                <th>Архив</th>
                                <th>Этапов</th>
                                <th>Этапы</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pipelines.length > 0 ? pipelines.map((pipeline, index) => (
                                <tr className="border-t border-slate-100 align-top" key={pipeline.id || `${pipeline.name}-${index}`}>
                                    <td className="py-2">{pipeline.id || '-'}</td>
                                    <td className="font-medium">
                                        {pipeline.links ? (
                                            <a className="text-blue-700 hover:text-blue-900" href={pipeline.links.show}>{pipeline.name}</a>
                                        ) : pipeline.name}
                                    </td>
                                    <td>{pipeline.is_main ? 'да' : 'нет'}</td>
                                    <td>{pipeline.is_unsorted_on ? 'да' : 'нет'}</td>
                                    <td>{pipeline.is_archive ? 'да' : 'нет'}</td>
                                    <td>{pipeline.statuses.length}</td>
                                    <td>
                                        <div className="flex max-w-2xl flex-wrap gap-2">
                                            {pipeline.statuses.map((status, statusIndex) => (
                                                <span className="rounded border border-slate-200 px-2 py-1 text-xs" key={status.id || `${status.name}-${statusIndex}`}>
                                                    {status.name}
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td>
                                        {pipeline.links ? (
                                            <div className="flex flex-wrap gap-2 text-sm">
                                                <a className="text-blue-700 hover:text-blue-900" href={pipeline.links.show}>Настройки</a>
                                                {can.sync ? <a className="text-blue-700 hover:text-blue-900" href={pipeline.links.clone}>Клонировать</a> : null}
                                            </div>
                                        ) : null}
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="py-4 text-slate-500" colSpan={8}>Воронки не загружены или пока отсутствуют.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
