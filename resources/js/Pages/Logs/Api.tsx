import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
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
                <h1 className="text-2xl font-semibold">API-логи</h1>
                <a className="rounded border border-slate-300 bg-white px-4 py-2 text-sm hover:border-blue-400" href={links.export}>Экспорт в Excel</a>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-2">Дата</th>
                                <th>Аккаунт</th>
                                <th>Method</th>
                                <th>URL</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Error</th>
                                <th>Response</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr className="border-t border-slate-100 align-top" key={log.id}>
                                    <td className="py-2">{log.created_at || '-'}</td>
                                    <td>{log.account_name || '-'}</td>
                                    <td>{log.method}</td>
                                    <td className="max-w-md break-all">{log.url}</td>
                                    <td>{log.status_code || '-'}</td>
                                    <td>{log.duration_ms ? `${log.duration_ms} ms` : '-'}</td>
                                    <td>{log.error_message || '-'}</td>
                                    <td>
                                        <details>
                                            <summary className="cursor-pointer text-blue-700">JSON</summary>
                                            <pre className="mt-2 max-w-md overflow-auto rounded bg-slate-950 p-3 text-[11px] text-slate-50">
                                                {JSON.stringify(log.response_payload, null, 2)}
                                            </pre>
                                        </details>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination links={logs.links} />
            </div>
        </AuthenticatedLayout>
    );
}
