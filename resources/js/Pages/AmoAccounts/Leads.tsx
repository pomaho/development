import Pagination from '../../Components/Pagination';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Lead = {
    id: number;
    external_id: string;
    name: string;
    pipeline_id: number | string | null;
    pipeline_name: string | null;
    status_id: number | string | null;
    status_name: string | null;
    responsible_user_id: number | string | null;
    responsible_name: string | null;
    entity_created_at: string | null;
    entity_updated_at: string | null;
    price: number | string | null;
    custom_fields_values: unknown[];
    raw: Record<string, unknown>;
};

type DictionaryItem = {
    id: number | string;
    name: string;
};

type StatusItem = DictionaryItem & {
    pipeline_id: number | string;
};

type Responsible = {
    id: number | string;
    name: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    account: Account;
    leads: {
        data: Lead[];
        links: PaginationLink[];
    };
    pipelines: DictionaryItem[];
    statuses: StatusItem[];
    responsibles: Responsible[];
    filters: {
        search: string;
        pipeline_id: string;
        status_id: string;
        responsible_user_id: string;
        created_from: string;
        created_to: string;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        export: string;
        reset: string;
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

const summary = (value: unknown) => {
    const encoded = JSON.stringify(value ?? null);

    if (encoded.length <= 180) {
        return encoded;
    }

    return `${encoded.slice(0, 180)}...`;
};

export default function AmoAccountLeads({ account, leads, pipelines, statuses, responsibles, filters, links }: Props) {
    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Сделки' },
            ]}
            links={links}
        >
            <div className="mb-6">
                <h1 className="text-2xl font-semibold">Сделки: {account.name}</h1>
                <div className="flex flex-wrap gap-3 text-sm">
                    <a className="text-blue-700 hover:text-blue-900" href={links.current_account.show}>Назад к аккаунту</a>
                    <a className="text-blue-700 hover:text-blue-900" href={links.export}>Экспорт в Excel</a>
                </div>
            </div>

            <form className="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 text-sm shadow-sm md:grid-cols-6" method="get">
                <input className="rounded border-slate-300" defaultValue={filters.search} name="search" placeholder="Название или ID" />
                <select className="rounded border-slate-300" defaultValue={filters.pipeline_id} name="pipeline_id">
                    <option value="">Все воронки</option>
                    {pipelines.map((pipeline) => <option key={pipeline.id} value={pipeline.id}>{pipeline.name}</option>)}
                </select>
                <select className="rounded border-slate-300" defaultValue={filters.status_id} name="status_id">
                    <option value="">Все этапы</option>
                    {statuses.map((status) => (
                        <option key={`${status.pipeline_id}-${status.id}`} value={status.id}>
                            {status.name} ({status.id})
                        </option>
                    ))}
                </select>
                <select className="rounded border-slate-300" defaultValue={filters.responsible_user_id} name="responsible_user_id">
                    <option value="">Все ответственные</option>
                    {responsibles.map((responsible) => (
                        <option key={responsible.id} value={responsible.id}>
                            {responsible.name ? `${responsible.name} (${responsible.id})` : responsible.id}
                        </option>
                    ))}
                </select>
                <input className="rounded border-slate-300" defaultValue={filters.created_from} name="created_from" type="date" />
                <input className="rounded border-slate-300" defaultValue={filters.created_to} name="created_to" type="date" />
                <div className="flex flex-wrap gap-2 md:col-span-6">
                    <button className="rounded bg-blue-700 px-3 py-2 text-white hover:bg-blue-800" type="submit">Фильтр</button>
                    <a className="rounded border border-slate-300 px-3 py-2 hover:border-blue-400" href={links.reset}>Сбросить</a>
                </div>
            </form>

            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-2">ID</th>
                                <th>Название</th>
                                <th>Воронка</th>
                                <th>Этап</th>
                                <th>Ответственный</th>
                                <th>Создана</th>
                                <th>Обновлена</th>
                                <th>Бюджет</th>
                                <th>Поля</th>
                                <th>Raw</th>
                            </tr>
                        </thead>
                        <tbody>
                            {leads.data.length > 0 ? leads.data.map((lead) => (
                                <tr className="border-t border-slate-100 align-top" key={lead.id}>
                                    <td className="py-2">{lead.external_id}</td>
                                    <td className="font-medium">{lead.name}</td>
                                    <td>{lead.pipeline_name || lead.pipeline_id || '-'}</td>
                                    <td>{lead.status_name || lead.status_id || '-'}</td>
                                    <td>
                                        {lead.responsible_name
                                            ? `${lead.responsible_name} (${lead.responsible_user_id})`
                                            : (lead.responsible_user_id || '-')}
                                    </td>
                                    <td>{lead.entity_created_at || '-'}</td>
                                    <td>{lead.entity_updated_at || '-'}</td>
                                    <td>{lead.price || '-'}</td>
                                    <td className="max-w-lg">{summary(lead.custom_fields_values)}</td>
                                    <td>
                                        <details>
                                            <summary className="cursor-pointer text-blue-700">JSON</summary>
                                            <pre className="mt-2 max-w-md overflow-auto rounded bg-slate-950 p-3 text-[11px] text-slate-50">
                                                {JSON.stringify(lead.raw, null, 2)}
                                            </pre>
                                        </details>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="py-4 text-slate-500" colSpan={10}>Сделки не найдены. Запустите CRM-аудит за нужный период.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={leads.links} />
            </div>
        </AuthenticatedLayout>
    );
}
