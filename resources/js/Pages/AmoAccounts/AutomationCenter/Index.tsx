import { AlertTriangle, ArrowRightLeft, GitBranchPlus, UserRoundCog } from 'lucide-react';
import DashboardMetric from '../../../Components/DashboardMetric';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Props = {
    account: Account;
    summary: {
        pipelines_count: number;
        leads_count: number;
        responsibility_runs_count: number;
        failed_responsibility_runs_count: number;
    };
    can: {
        sync: boolean;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        current_account: {
            dashboard: string;
            show: string;
            automation_center: string;
            analytics_center: string;
            sync_center: string;
            users: string;
            roles: string;
            leads: string;
            pipelines: string;
            pipelines_create: string;
            pipelines_transfer_leads: string;
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

export default function AutomationCenterIndex({ account, summary, can, links }: Props) {
    const accountLinks = links.current_account;
    const actions = [
        {
            title: 'Создать воронку',
            description: 'Создание новой воронки и этапов в amoCRM через официальный API.',
            href: accountLinks.pipelines_create,
            icon: <GitBranchPlus size={20} />,
            disabled: ! can.sync,
        },
        {
            title: 'Перенос сделок',
            description: 'Перенос сделок между воронками с сопоставлением этапов.',
            href: accountLinks.pipelines_transfer_leads,
            icon: <ArrowRightLeft size={20} />,
            disabled: ! can.sync,
        },
        {
            title: 'Перераспределение ответственных',
            description: 'Массовое распределение контактов, сделок и задач между активными пользователями.',
            href: accountLinks.responsibility_redistribution,
            icon: <UserRoundCog size={20} />,
            disabled: ! can.sync,
        },
    ];

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: accountLinks.show },
                { label: 'Центр автоматизации' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="text-theme-sm font-medium text-brand-600">Automation center</p>
                    <h1 className="mt-1 text-2xl font-semibold text-gray-900">Центр автоматизации: {account.name}</h1>
                    <div className="mt-1 text-theme-sm text-gray-500">{account.base_domain}</div>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <DashboardMetric label="Воронки" value={summary.pipelines_count} />
                <DashboardMetric label="Сделки в базе" value={summary.leads_count} />
                <DashboardMetric label="Запуски ответственных" value={summary.responsibility_runs_count} />
                <DashboardMetric label="Ошибки запусков" value={summary.failed_responsibility_runs_count} />
            </div>

            <section className="mt-6 rounded-2xl border border-warning-200 bg-warning-50 p-5 text-warning-800">
                <div className="flex items-start gap-3">
                    <AlertTriangle className="mt-0.5 shrink-0" size={20} />
                    <div>
                        <h2 className="font-semibold">Автоматизация изменяет данные в amoCRM</h2>
                        <p className="mt-1 text-theme-sm">
                            Эти операции должны запускаться осознанно: они создают воронки, меняют этапы сделок или ответственных пользователей.
                        </p>
                    </div>
                </div>
            </section>

            <section className="mt-6 grid gap-4 xl:grid-cols-3">
                {actions.map((action) => (
                    <a
                        className={`group rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm transition ${
                            action.disabled ? 'pointer-events-none opacity-55' : 'hover:border-brand-300 hover:shadow-theme-md'
                        }`}
                        href={action.href}
                        key={action.title}
                    >
                        <div className="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                            {action.icon}
                        </div>
                        <h2 className="mt-4 text-lg font-semibold text-gray-900 group-hover:text-brand-600">{action.title}</h2>
                        <p className="mt-2 text-theme-sm text-gray-500">{action.description}</p>
                        {action.disabled ? (
                            <div className="mt-4 inline-flex rounded-full bg-gray-100 px-3 py-1 text-theme-xs font-medium text-gray-500">
                                Только admin
                            </div>
                        ) : null}
                    </a>
                ))}
            </section>
        </AuthenticatedLayout>
    );
}
