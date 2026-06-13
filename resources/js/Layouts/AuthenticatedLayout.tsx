import { router, usePage } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    Blocks,
    BriefcaseBusiness,
    ChevronRight,
    ClipboardList,
    FileText,
    ListTree,
    LayoutDashboard,
    LogOut,
    Menu,
    Plug,
    RefreshCcw,
    Settings2,
    ShieldCheck,
    SquareCheckBig,
    UserRoundCog,
    Users,
    X,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import type { AmoAccountSummary, SharedProps } from '../types';

type Breadcrumb = {
    label: string;
    href?: string | null;
};

type NavLink = {
    label: string;
    href: string;
    icon: ReactNode;
    active: boolean;
    adminOnly?: boolean;
};

type NavSection = {
    label: string;
    items: NavLink[];
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

export default function AuthenticatedLayout({ title, breadcrumbs, links, children }: Props) {
    const { props, url } = usePage<SharedProps>();
    const [isMobileOpen, setIsMobileOpen] = useState(false);
    const user = props.auth.user;
    const accounts = props.amoAccounts || [];
    const currentAccount = props.currentAmoAccount;
    const isAdmin = user?.role === 'admin';

    const currentLinks = links.current_account;
    const catalogsHref = currentAccount ? currentLinks?.catalogs || `/amo-accounts/${currentAccount.id}/catalogs` : null;
    const responsibilityHref = currentAccount ? currentLinks?.responsibility_redistribution || `/amo-accounts/${currentAccount.id}/responsibility-redistribution` : null;
    const taskStatisticsHref = currentAccount ? currentLinks?.task_statistics || `/amo-accounts/${currentAccount.id}/task-statistics` : null;
    const eventsSyncHref = currentAccount ? currentLinks?.events_sync || `/amo-accounts/${currentAccount.id}/events-sync` : null;
    const leadSyncSchedulesHref = currentAccount ? currentLinks?.lead_sync_schedules || `/amo-accounts/${currentAccount.id}/lead-sync-schedules` : null;
    const syncCenterHref = currentAccount ? currentLinks?.sync_center || `/amo-accounts/${currentAccount.id}/sync` : null;
    const analyticsCenterHref = currentAccount ? currentLinks?.analytics_center || `/amo-accounts/${currentAccount.id}/analytics` : null;
    const automationCenterHref = currentAccount ? currentLinks?.automation_center || `/amo-accounts/${currentAccount.id}/automation` : null;
    const crmStructureCenterHref = currentAccount ? currentLinks?.crm_structure_center || `/amo-accounts/${currentAccount.id}/crm-structure` : null;
    const crmFieldsHref = currentAccount ? `/amo-accounts/${currentAccount.id}/crm-audit/fields` : null;

    const mainLinks: NavLink[] = [
        {
            label: 'Dashboard',
            href: currentAccount && currentLinks ? currentLinks.dashboard : links.dashboard,
            icon: <LayoutDashboard />,
            active: url === '/dashboard' || url.endsWith('/dashboard'),
        },
        {
            label: 'Клиенты',
            href: links.amo_accounts,
            icon: <BriefcaseBusiness />,
            active: url.startsWith('/amo-accounts') && ! currentAccount,
        },
        {
            label: 'OAuth amoCRM',
            href: links.oauth,
            icon: <Plug />,
            active: url.startsWith('/amo-oauth/external'),
            adminOnly: true,
        },
    ];

    const accountOverviewLinks: NavLink[] = currentAccount && currentLinks ? [
        { label: 'Обзор клиента', href: currentLinks.dashboard, icon: <LayoutDashboard />, active: url.endsWith('/dashboard') },
        { label: 'Профиль аккаунта', href: currentLinks.show, icon: <BriefcaseBusiness />, active: url === `/amo-accounts/${currentAccount.id}` },
    ] : [];

    const crmDataLinks: NavLink[] = currentAccount && currentLinks ? [
        { label: 'Сделки', href: currentLinks.leads, icon: <ClipboardList />, active: url.endsWith('/leads') },
    ] : [];

    const crmStructureLinks: NavLink[] = currentAccount && currentLinks ? [
        ...(crmStructureCenterHref ? [{ label: 'Центр структуры', href: crmStructureCenterHref, icon: <ListTree />, active: url === `/amo-accounts/${currentAccount.id}/crm-structure` }] : []),
        { label: 'Воронки', href: currentLinks.pipelines, icon: <BarChart3 />, active: url.includes('/pipelines') },
        ...(crmFieldsHref ? [{ label: 'Поля CRM', href: crmFieldsHref, icon: <ShieldCheck />, active: url.includes('/crm-audit/fields') }] : []),
        ...(catalogsHref ? [{ label: 'Списки', href: catalogsHref, icon: <ListTree />, active: url.includes('/catalogs') }] : []),
        { label: 'Пользователи', href: currentLinks.users, icon: <Users />, active: url.endsWith('/users') },
        { label: 'Роли и права', href: currentLinks.roles, icon: <UserRoundCog />, active: url.endsWith('/roles') },
    ] : [];

    const syncLinks: NavLink[] = currentAccount && currentLinks ? [
        ...(syncCenterHref ? [{ label: 'Центр синхронизации', href: syncCenterHref, icon: <Activity />, active: url === `/amo-accounts/${currentAccount.id}/sync` }] : []),
        ...(leadSyncSchedulesHref ? [{ label: 'Расписания сделок', href: leadSyncSchedulesHref, icon: <RefreshCcw />, active: url.includes('/lead-sync-schedules') }] : []),
        { label: 'CRM-аудит', href: currentLinks.crm_audit, icon: <ShieldCheck />, active: url.includes('/crm-audit') && ! url.includes('/crm-audit/fields') },
        ...(eventsSyncHref ? [{ label: 'События', href: eventsSyncHref, icon: <Activity />, active: url.includes('/events-sync') }] : []),
    ] : [];

    const automationLinks: NavLink[] = currentAccount && currentLinks ? [
        ...(automationCenterHref ? [{ label: 'Центр автоматизации', href: automationCenterHref, icon: <Settings2 />, active: url === `/amo-accounts/${currentAccount.id}/automation` }] : []),
        ...(responsibilityHref ? [{ label: 'Ответственные', href: responsibilityHref, icon: <UserRoundCog />, active: url.includes('/responsibility-redistribution') }] : []),
    ] : [];

    const analyticsLinks: NavLink[] = currentAccount && currentLinks ? [
        ...(analyticsCenterHref ? [{ label: 'Центр аналитики', href: analyticsCenterHref, icon: <BarChart3 />, active: url === `/amo-accounts/${currentAccount.id}/analytics` }] : []),
        ...(taskStatisticsHref ? [{ label: 'Задачи', href: taskStatisticsHref, icon: <SquareCheckBig />, active: url.includes('/task-statistics') }] : []),
    ] : [];

    const integrationLinks: NavLink[] = currentAccount && currentLinks ? [
        { label: 'Интеграции', href: currentLinks.integrations, icon: <Settings2 />, active: url.endsWith('/integrations') },
        { label: 'Dashboard-блоки', href: currentLinks.widgets, icon: <Blocks />, active: url.endsWith('/widgets') },
    ] : [];

    const systemLinks: NavLink[] = [
        {
            label: 'API-логи',
            href: links.api_logs,
            icon: <FileText />,
            active: url.startsWith('/logs/api'),
        },
    ];

    const sections: NavSection[] = [
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

    const selectAccount = (value: string) => {
        if (value) {
            window.location.href = value;
        }
    };

    const visibleItems = (items: NavLink[]) => items.filter((link) => ! link.adminOnly || isAdmin);

    return (
        <div className="min-h-screen bg-gray-50 text-gray-900">
            {isMobileOpen ? (
                <button
                    className="fixed inset-0 z-40 bg-gray-900/40 lg:hidden"
                    type="button"
                    aria-label="Close sidebar backdrop"
                    onClick={() => setIsMobileOpen(false)}
                />
            ) : null}

            <aside
                className={`fixed left-0 top-0 z-50 flex h-screen w-[290px] flex-col border-r border-gray-200 bg-white px-5 transition-transform duration-300 ease-in-out lg:translate-x-0 ${
                    isMobileOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex h-16 items-center justify-between">
                    <a className="flex min-w-0 items-center gap-3" href={links.dashboard}>
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500">
                            <Activity size={22} />
                        </span>
                        <span className="min-w-0">
                            <span className="block truncate text-theme-xl font-semibold text-gray-900">{title}</span>
                            <span className="block text-theme-xs text-gray-500">amoCRM operations</span>
                        </span>
                    </a>
                    <button
                        className="flex size-10 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 lg:hidden"
                        type="button"
                        aria-label="Close sidebar"
                        onClick={() => setIsMobileOpen(false)}
                    >
                        <X size={20} />
                    </button>
                </div>

                <div className="custom-scrollbar flex-1 overflow-y-auto py-6">
                    <nav className="space-y-6">
                        {sections.map((section) => {
                            const items = visibleItems(section.items);

                            if (items.length === 0) {
                                return null;
                            }

                            return (
                                <div key={section.label}>
                                    <div className="mb-3 px-3 text-theme-xs font-semibold uppercase tracking-wide text-gray-400">
                                        {section.label}
                                    </div>
                                    <ul className="flex flex-col gap-1.5">
                                        {items.map((link) => (
                                            <li key={link.label}>
                                                <a
                                                    className={`menu-item group ${link.active ? 'menu-item-active' : 'menu-item-inactive'}`}
                                                    href={link.href}
                                                    onClick={() => setIsMobileOpen(false)}
                                                >
                                                    <span className={`menu-item-icon-size ${link.active ? 'menu-item-icon-active' : 'menu-item-icon-inactive'}`}>
                                                        {link.icon}
                                                    </span>
                                                    <span>{link.label}</span>
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            );
                        })}
                    </nav>
                </div>

                <div className="border-t border-gray-200 py-4">
                    <div className="rounded-xl bg-gray-50 p-3">
                        <div className="text-theme-sm font-medium text-gray-900">{user?.name}</div>
                        <div className="mt-0.5 text-theme-xs text-gray-500">{user?.email}</div>
                        <div className="mt-3 inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-medium text-brand-700">
                            {user?.role}
                        </div>
                    </div>
                </div>
            </aside>

            <div className="flex min-h-screen flex-col transition-all duration-300 ease-in-out lg:ml-[290px]">
                <header className="sticky top-0 z-30 border-b border-gray-200 bg-white">
                    <div className="flex flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center lg:justify-between lg:px-6">
                        <div className="flex items-center justify-between gap-3">
                            <button
                                className="flex size-10 items-center justify-center rounded-lg border border-gray-200 text-gray-500 shadow-theme-xs hover:bg-gray-50 lg:hidden"
                                type="button"
                                aria-label="Toggle sidebar"
                                onClick={() => setIsMobileOpen(true)}
                            >
                                <Menu size={20} />
                            </button>
                            <div className="min-w-0">
                                <div className="text-theme-xs font-semibold uppercase tracking-wide text-gray-400">Workspace</div>
                                <div className="truncate text-theme-xl font-semibold text-gray-900">
                                    {currentAccount ? currentAccount.name : 'Все аккаунты'}
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <select
                                className="h-11 rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10"
                                value={currentAccount?.dashboard_url || links.dashboard}
                                onChange={(event) => selectAccount(event.target.value)}
                            >
                                <option value={links.dashboard}>Все аккаунты</option>
                                {accounts.map((account: AmoAccountSummary) => (
                                    <option key={account.id} value={account.dashboard_url}>
                                        {account.name}
                                    </option>
                                ))}
                            </select>
                            {currentAccount ? (
                                <span className="inline-flex h-9 items-center rounded-lg bg-gray-100 px-3 text-theme-sm font-medium text-gray-700">
                                    {currentAccount.base_domain}
                                </span>
                            ) : null}
                            <button
                                className="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50"
                                type="button"
                                onClick={() => router.post(links.logout)}
                            >
                                <LogOut size={16} />
                                Выйти
                            </button>
                        </div>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-[1536px] flex-1 px-4 py-6 md:px-6">
                    <nav className="mb-6 flex flex-wrap items-center gap-2 text-theme-sm text-gray-500" aria-label="Хлебные крошки">
                        {breadcrumbs.map((crumb, index) => (
                            <span className="inline-flex items-center gap-2" key={`${crumb.label}-${index}`}>
                                {index > 0 ? <ChevronRight size={14} /> : null}
                                {crumb.href && index < breadcrumbs.length - 1 ? (
                                    <a className="font-medium text-brand-600 hover:text-brand-700" href={crumb.href}>
                                        {crumb.label}
                                    </a>
                                ) : (
                                    <span className={index === breadcrumbs.length - 1 ? 'font-medium text-gray-800' : ''}>{crumb.label}</span>
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
