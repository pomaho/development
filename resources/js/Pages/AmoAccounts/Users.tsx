import { RefreshCw, ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import JsonDetails from '../../Components/JsonDetails';
import Pagination from '../../Components/Pagination';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type AmoUser = {
    id: number;
    amo_user_id: number;
    name: string;
    email: string | null;
    role_id: number | string | null;
    group_id: number | string | null;
    is_admin: boolean;
    is_active: boolean;
    rights: Record<string, unknown>;
    raw: Record<string, unknown>;
    synced_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    account: Account;
    users: {
        data: AmoUser[];
        links: PaginationLink[];
        from: number;
    };
    roles: Array<number | string>;
    groups: Array<number | string>;
    filters: {
        search: string;
        active: string;
        role_id: string;
        group_id: string;
        admins: boolean;
        sort: string;
        direction: string;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        sync: string;
        export: string;
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

const jsonValue = (value: unknown) => JSON.stringify(value ?? null);

function SortIcon({ column, sort, direction }: { column: string; sort: string; direction: string }) {
    if (sort !== column) return <ChevronsUpDown size={12} className="opacity-40" />;
    return direction === 'asc' ? <ChevronUp size={12} /> : <ChevronDown size={12} />;
}

export default function AmoAccountUsers({ account, users, roles, groups, filters, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const inputClass = 'h-10 rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10';
    const selectClass = `${inputClass} pr-8`;
    const actionLinkClass = 'inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500';

    const buildSortUrl = (column: string) => {
        const params = new URLSearchParams(window.location.search);
        const isActive = filters.sort === column;
        params.set('sort', column);
        params.set('direction', isActive && filters.direction === 'desc' ? 'asc' : 'desc');
        params.delete('page');
        return `?${params.toString()}`;
    };

    const sortableColumns: { key: string; label: string }[] = [
        { key: 'amo_user_id', label: 'ID' },
        { key: 'name', label: 'Имя' },
        { key: 'email', label: 'Email' },
        { key: 'is_active', label: 'Активен' },
        { key: 'is_admin', label: 'Админ' },
        { key: 'role_id', label: 'Role' },
        { key: 'group_id', label: 'Group' },
    ];

    const staticColumns = ['Сделки', 'Контакты', 'Компании', 'Задачи', 'Почта', 'Каталоги'];
    const syncColumn = { key: 'synced_at', label: 'Sync' };

    const thClass = 'px-5 py-3';
    const thSortClass = `${thClass} cursor-pointer select-none hover:text-gray-700`;

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Users audit' },
            ]}
            links={links}
        >
            <div className="mb-6">
                <p className="text-theme-sm font-medium text-brand-600">Users audit</p>
                <h1 className="mt-1 text-2xl font-semibold text-gray-900">Пользователи: {account.name}</h1>
                <div className="mt-4 flex flex-wrap gap-3">
                    <a className={actionLinkClass} href={links.current_account.show}>Назад к аккаунту</a>
                    <a className={actionLinkClass} href={links.export}>Экспорт в Excel</a>
                    <form action={links.sync} method="post">
                        <input name="_token" type="hidden" value={csrf} />
                        <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" type="submit">
                            <RefreshCw size={15} />
                            Синхронизировать
                        </button>
                    </form>
                </div>
            </div>

            <form className="mb-4 flex flex-wrap gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm" method="get">
                <input className={inputClass} defaultValue={filters.search} name="search" placeholder="Имя или email" />
                <select className={selectClass} defaultValue={filters.active} name="active">
                    <option value="">Любая активность</option>
                    <option value="1">Только активные</option>
                    <option value="0">Только неактивные</option>
                </select>
                <select className={selectClass} defaultValue={filters.role_id} name="role_id">
                    <option value="">Все роли</option>
                    {roles.map((role) => <option key={role} value={role}>{role}</option>)}
                </select>
                <select className={selectClass} defaultValue={filters.group_id} name="group_id">
                    <option value="">Все группы</option>
                    {groups.map((group) => <option key={group} value={group}>{group}</option>)}
                </select>
                <label className="flex h-10 items-center gap-2 text-theme-sm font-medium text-gray-700">
                    <input className="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20" defaultChecked={filters.admins} name="admins" type="checkbox" value="1" />
                    Только админы
                </label>
                <button className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" type="submit">Фильтр</button>
            </form>

            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-theme-xs">
                        <thead className="bg-gray-50 font-semibold uppercase text-gray-500">
                            <tr>
                                <th className={thClass}>#</th>
                                {sortableColumns.map(({ key, label }) => (
                                    <th className={thSortClass} key={key}>
                                        <a className="inline-flex items-center gap-1" href={buildSortUrl(key)}>
                                            {label}
                                            <SortIcon column={key} direction={filters.direction} sort={filters.sort} />
                                        </a>
                                    </th>
                                ))}
                                {staticColumns.map((heading) => (
                                    <th className={thClass} key={heading}>{heading}</th>
                                ))}
                                <th className={thSortClass}>
                                    <a className="inline-flex items-center gap-1" href={buildSortUrl(syncColumn.key)}>
                                        {syncColumn.label}
                                        <SortIcon column={syncColumn.key} direction={filters.direction} sort={filters.sort} />
                                    </a>
                                </th>
                                <th className={thClass}>Raw</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {users.data.length > 0 ? users.data.map((user, index) => (
                                <tr className="align-top" key={user.id}>
                                    <td className="px-5 py-3 text-gray-400">{(users.from ?? 1) + index}</td>
                                    <td className="px-5 py-3 text-gray-700">{user.amo_user_id}</td>
                                    <td className="px-5 py-3 font-medium text-gray-900">{user.name}</td>
                                    <td className="px-5 py-3 text-gray-600">{user.email || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{user.is_active ? 'да' : 'нет'}</td>
                                    <td className="px-5 py-3 text-gray-600">{user.is_admin ? 'да' : 'нет'}</td>
                                    <td className="px-5 py-3 text-gray-600">{user.role_id || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{user.group_id || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{jsonValue(user.rights.leads)}</td>
                                    <td className="px-5 py-3 text-gray-600">{jsonValue(user.rights.contacts)}</td>
                                    <td className="px-5 py-3 text-gray-600">{jsonValue(user.rights.companies)}</td>
                                    <td className="px-5 py-3 text-gray-600">{jsonValue(user.rights.tasks)}</td>
                                    <td className="px-5 py-3 text-gray-600">{jsonValue(user.rights.mail_access ?? user.rights.mail)}</td>
                                    <td className="px-5 py-3 text-gray-600">{jsonValue(user.rights.catalogs)}</td>
                                    <td className="px-5 py-3 text-gray-600">{user.synced_at || '-'}</td>
                                    <td className="px-5 py-3"><JsonDetails data={user.raw} /></td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="px-5 py-6 text-gray-500" colSpan={16}>Пользователи не найдены.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="border-t border-gray-100 px-5 pb-5">
                    <Pagination links={users.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
