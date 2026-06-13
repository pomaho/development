import { BarChart3, ListTree, ShieldCheck, UserRoundCog, Users } from 'lucide-react';
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
        statuses_count: number;
        custom_fields_count: number;
        catalogs_count: number;
        users_count: number;
        roles_count: number;
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

export default function CrmStructureCenterIndex({ account, summary, links }: Props) {
    const accountLinks = links.current_account;
    const sections = [
        {
            title: 'Воронки и этапы',
            description: 'Список воронок, этапов, архивности и настроек pipeline.',
            href: accountLinks.pipelines,
            icon: <BarChart3 size={20} />,
        },
        {
            title: 'Поля CRM',
            description: 'ID и типы полей сделок и контактов для настройки отчетов.',
            href: accountLinks.crm_fields,
            icon: <ShieldCheck size={20} />,
        },
        {
            title: 'Списки и каталоги',
            description: 'Каталоги, элементы списков и связанные справочники.',
            href: accountLinks.catalogs,
            icon: <ListTree size={20} />,
        },
        {
            title: 'Пользователи',
            description: 'Пользователи amoCRM, группы, активность и администраторы.',
            href: accountLinks.users,
            icon: <Users size={20} />,
        },
        {
            title: 'Роли и права',
            description: 'Роли amoCRM, users.rights и доступы по сущностям.',
            href: accountLinks.roles,
            icon: <UserRoundCog size={20} />,
        },
    ];

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: accountLinks.show },
                { label: 'Центр структуры CRM' },
            ]}
            links={links}
        >
            <div className="mb-6">
                <p className="text-theme-sm font-medium text-brand-600">CRM structure</p>
                <h1 className="mt-1 text-2xl font-semibold text-gray-900">Центр структуры CRM: {account.name}</h1>
                <div className="mt-1 text-theme-sm text-gray-500">{account.base_domain}</div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <DashboardMetric label="Воронки" value={summary.pipelines_count} />
                <DashboardMetric label="Этапы" value={summary.statuses_count} />
                <DashboardMetric label="Поля" value={summary.custom_fields_count} />
                <DashboardMetric label="Списки" value={summary.catalogs_count} />
                <DashboardMetric label="Пользователи" value={summary.users_count} />
                <DashboardMetric label="Роли" value={summary.roles_count} />
            </div>

            <section className="mt-6 grid gap-4 xl:grid-cols-3">
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
        </AuthenticatedLayout>
    );
}
