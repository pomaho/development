import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import JsonDetails from '../../Components/JsonDetails';
import Pagination from '../../Components/Pagination';

type ApiLog = {
    id: number;
    created_at: string | null;
    account_name: string | null;
    method: string;
    url: string;
    status_code: number | null;
    duration_ms: number | null;
    error_message: string | null;
    response_payload: Record<string, unknown> | unknown[];
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    logs: {
        data: ApiLog[];
        links: PaginationLink[];
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        export: string;
        logout: string;
        current_account: null;
    };
};

export default function ApiLogs({ logs, links }: Props) {
    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'API-логи' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-theme-sm font-medium text-brand-600">System logs</p>
                    <h1 className="mt-1 text-2xl font-semibold text-gray-900">API-логи</h1>
                </div>
                <a className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500" href={links.export}>Экспорт в Excel</a>
            </div>

            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-theme-sm">
                        <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                            <tr>
                                {['Дата', 'Аккаунт', 'Method', 'URL', 'Status', 'Duration', 'Error', 'Response'].map((heading) => (
                                    <th className="px-5 py-3" key={heading}>{heading}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {logs.data.map((log) => (
                                <tr className="align-top" key={log.id}>
                                    <td className="px-5 py-3 text-gray-700">{log.created_at || '-'}</td>
                                    <td className="px-5 py-3 font-medium text-gray-900">{log.account_name || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{log.method}</td>
                                    <td className="max-w-md break-all px-5 py-3 text-gray-600">{log.url}</td>
                                    <td className="px-5 py-3 text-gray-600">{log.status_code || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{log.duration_ms ? `${log.duration_ms} ms` : '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{log.error_message || '-'}</td>
                                    <td className="px-5 py-3"><JsonDetails data={log.response_payload} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="border-t border-gray-100 px-5 pb-5">
                    <Pagination links={logs.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
