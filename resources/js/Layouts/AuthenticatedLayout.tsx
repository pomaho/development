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
    Plug,
    Settings2,
    ShieldCheck,
    SquareCheckBig,
    UserRoundCog,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
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
            leads: string;
            pipelines: string;
            catalogs?: string;
            responsibility_redistribution?: string;
            task_statistics?: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        } | null;
    };
    children: ReactNode;
};

export default function AuthenticatedLayout({ title, breadcrumbs, links, children }: Props) {
    const { props, url } = usePage<SharedProps>();
    const user = props.auth.user;
    const accounts = props.amoAccounts || [];
    const currentAccount = props.currentAmoAccount;
    const isAdmin = user?.role === 'admin';

    const currentLinks = links.current_account;
    const catalogsHref = currentAccount ? currentLinks?.catalogs || `/amo-accounts/${currentAccount.id}/catalogs` : null;
    const responsibilityHref = currentAccount ? currentLinks?.responsibility_redistribution || `/amo-accounts/${currentAccount.id}/responsibility-redistribution` : null;
    const taskStatisticsHref = currentAccount ? currentLinks?.task_statistics || `/amo-accounts/${currentAccount.id}/task-statistics` : null;
    const navLinks: NavLink[] = [
        {
            label: 'Dashboard',
            href: currentAccount && currentLinks ? currentLinks.dashboard : links.dashboard,
            icon: <LayoutDashboard size={16} />,
            active: url === '/dashboard' || url.endsWith('/dashboard'),
        },
        {
            label: 'Клиенты',
            href: links.amo_accounts,
            icon: <BriefcaseBusiness size={16} />,
            active: url.startsWith('/amo-accounts') && ! currentAccount,
        },
        {
            label: 'OAuth amoCRM',
            href: links.oauth,
            icon: <Plug size={16} />,
            active: url.startsWith('/amo-oauth/external'),
            adminOnly: true,
        },
        ...(currentAccount && currentLinks ? [
            { label: 'Users audit', href: currentLinks.users, icon: <Users size={16} />, active: url.endsWith('/users') },
            { label: 'Сделки', href: currentLinks.leads, icon: <ClipboardList size={16} />, active: url.endsWith('/leads') },
            { label: 'Воронки', href: currentLinks.pipelines, icon: <BarChart3 size={16} />, active: url.includes('/pipelines') },
            ...(catalogsHref ? [{ label: 'Списки', href: catalogsHref, icon: <ListTree size={16} />, active: url.includes('/catalogs') }] : []),
            ...(responsibilityHref ? [{ label: 'Ответственные', href: responsibilityHref, icon: <UserRoundCog size={16} />, active: url.includes('/responsibility-redistribution') }] : []),
            ...(taskStatisticsHref ? [{ label: 'Задачи', href: taskStatisticsHref, icon: <SquareCheckBig size={16} />, active: url.includes('/task-statistics') }] : []),
            { label: 'CRM-аудит', href: currentLinks.crm_audit, icon: <ShieldCheck size={16} />, active: url.includes('/crm-audit') },
            { label: 'Интеграции', href: currentLinks.integrations, icon: <Settings2 size={16} />, active: url.endsWith('/integrations') },
            { label: 'Dashboard-блоки', href: currentLinks.widgets, icon: <Blocks size={16} />, active: url.endsWith('/widgets') },
        ] : []),
        {
            label: 'API-логи',
            href: links.api_logs,
            icon: <FileText size={16} />,
            active: url.startsWith('/logs/api'),
        },
    ];

    const selectAccount = (value: string) => {
        if (value) {
            window.location.href = value;
        }
    };

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <a className="flex items-center gap-2 text-lg font-semibold text-slate-950" href={links.dashboard}>
                        <Activity className="text-cyan-600" size={20} />
                        {title}
                    </a>

                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <select
                            className="rounded border-slate-300 text-sm"
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
                            <span className="rounded bg-blue-50 px-2 py-1 text-blue-800">{currentAccount.base_domain}</span>
                        ) : null}
                    </div>

                    <nav className="flex flex-wrap items-center gap-2 text-sm">
                        {navLinks
                            .filter((link) => ! link.adminOnly || isAdmin)
                            .map((link) => (
                                <a
                                    key={link.label}
                                    className={link.active
                                        ? 'inline-flex items-center gap-1.5 rounded bg-blue-50 px-2 py-1 font-medium text-blue-800'
                                        : 'inline-flex items-center gap-1.5 rounded px-2 py-1 text-slate-600 hover:bg-slate-100 hover:text-blue-700'}
                                    href={link.href}
                                >
                                    {link.icon}
                                    {link.label}
                                </a>
                            ))}
                        <span className="rounded bg-slate-100 px-2 py-1">{user?.role}</span>
                        <button
                            className="inline-flex items-center gap-1.5 rounded px-2 py-1 text-slate-600 hover:bg-red-50 hover:text-red-700"
                            type="button"
                            onClick={() => router.post(links.logout)}
                        >
                            <LogOut size={16} />
                            Выйти
                        </button>
                    </nav>
                </div>
            </header>

            <main className="mx-auto max-w-7xl px-4 py-6">
                <nav className="mb-4 flex flex-wrap items-center gap-2 text-sm text-slate-500" aria-label="Хлебные крошки">
                    {breadcrumbs.map((crumb, index) => (
                        <span className="inline-flex items-center gap-2" key={`${crumb.label}-${index}`}>
                            {index > 0 ? <ChevronRight size={14} /> : null}
                            {crumb.href && index < breadcrumbs.length - 1 ? (
                                <a className="text-blue-700 hover:text-blue-900" href={crumb.href}>
                                    {crumb.label}
                                </a>
                            ) : (
                                <span className={index === breadcrumbs.length - 1 ? 'font-medium text-slate-700' : ''}>{crumb.label}</span>
                            )}
                        </span>
                    ))}
                </nav>

                {children}
            </main>
        </div>
    );
}
