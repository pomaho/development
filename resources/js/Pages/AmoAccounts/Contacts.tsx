import Pagination from '../../Components/Pagination';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type ContactRow = {
    id: number;
    external_id: string;
    type: 'contacts' | 'companies';
    name: string;
    category: string | null;
    responsible_user_id: number | string | null;
    entity_created_at: string | null;
    entity_updated_at: string | null;
};

type DictionaryItem = {
    value: string;
    label: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    account: Account;
    contacts: {
        data: ContactRow[];
        links: PaginationLink[];
    };
    types: DictionaryItem[];
    filters: {
        search: string;
        type: string;
        category: string;
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
            contacts: string;
            pipelines: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        };
    };
};

const typeLabel = (type: string, types: DictionaryItem[]) => types.find((t) => t.value === type)?.label ?? type;

export default function AmoAccountContacts({ account, contacts, types, filters, links }: Props) {
    const inputClass = 'h-10 rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10';
    const actionLinkClass = 'inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500';

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Контакты' },
            ]}
            links={links}
        >
            <div className="mb-6">
                <p className="text-theme-sm font-medium text-brand-600">CRM data</p>
                <h1 className="mt-1 text-2xl font-semibold text-gray-900">Контакты: {account.name}</h1>
                <div className="mt-4 flex flex-wrap gap-3">
                    <a className={actionLinkClass} href={links.current_account.show}>Назад к аккаунту</a>
                    <a className={actionLinkClass} href={links.export}>Экспорт в CSV</a>
                </div>
            </div>

            <form className="mb-4 grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm md:grid-cols-4" method="get">
                <input className={inputClass} defaultValue={filters.search} name="search" placeholder="Название или ID" />
                <select className={inputClass} defaultValue={filters.type} name="type">
                    <option value="">Контакты и компании</option>
                    {types.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}
                </select>
                <input className={inputClass} defaultValue={filters.category} name="category" placeholder="Категория (для контактов)" />
                <div className="flex flex-wrap gap-2">
                    <button className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" type="submit">Фильтр</button>
                    <a className={actionLinkClass} href={links.reset}>Сбросить</a>
                </div>
            </form>

            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-theme-xs">
                        <thead className="bg-gray-50 font-semibold uppercase text-gray-500">
                            <tr>
                                {['ID', 'Тип', 'Название', 'Категория', 'Ответственный', 'Создан', 'Обновлён'].map((heading) => (
                                    <th className="px-5 py-3" key={heading}>{heading}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {contacts.data.length > 0 ? contacts.data.map((row) => (
                                <tr className="align-top" key={row.id}>
                                    <td className="px-5 py-3 text-gray-700">{row.external_id}</td>
                                    <td className="px-5 py-3 text-gray-600">{typeLabel(row.type, types)}</td>
                                    <td className="px-5 py-3 font-medium text-gray-900">{row.name || 'Без названия'}</td>
                                    <td className="px-5 py-3 text-gray-600">{row.category || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{row.responsible_user_id || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{row.entity_created_at || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{row.entity_updated_at || '-'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="px-5 py-6 text-gray-500" colSpan={7}>Контакты не найдены. Запустите синхронизацию контактов в расписаниях.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="border-t border-gray-100 px-5 pb-5">
                    <Pagination links={contacts.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
