import { usePage } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    Blocks,
    BriefcaseBusiness,
    ChevronRight,
    ClipboardList,
    Database,
    FileText,
    ListTree,
    LayoutDashboard,
    Plug,
    RefreshCcw,
    Settings2,
    ShieldCheck,
    SquareCheckBig,
    UserRoundCog,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import type { SharedProps } from '../types';
import { useSidebar } from '../hooks/useSidebar';
import { Header } from './Header';
import { Sidebar, type NavLink, type NavSection } from './Sidebar';

type Breadcrumb = {
    label: string;
    href?: string | null;
};

type Props = {
    title: string;
    breadcrumbs: Breadcrumb[];
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        current_account?: {
            dashboard: string;
            show: string;
            users: string;
            roles: string;
            leads: string;
            data_center?: string;
            pipelines: string;
            catalogs?: string;
            responsibility_redistribution?: string;
            task_statistics?: string;
            events_sync?: string;
            lead_sync_schedules?: string;
            sync_center?: string;
            analytics_center?: string;
            automation_center?: string;
            crm_structure_center?: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        } | null;
    };
    children: ReactNode;
};

function buildSections(links: Props['links'], url: string, isAdmin: boolean): NavSection[] {
    const currentLinks = links.current_account;
    const accountId = url.match(/\/amo-accounts\/(\d+)/)?.[1];
    const base = accountId ? `/amo-accounts/${accountId}` : null;

    const href = (key: string | undefined, fallback: string | null) =>
        currentLinks && base ? (key ?? fallback) : null;

    const catalogsHref = href(currentLinks?.catalogs, base ? `${base}/catalogs` : null);
    const responsibilityHref = href(currentLinks?.responsibility_redistribution, base ? `${base}/responsibility-redistribution` : null);
    const taskStatisticsHref = href(currentLinks?.task_statistics, base ? `${base}/task-statistics` : null);
    const eventsSyncHref = href(currentLinks?.events_sync, base ? `${base}/events-sync` : null);
    const leadSyncSchedulesHref = href(currentLinks?.lead_sync_schedules, base ? `${base}/lead-sync-schedules` : null);
    const syncCenterHref = href(currentLinks?.sync_center, base ? `${base}/sync` : null);
    const analyticsCenterHref = href(currentLinks?.analytics_center, base ? `${base}/analytics` : null);
    const automationCenterHref = href(currentLinks?.automation_center, base ? `${base}/automation` : null);
    const crmStructureCenterHref = href(currentLinks?.crm_structure_center, base ? `${base}/crm-structure` : null);
    const dataCenterHref = href(currentLinks?.data_center, base ? `${base}/data` : null);
    const crmFieldsHref = base ? `${base}/crm-audit/fields` : null;

    const mainLinks: NavLink[] = [
        {
            label: 'Dashboard',
            href: currentLinks ? currentLinks.dashboard : links.dashboard,
            icon: <LayoutDashboard />,
            active: url === '/dashboard' || url.endsWith('/dashboard'),
        },
        {
            label: 'Клиенты',
            href: links.amo_accounts,
            icon: <BriefcaseBusiness />,
            active: url.startsWith('/amo-accounts') && !currentLinks,
        },
        {
            label: 'OAuth amoCRM',
            href: links.oauth,
            icon: <Plug />,
            active: url.startsWith('/amo-oauth/external'),
            adminOnly: true,
        },
    ];

    const accountOverviewLinks: NavLink[] = currentLinks ? [
        { label: 'Обзор клиента', href: currentLinks.dashboard, icon: <LayoutDashboard />, active: url.endsWith('/dashboard') },
        { label: 'Профиль аккаунта', href: currentLinks.show, icon: <BriefcaseBusiness />, active: url === `${base}` },
    ] : [];

    const crmDataLinks: NavLink[] = currentLinks ? [
        ...(dataCenterHref ? [{ label: 'Центр данных', href: dataCenterHref, icon: <Database />, active: url === `${base}/data` }] : []),
        { label: 'Сделки', href: currentLinks.leads, icon: <ClipboardList />, active: url.endsWith('/leads') },
    ] : [];

    const crmStructureLinks: NavLink[] = currentLinks ? [
        ...(crmStructureCenterHref ? [{ label: 'Центр структуры', href: crmStructureCenterHref, icon: <ListTree />, active: url === `${base}/crm-structure` }] : []),
        { label: 'Воронки', href: currentLinks.pipelines, icon: <BarChart3 />, active: url.includes('/pipelines') },
        ...(crmFieldsHref ? [{ label: 'Поля CRM', href: crmFieldsHref, icon: <ShieldCheck />, active: url.includes('/crm-audit/fields') }] : []),
        ...(catalogsHref ? [{ label: 'Списки', href: catalogsHref, icon: <ListTree />, active: url.includes('/catalogs') }] : []),
        { label: 'Пользователи', href: currentLinks.users, icon: <Users />, active: url.endsWith('/users') },
        { label: 'Роли и права', href: currentLinks.roles, icon: <UserRoundCog />, active: url.endsWith('/roles') },
    ] : [];

    const syncLinks: NavLink[] = currentLinks ? [
        ...(syncCenterHref ? [{ label: 'Центр синхронизации', href: syncCenterHref, icon: <Activity />, active: url === `${base}/sync` }] : []),
        ...(leadSyncSchedulesHref ? [{ label: 'Расписания сделок', href: leadSyncSchedulesHref, icon: <RefreshCcw />, active: url.includes('/lead-sync-schedules') }] : []),
        { label: 'CRM-аудит', href: currentLinks.crm_audit, icon: <ShieldCheck />, active: url.includes('/crm-audit') && !url.includes('/crm-audit/fields') },
        ...(eventsSyncHref ? [{ label: 'События', href: eventsSyncHref, icon: <Activity />, active: url.includes('/events-sync') }] : []),
    ] : [];

    const automationLinks: NavLink[] = currentLinks ? [
        ...(automationCenterHref ? [{ label: 'Центр автоматизации', href: automationCenterHref, icon: <Settings2 />, active: url === `${base}/automation` }] : []),
        ...(responsibilityHref ? [{ label: 'Ответственные', href: responsibilityHref, icon: <UserRoundCog />, active: url.includes('/responsibility-redistribution') }] : []),
    ] : [];

    const analyticsLinks: NavLink[] = currentLinks ? [
        ...(analyticsCenterHref ? [{ label: 'Центр аналитики', href: analyticsCenterHref, icon: <BarChart3 />, active: url === `${base}/analytics` }] : []),
        ...(taskStatisticsHref ? [{ label: 'Задачи', href: taskStatisticsHref, icon: <SquareCheckBig />, active: url.includes('/task-statistics') }] : []),
    ] : [];

    const integrationLinks: NavLink[] = currentLinks ? [
        { label: 'Интеграции', href: currentLinks.integrations, icon: <Settings2 />, active: url.endsWith('/integrations') },
        { label: 'Dashboard-блоки', href: currentLinks.widgets, icon: <Blocks />, active: url.endsWith('/widgets') },
    ] : [];

    const systemLinks: NavLink[] = [
        { label: 'API-логи', href: links.api_logs, icon: <FileText />, active: url.startsWith('/logs/api') },
    ];

    return [
        { label: 'Основное', items: mainLinks },
        ...(accountOverviewLinks.length > 0 ? [{ label: 'Обзор аккаунта', items: accountOverviewLinks }] : []),
        ...(crmDataLinks.length > 0 ? [{ label: 'CRM-данные', items: crmDataLinks }] : []),
        ...(crmStructureLinks.length > 0 ? [{ label: 'CRM-структура', items: crmStructureLinks }] : []),
        ...(syncLinks.length > 0 ? [{ label: 'Синхронизация', items: syncLinks }] : []),
        ...(automationLinks.length > 0 ? [{ label: 'Автоматизация', items: automationLinks }] : []),
        ...(analyticsLinks.length > 0 ? [{ label: 'Аналитика', items: analyticsLinks }] : []),
        ...(integrationLinks.length > 0 ? [{ label: 'Интеграции', items: integrationLinks }] : []),
        { label: 'Система', items: systemLinks },
    ];
}

export default function AuthenticatedLayout({ title, breadcrumbs, links, children }: Props) {
    const { props, url } = usePage<SharedProps>();
    const sidebar = useSidebar();
    const user = props.auth.user;
    const accounts = props.amoAccounts || [];
    const currentAccount = props.currentAmoAccount;
    const isAdmin = user?.role === 'admin';

    const sections = buildSections(links, url, isAdmin);

    return (
        <div className="min-h-screen bg-gray-50 text-gray-900">
            <Sidebar
                title={title}
                dashboardHref={links.dashboard}
                sections={sections}
                user={user ?? null}
                isOpen={sidebar.isOpen}
                isAdmin={isAdmin}
                onClose={sidebar.close}
            />

            <div className="flex min-h-screen flex-col transition-all duration-300 ease-in-out lg:ml-[290px]">
                <Header
                    currentAccount={currentAccount}
                    accounts={accounts}
                    dashboardHref={links.dashboard}
                    logoutHref={links.logout}
                    onMenuOpen={sidebar.open}
                />

                <main className="mx-auto w-full max-w-[1536px] flex-1 px-4 py-6 md:px-6">
                    <nav className="mb-6 flex flex-wrap items-center gap-2 text-theme-sm text-gray-500" aria-label="Хлебные крошки">
                        {breadcrumbs.map((crumb, index) => (
                            <span className="inline-flex items-center gap-2" key={`${crumb.label}-${index}`}>
                                {index > 0 && <ChevronRight size={14} />}
                                {crumb.href && index < breadcrumbs.length - 1 ? (
                                    <a className="font-medium text-brand-600 hover:text-brand-700" href={crumb.href}>
                                        {crumb.label}
                                    </a>
                                ) : (
                                    <span className={index === breadcrumbs.length - 1 ? 'font-medium text-gray-800' : ''}>
                                        {crumb.label}
                                    </span>
                                )}
                            </span>
                        ))}
                    </nav>

                    {children}
                </main>
            </div>
        </div>
    );
}
