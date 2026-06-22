import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import { Copy, ExternalLink, Settings2, Puzzle, CheckCircle, XCircle } from 'lucide-react';
import { useState } from 'react';

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

function CopyButton({ text }: { text: string }) {
    const [copied, setCopied] = useState(false);

    const handleCopy = () => {
        navigator.clipboard.writeText(text).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <button
            type="button"
            onClick={handleCopy}
            title="Скопировать"
            className="ml-1.5 shrink-0 rounded-md p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
        >
            <Copy className={`size-3.5 ${copied ? 'text-green-500' : ''}`} />
        </button>
    );
}

function StatusBadge({ active }: { active: boolean }) {
    return active ? (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
            <CheckCircle className="size-3.5" />
            Активен
        </span>
    ) : (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 ring-1 ring-inset ring-gray-500/20">
            <XCircle className="size-3.5" />
            Отключён
        </span>
    );
}

function WidgetCard({ widget }: { widget: Widget }) {
    const active = widget.is_enabled && widget.installation.is_enabled;

    return (
        <div className="group flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm transition-shadow hover:shadow-md">
            {/* Header */}
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <Puzzle className="size-5" />
                    </div>
                    <div className="min-w-0">
                        <div className="font-semibold text-gray-900">{widget.name}</div>
                        <div className="mt-0.5 flex items-center gap-1.5">
                            <code className="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-500">
                                {widget.code}
                            </code>
                            <span className="text-gray-300">·</span>
                            <span className="text-xs text-gray-400">порядок {widget.sort_order}</span>
                        </div>
                    </div>
                </div>
                <StatusBadge active={active} />
            </div>

            {/* Component key */}
            <div className="rounded-xl border border-gray-100 bg-gray-50 px-3.5 py-2.5">
                <div className="mb-1 text-xs font-medium uppercase tracking-wider text-gray-400">Компонент</div>
                <div className="flex items-center justify-between gap-2">
                    <code className="truncate font-mono text-xs text-gray-600">{widget.component_key}</code>
                </div>
            </div>

            {/* Public key */}
            <div className="rounded-xl border border-gray-100 bg-gray-50 px-3.5 py-2.5">
                <div className="mb-1 text-xs font-medium uppercase tracking-wider text-gray-400">Ключ клиента</div>
                <div className="flex items-center gap-1">
                    <code className="truncate font-mono text-xs text-gray-600">{widget.installation.public_key}</code>
                    <CopyButton text={widget.installation.public_key} />
                </div>
            </div>

            {/* Iframe URL */}
            {widget.installation.iframe_url && (
                <div className="rounded-xl border border-brand-100 bg-brand-50/40 px-3.5 py-2.5">
                    <div className="mb-1 text-xs font-medium uppercase tracking-wider text-brand-400">Iframe URL</div>
                    <div className="flex items-start gap-1">
                        <a
                            href={widget.installation.iframe_url}
                            target="_blank"
                            rel="noreferrer"
                            className="break-all font-mono text-xs text-brand-600 hover:text-brand-800 hover:underline"
                        >
                            {widget.installation.iframe_url}
                        </a>
                        <CopyButton text={widget.installation.iframe_url} />
                    </div>
                    {widget.installation.api_url && (
                        <div className="mt-2 border-t border-brand-100 pt-2">
                            <div className="mb-0.5 text-xs font-medium uppercase tracking-wider text-gray-400">API URL</div>
                            <div className="flex items-start gap-1">
                                <span className="break-all font-mono text-xs text-gray-500">{widget.installation.api_url}</span>
                                <CopyButton text={widget.installation.api_url} />
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Actions */}
            <div className="flex items-center gap-2 border-t border-gray-100 pt-1">
                <a
                    href={widget.installation.settings_url}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 shadow-theme-sm transition-colors hover:border-brand-300 hover:text-brand-600"
                >
                    <Settings2 className="size-3.5" />
                    Настройки
                </a>
                {widget.installation.iframe_url && (
                    <a
                        href={widget.installation.iframe_url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-600 shadow-theme-sm transition-colors hover:bg-brand-100"
                    >
                        <ExternalLink className="size-3.5" />
                        Открыть
                    </a>
                )}
            </div>
        </div>
    );
}

export default function AmoAccountWidgets({ account, widgets, links }: Props) {
    const activeCount = widgets.filter((w) => w.is_enabled && w.installation.is_enabled).length;

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
            {/* Page header */}
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wider text-brand-600">Dashboard-блоки</p>
                    <h1 className="mt-1 text-2xl font-semibold text-gray-900">{account.name}</h1>
                    <div className="mt-0.5 text-sm text-gray-500">{account.base_domain}</div>
                </div>
                <div className="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 shadow-theme-sm">
                    <span className="text-sm text-gray-500">Активных блоков:</span>
                    <span className="text-sm font-bold text-gray-900">{activeCount} / {widgets.length}</span>
                </div>
            </div>

            {/* Widget cards grid */}
            {widgets.length > 0 ? (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {widgets.map((widget) => (
                        <WidgetCard key={widget.id} widget={widget} />
                    ))}
                </div>
            ) : (
                <div className="rounded-2xl border border-dashed border-gray-200 bg-white py-16 text-center">
                    <Puzzle className="mx-auto size-10 text-gray-300" />
                    <p className="mt-3 text-sm font-medium text-gray-500">Нет подключённых блоков</p>
                    <p className="mt-1 text-xs text-gray-400">Dashboard-блоки появятся здесь после добавления</p>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
