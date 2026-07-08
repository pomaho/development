import { usePage } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    Blocks,
    BriefcaseBusiness,
    ChevronRight,
    ClipboardList,
    Contact,
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
    Webhook,
} from 'lucide-react';
import type { ReactNode } from 'react';
import type { SharedProps } from '../types';
import ru from '../i18n/ru';
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
            contacts?: string;
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
            webhooks?: string;
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
    const contactsHref = href(currentLinks?.contacts, base ? `${base}/contacts` : null);
    const crmFieldsHref = base ? `${base}/crm-audit/fields` : null;

    const n = ru.nav;

    const mainLinks: NavLink[] = [
        {
            label: n.dashboard,
            href: currentLinks ? currentLinks.dashboard : links.dashboard,
            icon: <LayoutDashboard />,
            active: url === '/dashboard' || url.endsWith('/dashboard'),
        },
        {
            label: n.clients,
            href: links.amo_accounts,
            icon: <BriefcaseBusiness />,
            active: url.startsWith('/amo-accounts') && !currentLinks,
        },
        {
            label: n.oauthAmo,
            href: links.oauth,
            icon: <Plug />,
            active: url.startsWith('/amo-oauth/external'),
            adminOnly: true,
        },
    ];

    const accountOverviewLinks: NavLink[] = currentLinks ? [
        { label: n.clientOverview, href: currentLinks.dashboard, icon: <LayoutDashboard />, active: url.endsWith('/dashboard') },
        { label: n.accountProfile, href: currentLinks.show, icon: <BriefcaseBusiness />, active: url === `${base}` },
    ] : [];

    const crmDataLinks: NavLink[] = currentLinks ? [
        ...(dataCenterHref ? [{ label: n.dataCenter, href: dataCenterHref, icon: <Database />, active: url === `${base}/data` }] : []),
        { label: n.leads, href: currentLinks.leads, icon: <ClipboardList />, active: url.endsWith('/leads') },
        ...(contactsHref ? [{ label: n.contacts, href: contactsHref, icon: <Contact />, active: url.endsWith('/contacts') }] : []),
        ...(taskStatisticsHref ? [{ label: n.tasks, href: taskStatisticsHref, icon: <SquareCheckBig />, active: url.includes('/task-statistics') }] : []),
        ...(eventsSyncHref ? [{ label: n.events, href: eventsSyncHref, icon: <Activity />, active: url.includes('/events-sync') }] : []),
    ] : [];

    const crmStructureLinks: NavLink[] = currentLinks ? [
        ...(crmStructureCenterHref ? [{ label: n.structureCenter, href: crmStructureCenterHref, icon: <ListTree />, active: url === `${base}/crm-structure` }] : []),
        { label: n.pipelines, href: currentLinks.pipelines, icon: <BarChart3 />, active: url.includes('/pipelines') },
        ...(crmFieldsHref ? [{ label: n.crmFields, href: crmFieldsHref, icon: <ShieldCheck />, active: url.includes('/crm-audit/fields') }] : []),
        ...(catalogsHref ? [{ label: n.lists, href: catalogsHref, icon: <ListTree />, active: url.includes('/catalogs') }] : []),
        { label: n.users, href: currentLinks.users, icon: <Users />, active: url.endsWith('/users') },
        { label: n.roles, href: currentLinks.roles, icon: <UserRoundCog />, active: url.endsWith('/roles') },
    ] : [];

    const syncLinks: NavLink[] = currentLinks ? [
        ...(syncCenterHref ? [{ label: n.syncCenter, href: syncCenterHref, icon: <Activity />, active: url === `${base}/sync` }] : []),
        ...(leadSyncSchedulesHref ? [{ label: n.leadSyncSchedules, href: leadSyncSchedulesHref, icon: <RefreshCcw />, active: url.includes('/lead-sync-schedules') }] : []),
        { label: n.crmAudit, href: currentLinks.crm_audit, icon: <ShieldCheck />, active: url.includes('/crm-audit') && !url.includes('/crm-audit/fields') },
    ] : [];

    const automationLinks: NavLink[] = currentLinks ? [
        ...(automationCenterHref ? [{ label: n.automationCenter, href: automationCenterHref, icon: <Settings2 />, active: url === `${base}/automation` }] : []),
        ...(responsibilityHref ? [{ label: n.responsible, href: responsibilityHref, icon: <UserRoundCog />, active: url.includes('/responsibility-redistribution') }] : []),
    ] : [];

    const analyticsLinks: NavLink[] = currentLinks ? [
        ...(analyticsCenterHref ? [{ label: n.analyticsCenter, href: analyticsCenterHref, icon: <BarChart3 />, active: url === `${base}/analytics` }] : []),
    ] : [];

    const webhooksHref = currentLinks?.webhooks ?? (base ? `${base}/webhooks` : null);

    const integrationLinks: NavLink[] = currentLinks ? [
        { label: n.integrationsList, href: currentLinks.integrations, icon: <Settings2 />, active: url.endsWith('/integrations') },
        { label: n.dashboardBlocks, href: currentLinks.widgets, icon: <Blocks />, active: url.endsWith('/widgets') },
        ...(webhooksHref ? [{ label: n.webhooks, href: webhooksHref, icon: <Webhook />, active: url.endsWith('/webhooks') }] : []),
    ] : [];

    const systemLinks: NavLink[] = [
        { label: n.apiLogs, href: links.api_logs, icon: <FileText />, active: url.startsWith('/logs/api') },
    ];

    return [
        { label: n.main, items: mainLinks },
        ...(accountOverviewLinks.length > 0 ? [{ label: n.accountOverview, items: accountOverviewLinks }] : []),
        ...(crmDataLinks.length > 0 ? [{ label: n.crmData, items: crmDataLinks }] : []),
        ...(crmStructureLinks.length > 0 ? [{ label: n.crmStructure, items: crmStructureLinks }] : []),
        ...(syncLinks.length > 0 ? [{ label: n.sync, items: syncLinks }] : []),
        ...(automationLinks.length > 0 ? [{ label: n.automation, items: automationLinks }] : []),
        ...(analyticsLinks.length > 0 ? [{ label: n.analytics, items: analyticsLinks }] : []),
        ...(integrationLinks.length > 0 ? [{ label: n.integrations, items: integrationLinks }] : []),
        { label: n.system, items: systemLinks },
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
                    <nav className="mb-6 flex flex-wrap items-center gap-2 text-theme-sm text-gray-500" aria-label={ru.breadcrumbs.label}>
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
