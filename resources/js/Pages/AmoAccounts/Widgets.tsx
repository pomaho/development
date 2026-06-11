import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Widget = {
    id: number;
    code: string;
    name: string;
    component_key: string;
    sort_order: number;
    is_enabled: boolean;
    installation: {
        public_key: string;
        is_enabled: boolean;
        settings_url: string;
        iframe_url: string | null;
        api_url: string | null;
    };
};

type Props = {
    account: Account;
    widgets: Widget[];
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        current_account: {
            dashboard: string;
            show: string;
            users: string;
            roles: string;
            leads: string;
            pipelines: string;
            catalogs?: string;
            responsibility_redistribution?: string;
            task_statistics?: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        };
    };
};

export default function AmoAccountWidgets({ account, widgets, links }: Props) {
    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Dashboard-блоки' },
            ]}
            links={links}
        >
            <div className="mb-6">
                <h1 className="text-2xl font-semibold">Dashboard-блоки: {account.name}</h1>
                <div className="text-sm text-slate-500">{account.base_domain}</div>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-2">Код</th>
                                <th>Название блока</th>
                                <th>Компонент</th>
                                <th>Порядок</th>
                                <th>Статус</th>
                                <th>Ключ клиента</th>
                                <th>Настройки</th>
                                <th>Iframe URL</th>
                            </tr>
                        </thead>
                        <tbody>
                            {widgets.map((widget) => (
                                <tr className="border-t border-slate-100" key={widget.id}>
                                    <td className="py-2 font-mono text-xs">{widget.code}</td>
                                    <td>{widget.name}</td>
                                    <td>{widget.component_key}</td>
                                    <td>{widget.sort_order}</td>
                                    <td>{widget.is_enabled && widget.installation.is_enabled ? 'enabled' : 'disabled'}</td>
                                    <td className="font-mono text-xs">{widget.installation.public_key}</td>
                                    <td>
                                        <a className="text-blue-700 hover:text-blue-900" href={widget.installation.settings_url}>
                                            Настроить
                                        </a>
                                    </td>
                                    <td className="max-w-xl">
                                        {widget.installation.iframe_url ? (
                                            <div className="space-y-1">
                                                <a className="break-all text-blue-700 hover:text-blue-900" href={widget.installation.iframe_url} target="_blank" rel="noreferrer">
                                                    {widget.installation.iframe_url}
                                                </a>
                                                {widget.installation.api_url ? <div className="break-all text-xs text-slate-500">API: {widget.installation.api_url}</div> : null}
                                            </div>
                                        ) : (
                                            <span className="text-slate-400">-</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
