import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type AmoRole = {
    id: number;
    amo_role_id: number;
    name: string;
    rights: Record<string, unknown>;
    users_count: number;
    synced_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    account: Account;
    roles: {
        data: AmoRole[];
        links: PaginationLink[];
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

const paginationLabel = (label: string) => label
    .replace('&laquo; Previous', 'Назад')
    .replace('Next &raquo;', 'Вперед');

const rightsSummary = (rights: Record<string, unknown>) => {
    const encoded = JSON.stringify(rights);

    if (encoded.length <= 180) {
        return encoded;
    }

    return `${encoded.slice(0, 180)}...`;
};

export default function AmoAccountRoles({ account, roles, links }: Props) {
    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Роли' },
            ]}
            links={links}
        >
            <div className="mb-6">
                <h1 className="text-2xl font-semibold">Роли: {account.name}</h1>
                <div className="flex flex-wrap gap-3 text-sm">
                    <a className="text-blue-700 hover:text-blue-900" href={links.current_account.show}>Назад к аккаунту</a>
                    <a className="text-blue-700 hover:text-blue-900" href={links.export}>Экспорт в Excel</a>
                </div>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-2">ID роли</th>
                                <th>Название</th>
                                <th>Пользователей</th>
                                <th>Права</th>
                                <th>Sync</th>
                                <th>JSON</th>
                            </tr>
                        </thead>
                        <tbody>
                            {roles.data.length > 0 ? roles.data.map((role) => (
                                <tr className="border-t border-slate-100 align-top" key={role.id}>
                                    <td className="py-2">{role.amo_role_id}</td>
                                    <td className="font-medium">{role.name}</td>
                                    <td>{role.users_count}</td>
                                    <td className="max-w-lg">{rightsSummary(role.rights)}</td>
                                    <td>{role.synced_at || '-'}</td>
                                    <td>
                                        <details>
                                            <summary className="cursor-pointer text-blue-700">JSON</summary>
                                            <pre className="mt-2 max-w-md overflow-auto rounded bg-slate-950 p-3 text-[11px] text-slate-50">
                                                {JSON.stringify(role.rights, null, 2)}
                                            </pre>
                                        </details>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="py-4 text-slate-500" colSpan={6}>Роли не найдены.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {roles.links.length > 3 ? (
                    <div className="mt-4 flex flex-wrap gap-2 text-sm">
                        {roles.links.map((link, index) => link.url ? (
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
