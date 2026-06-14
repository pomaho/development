import { Activity, AlertTriangle, DatabaseZap, RefreshCcw, ShieldCheck } from 'lucide-react';
import DashboardMetric from '../../../Components/DashboardMetric';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type WebhookEvent = {
    id: number;
    event_type: string;
    entity_type: string | null;
    entity_id: string | null;
    status: string;
    received_at: string | null;
    processed_at: string | null;
    error_message: string | null;
};

type Props = {
    account: Account;
    summary: {
        lead_schedules_total: number;
        lead_schedules_enabled: number;
        webhook_events_pending: number;
        webhook_events_failed: number;
    };
    can: {
        retry_webhooks: boolean;
    };
    recentWebhookEvents: WebhookEvent[];
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        retry_failed_webhooks: string;
        current_account: {
            dashboard: string;
            show: string;
            sync_center: string;
            users: string;
            roles: string;
            leads: string;
            pipelines: string;
            catalogs: string;
            lead_sync_schedules: string;
            events_sync: string;
            task_statistics: string;
            responsibility_redistribution: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        };
    };
};

export default function SyncCenterIndex({ account, summary, can, recentWebhookEvents, links }: Props) {
    const accountLinks = links.current_account;
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const cards = [
        {
            title: 'Расписания сделок',
            description: 'Настройка регулярной и ручной загрузки сделок по конкретным воронкам.',
            href: accountLinks.lead_sync_schedules,
            icon: <RefreshCcw size={20} />,
        },
        {
            title: 'CRM-аудит',
            description: 'Обновление структуры CRM, полей, воронок и snapshots для диагностики.',
            href: accountLinks.crm_audit,
            icon: <ShieldCheck size={20} />,
        },
        {
            title: 'События',
            description: 'Загрузка истории событий amoCRM для отчетов и анализа процессов.',
            href: accountLinks.events_sync,
            icon: <Activity size={20} />,
        },
    ];

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: accountLinks.show },
                { label: 'Центр синхронизации' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="text-theme-sm font-medium text-brand-600">Sync center</p>
                    <h1 className="mt-1 text-2xl font-semibold text-gray-900">Центр синхронизации: {account.name}</h1>
                    <div className="mt-1 text-theme-sm text-gray-500">{account.base_domain}</div>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <DashboardMetric label="Расписания сделок" value={summary.lead_schedules_total} />
                <DashboardMetric label="Включено" value={summary.lead_schedules_enabled} />
                <DashboardMetric label="Webhook в очереди" value={summary.webhook_events_pending} />
                <DashboardMetric label="Webhook ошибки" value={summary.webhook_events_failed} />
            </div>

            <section className="mt-6 grid gap-4 xl:grid-cols-3">
                {cards.map((card) => (
                    <a className="group rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm transition hover:border-brand-300 hover:shadow-theme-md" href={card.href} key={card.title}>
                        <div className="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                            {card.icon}
                        </div>
                        <h2 className="mt-4 text-lg font-semibold text-gray-900 group-hover:text-brand-600">{card.title}</h2>
                        <p className="mt-2 text-theme-sm text-gray-500">{card.description}</p>
                    </a>
                ))}
            </section>

            <section className="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">Последние webhook events</h2>
                        <p className="mt-1 text-theme-sm text-gray-500">Оперативные события, которые amoCRM отправила в сервис.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {can.retry_webhooks && summary.webhook_events_failed > 0 ? (
                            <form action={links.retry_failed_webhooks} method="post">
                                <input name="_token" type="hidden" value={csrf} />
                                <button className="inline-flex h-9 items-center rounded-lg bg-error-500 px-3 text-theme-xs font-medium text-white shadow-theme-xs hover:bg-error-600" type="submit">
                                    Переобработать ошибки
                                </button>
                            </form>
                        ) : null}
                        <span className="inline-flex items-center gap-2 rounded-full bg-gray-50 px-3 py-1 text-theme-xs font-medium text-gray-600">
                            <DatabaseZap size={14} />
                            amo_webhook_events
                        </span>
                    </div>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[900px] text-left text-theme-sm">
                        <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th className="px-5 py-3">Получено</th>
                                <th className="px-5 py-3">Событие</th>
                                <th className="px-5 py-3">Сущность</th>
                                <th className="px-5 py-3">Status</th>
                                <th className="px-5 py-3">Обработано</th>
                                <th className="px-5 py-3">Ошибка</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {recentWebhookEvents.length > 0 ? recentWebhookEvents.map((event) => (
                                <tr className="align-top" key={event.id}>
                                    <td className="px-5 py-3 text-gray-600">{event.received_at || '-'}</td>
                                    <td className="px-5 py-3 font-medium text-gray-900">{event.event_type}</td>
                                    <td className="px-5 py-3 text-gray-600">{event.entity_type || '-'} {event.entity_id ? `#${event.entity_id}` : ''}</td>
                                    <td className="px-5 py-3"><StatusBadge status={event.status} /></td>
                                    <td className="px-5 py-3 text-gray-600">{event.processed_at || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{event.error_message || '-'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="px-5 py-6 text-gray-500" colSpan={6}>Webhook-событий пока нет.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}

function StatusBadge({ status }: { status: string }) {
    const className = status === 'failed'
        ? 'bg-error-50 text-error-700'
        : status === 'processed'
            ? 'bg-success-50 text-success-700'
            : status === 'pending'
                ? 'bg-warning-50 text-warning-700'
                : 'bg-gray-100 text-gray-600';

    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-theme-xs font-medium ${className}`}>
            {status === 'failed' ? <AlertTriangle size={13} /> : null}
            {status}
        </span>
    );
}
