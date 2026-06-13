import { BarChart3, Blocks, ClipboardList, SquareCheckBig } from 'lucide-react';
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
        leads_count: number;
        tasks_count: number;
        events_count: number;
        dashboard_widgets_count: number;
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
            analytics_center: string;
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

export default function AnalyticsCenterIndex({ account, summary, links }: Props) {
    const accountLinks = links.current_account;
    const reports = [
        {
            title: 'Отчет по задачам',
            description: 'Выполненные просроченные задачи, группировка по пользователям и группам.',
            href: accountLinks.task_statistics,
            icon: <SquareCheckBig size={20} />,
        },
        {
            title: 'Сделки и рекрутеры',
            description: 'База для отчетов по рекрутерам, источникам, командам и городам.',
            href: accountLinks.leads,
            icon: <ClipboardList size={20} />,
        },
        {
            title: 'Dashboard-блоки amoCRM',
            description: 'Iframe-виджеты и настройки отчетов для рабочего стола amoCRM.',
            href: accountLinks.widgets,
            icon: <Blocks size={20} />,
        },
    ];

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: accountLinks.show },
                { label: 'Центр аналитики' },
            ]}
            links={links}
        >
            <div className="mb-6">
                <p className="text-theme-sm font-medium text-brand-600">Analytics center</p>
                <h1 className="mt-1 text-2xl font-semibold text-gray-900">Центр аналитики: {account.name}</h1>
                <div className="mt-1 text-theme-sm text-gray-500">{account.base_domain}</div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <DashboardMetric label="Сделки в базе" value={summary.leads_count} />
                <DashboardMetric label="Задачи в базе" value={summary.tasks_count} />
                <DashboardMetric label="События в базе" value={summary.events_count} />
                <DashboardMetric label="Dashboard-блоки" value={summary.dashboard_widgets_count} />
            </div>

            <section className="mt-6 grid gap-4 xl:grid-cols-3">
                {reports.map((report) => (
                    <a className="group rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm transition hover:border-brand-300 hover:shadow-theme-md" href={report.href} key={report.title}>
                        <div className="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                            {report.icon}
                        </div>
                        <h2 className="mt-4 text-lg font-semibold text-gray-900 group-hover:text-brand-600">{report.title}</h2>
                        <p className="mt-2 text-theme-sm text-gray-500">{report.description}</p>
                    </a>
                ))}
            </section>

            <section className="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm">
                <div className="flex items-start gap-3">
                    <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gray-50 text-gray-600">
                        <BarChart3 size={20} />
                    </div>
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">Принцип отчетов</h2>
                        <p className="mt-2 max-w-3xl text-theme-sm text-gray-500">
                            Отчеты должны строиться из локальных snapshots и агрегатов. amoCRM API используется для синхронизации,
                            webhook-обновлений и восстановления данных, а не для тяжелых запросов при каждом открытии отчета.
                        </p>
                    </div>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
