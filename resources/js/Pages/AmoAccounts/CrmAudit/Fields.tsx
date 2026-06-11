import DashboardMetric from '../../../Components/DashboardMetric';
import JsonDetails from '../../../Components/JsonDetails';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Field = {
    id: number;
    entity_type: 'leads' | 'contacts';
    amo_field_id: number;
    name: string;
    field_type: string | null;
    code: string | null;
    sort: number | null;
    enums_count: number;
    enums: unknown[];
};

type Props = {
    account: Account;
    filters: {
        entity_type: string;
        search: string;
    };
    summary: {
        leads: number;
        contacts: number;
    };
    fields: Field[];
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        crm_audit: string;
        fields: string;
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

const entityLabels: Record<string, string> = {
    leads: 'Сделки',
    contacts: 'Контакты',
};

export default function CrmAuditFields({ account, filters, summary, fields, links }: Props) {
    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'CRM-аудит', href: links.crm_audit },
                { label: 'Поля' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold">Поля сделок и контактов</h1>
                    <div className="text-theme-sm text-gray-500">{account.name} · {account.base_domain}</div>
                </div>
                <a className="rounded border border-gray-200 bg-white px-4 py-2 text-sm hover:border-brand-300" href={links.crm_audit}>
                    Назад к CRM-аудиту
                </a>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <DashboardMetric label="Поля сделок" value={summary.leads} />
                <DashboardMetric label="Поля контактов" value={summary.contacts} />
                <DashboardMetric label="Показано" value={fields.length} />
            </div>

            <section className="mt-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <form action={links.fields} className="grid gap-3 text-sm md:grid-cols-[220px_1fr_auto]" method="get">
                    <label>
                        <span className="text-theme-xs text-gray-500">Сущность</span>
                        <select className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" defaultValue={filters.entity_type} name="entity_type">
                            <option value="">Сделки и контакты</option>
                            <option value="leads">Сделки</option>
                            <option value="contacts">Контакты</option>
                        </select>
                    </label>
                    <label>
                        <span className="text-theme-xs text-gray-500">Поиск по названию или ID</span>
                        <input className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" defaultValue={filters.search} name="search" placeholder="Рекрутер или 123456" />
                    </label>
                    <div className="flex items-end gap-2">
                        <button className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" type="submit">Найти</button>
                        <a className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500 text-gray-700 hover:border-brand-300" href={links.fields}>Сбросить</a>
                    </div>
                </form>
            </section>

            <section className="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="border-b border-gray-200 px-4 py-3">
                    <h2 className="font-semibold">Список полей</h2>
                    <div className="mt-1 text-theme-sm text-gray-500">
                        Данные берутся из последней синхронизации CRM-аудита. Для обновления ID и новых полей запустите синхронизацию структуры.
                    </div>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-3 font-semibold">Сущность</th>
                                <th className="px-3 py-3 font-semibold">Field ID</th>
                                <th className="px-3 py-3 font-semibold">Название</th>
                                <th className="px-3 py-3 font-semibold">Тип</th>
                                <th className="px-3 py-3 font-semibold">Code</th>
                                <th className="px-3 py-3 font-semibold">Sort</th>
                                <th className="px-3 py-3 font-semibold">Значения</th>
                            </tr>
                        </thead>
                        <tbody>
                            {fields.length > 0 ? fields.map((field) => (
                                <tr className="border-t border-gray-100 align-top hover:bg-gray-50" key={field.id}>
                                    <td className="px-4 py-3">{entityLabels[field.entity_type] || field.entity_type}</td>
                                    <td className="px-3 py-3 font-mono text-gray-900">{field.amo_field_id}</td>
                                    <td className="px-3 py-3 font-medium text-gray-900">{field.name}</td>
                                    <td className="px-3 py-3">{field.field_type || '-'}</td>
                                    <td className="px-3 py-3">{field.code || '-'}</td>
                                    <td className="px-3 py-3">{field.sort ?? '-'}</td>
                                    <td className="px-3 py-3">
                                        {field.enums_count > 0 ? (
                                            <div className="flex items-center gap-2">
                                                <span>{field.enums_count}</span>
                                                <JsonDetails data={field.enums} label="JSON" />
                                            </div>
                                        ) : '-'}
                                    </td>
                                </tr>
                            )) : <EmptyRow />}
                        </tbody>
                    </table>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}

function EmptyRow() {
    return (
        <tr>
            <td className="px-4 py-5 text-gray-500" colSpan={7}>Поля не найдены. Запустите CRM-аудит структуры или измените фильтр.</td>
        </tr>
    );
}
