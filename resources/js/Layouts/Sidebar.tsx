import { Activity, X } from 'lucide-react';
import type { ReactNode } from 'react';
import ru from '../i18n/ru';

export type NavLink = {
    label: string;
    href: string;
    icon: ReactNode;
    active: boolean;
    adminOnly?: boolean;
};

export type NavSection = {
    label: string;
    items: NavLink[];
};

type User = {
    name: string;
    email: string;
    role: string;
};

type Props = {
    title: string;
    dashboardHref: string;
    sections: NavSection[];
    user: User | null;
    isOpen: boolean;
    isAdmin: boolean;
    onClose: () => void;
};

export function Sidebar({ title, dashboardHref, sections, user, isOpen, isAdmin, onClose }: Props) {
    const visibleItems = (items: NavLink[]) => items.filter((link) => !link.adminOnly || isAdmin);

    return (
        <>
            {isOpen && (
                <button
                    className="fixed inset-0 z-40 bg-gray-900/40 lg:hidden"
                    type="button"
                    aria-label={ru.sidebar.closePanel}
                    onClick={onClose}
                />
            )}

            <aside
                className={`fixed left-0 top-0 z-50 flex h-screen w-[290px] flex-col border-r border-gray-200 bg-white px-5 transition-transform duration-300 ease-in-out lg:translate-x-0 ${
                    isOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex h-16 items-center justify-between">
                    <a className="flex min-w-0 items-center gap-3" href={dashboardHref}>
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500">
                            <Activity size={22} />
                        </span>
                        <span className="min-w-0">
                            <span className="block truncate text-theme-xl font-semibold text-gray-900">{title}</span>
                            <span className="block text-theme-xs text-gray-500">{ru.sidebar.appSubtitle}</span>
                        </span>
                    </a>
                    <button
                        className="flex size-10 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 lg:hidden"
                        type="button"
                        aria-label={ru.sidebar.closeSidebar}
                        onClick={onClose}
                    >
                        <X size={20} />
                    </button>
                </div>

                <div className="custom-scrollbar flex-1 overflow-y-auto py-6">
                    <nav className="space-y-6" aria-label={ru.sidebar.mainNavLabel}>
                        {sections.map((section) => {
                            const items = visibleItems(section.items);
                            if (items.length === 0) return null;

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
                                                    onClick={onClose}
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

                {user && (
                    <div className="border-t border-gray-200 py-4">
                        <div className="rounded-xl bg-gray-50 p-3">
                            <div className="text-theme-sm font-medium text-gray-900">{user.name}</div>
                            <div className="mt-0.5 text-theme-xs text-gray-500">{user.email}</div>
                            <div className="mt-3 inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-medium text-brand-700">
                                {user.role}
                            </div>
                        </div>
                    </div>
                )}
            </aside>
        </>
    );
}
