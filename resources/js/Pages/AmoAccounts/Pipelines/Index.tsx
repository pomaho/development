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
        transfer_leads: string;
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
    const inputClass = 'h-10 rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10';
    const actionLinkClass = 'inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500';

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
                    <p className="text-theme-sm font-medium text-brand-600">Pipeline settings</p>
                    <h1 className="mt-1 text-2xl font-semibold text-gray-900">Воронки: {account.name}</h1>
                    <div className="mt-1 text-theme-sm text-gray-500">{account.base_domain}</div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <a className={actionLinkClass} href={links.export}>Экспорт в Excel</a>
                    {can.sync ? <a className={actionLinkClass} href={links.transfer_leads}>Перенос сделок</a> : null}
                    {can.sync ? <a className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" href={links.create}>Создать воронку</a> : null}
                </div>
            </div>

            <form className="mb-4 flex flex-wrap gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm" method="get">
                <select className={inputClass} defaultValue={filters.activity} name="activity">
                    <option value="">Все воронки</option>
                    <option value="active">Только активные</option>
                    <option value="archived">Только архивные</option>
                </select>
                <button className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" type="submit">Фильтр</button>
                <a className={actionLinkClass} href={links.reset}>Сбросить</a>
            </form>

            {error ? (
                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-theme-sm text-amber-800">
                    Не удалось загрузить воронки из amoCRM: {error}
                </div>
            ) : null}

            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-theme-sm">
                        <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                            <tr>
                                {['ID', 'Название', 'Главная', 'Неразобранное', 'Архив', 'Этапов', 'Этапы', 'Действия'].map((heading) => (
                                    <th className="px-5 py-3" key={heading}>{heading}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {pipelines.length > 0 ? pipelines.map((pipeline, index) => (
                                <tr className="align-top" key={pipeline.id || `${pipeline.name}-${index}`}>
                                    <td className="px-5 py-3 text-gray-700">{pipeline.id || '-'}</td>
                                    <td className="px-5 py-3 font-medium text-gray-900">
                                        {pipeline.links ? (
                                            <a className="text-brand-600 hover:text-brand-700" href={pipeline.links.show}>{pipeline.name}</a>
                                        ) : pipeline.name}
                                    </td>
                                    <td className="px-5 py-3 text-gray-600">{pipeline.is_main ? 'да' : 'нет'}</td>
                                    <td className="px-5 py-3 text-gray-600">{pipeline.is_unsorted_on ? 'да' : 'нет'}</td>
                                    <td className="px-5 py-3 text-gray-600">{pipeline.is_archive ? 'да' : 'нет'}</td>
                                    <td className="px-5 py-3 text-gray-600">{pipeline.statuses.length}</td>
                                    <td className="px-5 py-3">
                                        <div className="flex max-w-2xl flex-wrap gap-2">
                                            {pipeline.statuses.map((status, statusIndex) => (
                                                <span className="rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-theme-xs text-gray-600" key={status.id || `${status.name}-${statusIndex}`}>
                                                    {status.name}
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-5 py-3">
                                        {pipeline.links ? (
                                            <div className="flex flex-wrap gap-2">
                                                <a className="text-theme-sm font-medium text-brand-600 hover:text-brand-700" href={pipeline.links.show}>Настройки</a>
                                                {can.sync ? <a className="text-theme-sm font-medium text-brand-600 hover:text-brand-700" href={pipeline.links.clone}>Клонировать</a> : null}
                                            </div>
                                        ) : null}
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="px-5 py-6 text-gray-500" colSpan={8}>Воронки не загружены или пока отсутствуют.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
