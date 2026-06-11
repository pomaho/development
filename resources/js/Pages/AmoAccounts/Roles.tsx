import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import JsonDetails from '../../Components/JsonDetails';
import Pagination from '../../Components/Pagination';

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

const rightsSummary = (rights: Record<string, unknown>) => {
    const encoded = JSON.stringify(rights);

    if (encoded.length <= 180) {
        return encoded;
    }

    return `${encoded.slice(0, 180)}...`;
};

export default function AmoAccountRoles({ account, roles, links }: Props) {
    const actionLinkClass = 'inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500';

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
                <p className="text-theme-sm font-medium text-brand-600">Roles audit</p>
                <h1 className="mt-1 text-2xl font-semibold text-gray-900">Роли: {account.name}</h1>
                <div className="mt-4 flex flex-wrap gap-3">
                    <a className={actionLinkClass} href={links.current_account.show}>Назад к аккаунту</a>
                    <a className={actionLinkClass} href={links.export}>Экспорт в Excel</a>
                </div>
            </div>

            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-theme-sm">
                        <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                            <tr>
                                {['ID роли', 'Название', 'Пользователей', 'Права', 'Sync', 'JSON'].map((heading) => (
                                    <th className="px-5 py-3" key={heading}>{heading}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {roles.data.length > 0 ? roles.data.map((role) => (
                                <tr className="align-top" key={role.id}>
                                    <td className="px-5 py-3 text-gray-700">{role.amo_role_id}</td>
                                    <td className="px-5 py-3 font-medium text-gray-900">{role.name}</td>
                                    <td className="px-5 py-3 text-gray-600">{role.users_count}</td>
                                    <td className="max-w-lg px-5 py-3 text-gray-600">{rightsSummary(role.rights)}</td>
                                    <td className="px-5 py-3 text-gray-600">{role.synced_at || '-'}</td>
                                    <td className="px-5 py-3"><JsonDetails data={role.rights} /></td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="px-5 py-6 text-gray-500" colSpan={6}>Роли не найдены.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="border-t border-gray-100 px-5 pb-5">
                    <Pagination links={roles.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
