import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

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
    };
    roles: Array<number | string>;
    groups: Array<number | string>;
    filters: {
        search: string;
        active: string;
        role_id: string;
        group_id: string;
        admins: boolean;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
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

const paginationLabel = (label: string) => label
    .replace('&laquo; Previous', 'Назад')
    .replace('Next &raquo;', 'Вперед');

export default function AmoAccountUsers({ account, users, roles, groups, filters, links }: Props) {
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
                <h1 className="text-2xl font-semibold">Пользователи: {account.name}</h1>
                <div className="flex flex-wrap gap-3 text-sm">
                    <a className="text-blue-700 hover:text-blue-900" href={links.current_account.show}>Назад к аккаунту</a>
                    <a className="text-blue-700 hover:text-blue-900" href={links.export}>Экспорт в Excel</a>
                </div>
            </div>

            <form className="mb-4 flex flex-wrap gap-3 rounded-lg border border-slate-200 bg-white p-4 text-sm shadow-sm" method="get">
                <input className="rounded border-slate-300" defaultValue={filters.search} name="search" placeholder="Имя или email" />
                <select className="rounded border-slate-300" defaultValue={filters.active} name="active">
                    <option value="">Любая активность</option>
                    <option value="1">Только активные</option>
                    <option value="0">Только неактивные</option>
                </select>
                <select className="rounded border-slate-300" defaultValue={filters.role_id} name="role_id">
                    <option value="">Все роли</option>
                    {roles.map((role) => <option key={role} value={role}>{role}</option>)}
                </select>
                <select className="rounded border-slate-300" defaultValue={filters.group_id} name="group_id">
                    <option value="">Все группы</option>
                    {groups.map((group) => <option key={group} value={group}>{group}</option>)}
                </select>
                <label className="flex items-center gap-2">
                    <input className="rounded border-slate-300" defaultChecked={filters.admins} name="admins" type="checkbox" value="1" />
                    Только админы
                </label>
                <button className="rounded bg-blue-700 px-3 py-2 text-white hover:bg-blue-800" type="submit">Фильтр</button>
            </form>

            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-2">ID</th>
                                <th>Имя</th>
                                <th>Email</th>
                                <th>Активен</th>
                                <th>Админ</th>
                                <th>Role</th>
                                <th>Group</th>
                                <th>Сделки</th>
                                <th>Контакты</th>
                                <th>Компании</th>
                                <th>Задачи</th>
                                <th>Почта</th>
                                <th>Каталоги</th>
                                <th>Sync</th>
                                <th>Raw</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.length > 0 ? users.data.map((user) => (
                                <tr className="border-t border-slate-100 align-top" key={user.id}>
                                    <td className="py-2">{user.amo_user_id}</td>
                                    <td className="font-medium">{user.name}</td>
                                    <td>{user.email || '-'}</td>
                                    <td>{user.is_active ? 'да' : 'нет'}</td>
                                    <td>{user.is_admin ? 'да' : 'нет'}</td>
                                    <td>{user.role_id || '-'}</td>
                                    <td>{user.group_id || '-'}</td>
                                    <td>{jsonValue(user.rights.leads)}</td>
                                    <td>{jsonValue(user.rights.contacts)}</td>
                                    <td>{jsonValue(user.rights.companies)}</td>
                                    <td>{jsonValue(user.rights.tasks)}</td>
                                    <td>{jsonValue(user.rights.mail_access ?? user.rights.mail)}</td>
                                    <td>{jsonValue(user.rights.catalogs)}</td>
                                    <td>{user.synced_at || '-'}</td>
                                    <td>
                                        <details>
                                            <summary className="cursor-pointer text-blue-700">JSON</summary>
                                            <pre className="mt-2 max-w-md overflow-auto rounded bg-slate-950 p-3 text-[11px] text-slate-50">
                                                {JSON.stringify(user.raw, null, 2)}
                                            </pre>
                                        </details>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="py-4 text-slate-500" colSpan={15}>Пользователи не найдены.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {users.links.length > 3 ? (
                    <div className="mt-4 flex flex-wrap gap-2 text-sm">
                        {users.links.map((link, index) => link.url ? (
                            <a
                                className={link.active
                                    ? 'rounded bg-blue-700 px-3 py-1 text-white'
                                    : 'rounded border border-slate-300 px-3 py-1 text-slate-700 hover:border-blue-400'}
                                href={link.url}
                                key={`${link.label}-${index}`}
                            >
                                {paginationLabel(link.label)}
                            </a>
                        ) : (
                            <span className="rounded border border-slate-200 px-3 py-1 text-slate-400" key={`${link.label}-${index}`}>
                                {paginationLabel(link.label)}
                            </span>
                        ))}
                    </div>
                ) : null}
            </div>
        </AuthenticatedLayout>
    );
}
