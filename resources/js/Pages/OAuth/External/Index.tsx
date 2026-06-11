import Pagination from '../../../Components/Pagination';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Connection = {
    id: number;
    name: string | null;
    base_domain: string | null;
    status: string;
    created_at: string | null;
    account: {
        id: number;
        name: string;
        url: string;
    } | null;
    url: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    connections: {
        data: Connection[];
        links: PaginationLink[];
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        install: string;
        api_logs: string;
        logout: string;
        current_account: null;
    };
};

export default function OAuthExternalIndex({ connections, links }: Props) {
    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'OAuth amoCRM' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">OAuth-подключение amoCRM</h1>
                    <p className="mt-1 text-theme-sm text-gray-600">История установок через публичную кнопку интеграции.</p>
                </div>
                <a className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" href={links.install} rel="noreferrer" target="_blank">
                    Открыть публичную страницу установки
                </a>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-gray-500">
                            <tr><th className="py-2">Клиент</th><th>Домен</th><th>Статус</th><th>Создано</th><th>Аккаунт</th><th>Действия</th></tr>
                        </thead>
                        <tbody>
                            {connections.data.length > 0 ? connections.data.map((connection) => (
                                <tr className="border-t border-gray-100" key={connection.id}>
                                    <td className="py-2 font-medium">{connection.name || '-'}</td>
                                    <td>{connection.base_domain || '-'}</td>
                                    <td>{connection.status}</td>
                                    <td>{connection.created_at || '-'}</td>
                                    <td>{connection.account ? <a className="text-brand-600" href={connection.account.url}>{connection.account.name}</a> : '-'}</td>
                                    <td><a className="text-brand-600" href={connection.url}>открыть</a></td>
                                </tr>
                            )) : (
                                <tr><td className="py-4 text-gray-500" colSpan={6}>OAuth-подключений пока нет.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={connections.links} />
            </div>
        </AuthenticatedLayout>
    );
}
