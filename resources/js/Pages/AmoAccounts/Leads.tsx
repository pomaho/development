import Pagination from '../../Components/Pagination';
import JsonDetails from '../../Components/JsonDetails';
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
    const inputClass = 'h-10 rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10';
    const actionLinkClass = 'inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500';

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
                <p className="text-theme-sm font-medium text-brand-600">Leads analytics</p>
                <h1 className="mt-1 text-2xl font-semibold text-gray-900">Сделки: {account.name}</h1>
                <div className="mt-4 flex flex-wrap gap-3">
                    <a className={actionLinkClass} href={links.current_account.show}>Назад к аккаунту</a>
                    <a className={actionLinkClass} href={links.export}>Экспорт в Excel</a>
                </div>
            </div>

            <form className="mb-4 grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm md:grid-cols-6" method="get">
                <input className={inputClass} defaultValue={filters.search} name="search" placeholder="Название или ID" />
                <select className={inputClass} defaultValue={filters.pipeline_id} name="pipeline_id">
                    <option value="">Все воронки</option>
                    {pipelines.map((pipeline) => <option key={pipeline.id} value={pipeline.id}>{pipeline.name}</option>)}
                </select>
                <select className={inputClass} defaultValue={filters.status_id} name="status_id">
                    <option value="">Все этапы</option>
                    {statuses.map((status) => (
                        <option key={`${status.pipeline_id}-${status.id}`} value={status.id}>
                            {status.name} ({status.id})
                        </option>
                    ))}
                </select>
                <select className={inputClass} defaultValue={filters.responsible_user_id} name="responsible_user_id">
                    <option value="">Все ответственные</option>
                    {responsibles.map((responsible) => (
                        <option key={responsible.id} value={responsible.id}>
                            {responsible.name ? `${responsible.name} (${responsible.id})` : responsible.id}
                        </option>
                    ))}
                </select>
                <input className={inputClass} defaultValue={filters.created_from} name="created_from" type="date" />
                <input className={inputClass} defaultValue={filters.created_to} name="created_to" type="date" />
                <div className="flex flex-wrap gap-2 md:col-span-6">
                    <button className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" type="submit">Фильтр</button>
                    <a className={actionLinkClass} href={links.reset}>Сбросить</a>
                </div>
            </form>

            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-theme-xs">
                        <thead className="bg-gray-50 font-semibold uppercase text-gray-500">
                            <tr>
                                {['ID', 'Название', 'Воронка', 'Этап', 'Ответственный', 'Создана', 'Обновлена', 'Бюджет', 'Поля', 'Raw'].map((heading) => (
                                    <th className="px-5 py-3" key={heading}>{heading}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {leads.data.length > 0 ? leads.data.map((lead) => (
                                <tr className="align-top" key={lead.id}>
                                    <td className="px-5 py-3 text-gray-700">{lead.external_id}</td>
                                    <td className="px-5 py-3 font-medium text-gray-900">{lead.name}</td>
                                    <td className="px-5 py-3 text-gray-600">{lead.pipeline_name || lead.pipeline_id || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{lead.status_name || lead.status_id || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">
                                        {lead.responsible_name
                                            ? `${lead.responsible_name} (${lead.responsible_user_id})`
                                            : (lead.responsible_user_id || '-')}
                                    </td>
                                    <td className="px-5 py-3 text-gray-600">{lead.entity_created_at || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{lead.entity_updated_at || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{lead.price || '-'}</td>
                                    <td className="max-w-lg px-5 py-3 text-gray-600">{summary(lead.custom_fields_values)}</td>
                                    <td className="px-5 py-3"><JsonDetails data={lead.raw} /></td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="px-5 py-6 text-gray-500" colSpan={10}>Сделки не найдены. Запустите CRM-аудит за нужный период.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="border-t border-gray-100 px-5 pb-5">
                    <Pagination links={leads.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
