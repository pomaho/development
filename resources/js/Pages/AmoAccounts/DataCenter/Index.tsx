import { Activity, ClipboardList, Database, RefreshCcw, SquareCheckBig } from 'lucide-react';
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
        contacts_count: number;
        companies_count: number;
        tasks_count: number;
        events_count: number;
        last_synced_at: string | null;
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
            data_center: string;
            crm_structure_center: string;
            automation_center: string;
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
            crm_fields: string;
            integrations: string;
            widgets: string;
        };
    };
};

export default function DataCenterIndex({ account, summary, links }: Props) {
    const accountLinks = links.current_account;
    const sections = [
        {
            title: 'Сделки',
            description: 'Поиск, фильтры, экспорт и проверка сохраненных сделок.',
            href: accountLinks.leads,
            icon: <ClipboardList size={20} />,
        },
        {
            title: 'Расписания загрузки',
            description: 'Периодическая и ручная синхронизация сделок по воронкам.',
            href: accountLinks.lead_sync_schedules,
            icon: <RefreshCcw size={20} />,
        },
        {
            title: 'События amoCRM',
            description: 'История событий для аналитики переходов, изменений и SLA.',
            href: accountLinks.events_sync,
            icon: <Activity size={20} />,
        },
        {
            title: 'Задачи',
            description: 'Статистика выполненных и просроченных задач по пользователям.',
            href: accountLinks.task_statistics,
            icon: <SquareCheckBig size={20} />,
        },
    ];

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: accountLinks.show },
                { label: 'Центр данных CRM' },
            ]}
            links={links}
        >
            <div className="mb-6">
                <p className="text-theme-sm font-medium text-brand-600">CRM data</p>
                <h1 className="mt-1 text-2xl font-semibold text-gray-900">Центр данных CRM: {account.name}</h1>
                <div className="mt-1 text-theme-sm text-gray-500">{account.base_domain}</div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <DashboardMetric label="Сделки" value={summary.leads_count} />
                <DashboardMetric label="Контакты" value={summary.contacts_count} />
                <DashboardMetric label="Компании" value={summary.companies_count} />
                <DashboardMetric label="Задачи" value={summary.tasks_count} />
                <DashboardMetric label="События" value={summary.events_count} />
                <DashboardMetric label="Последняя загрузка" value={summary.last_synced_at || '-'} />
            </div>

            <section className="mt-6 grid gap-4 xl:grid-cols-4">
                {sections.map((section) => (
                    <a className="group rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm transition hover:border-brand-300 hover:shadow-theme-md" href={section.href} key={section.title}>
                        <div className="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                            {section.icon}
                        </div>
                        <h2 className="mt-4 text-lg font-semibold text-gray-900 group-hover:text-brand-600">{section.title}</h2>
                        <p className="mt-2 text-theme-sm text-gray-500">{section.description}</p>
                    </a>
                ))}
            </section>

            <section className="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm">
                <div className="flex items-start gap-3">
                    <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gray-50 text-gray-600">
                        <Database size={20} />
                    </div>
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">Локальный слой данных</h2>
                        <p className="mt-2 max-w-3xl text-theme-sm text-gray-500">
                            Эта страница показывает только данные, уже сохраненные в базе сервиса. Регулярные задачи, webhook и ручные загрузки
                            обновляют snapshots, а отчеты строятся поверх этого локального слоя без тяжелых запросов в amoCRM при открытии страниц.
                        </p>
                    </div>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
