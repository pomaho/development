import { router } from '@inertiajs/react';
import { LogOut, Menu } from 'lucide-react';
import type { AmoAccountSummary } from '../types';

type Props = {
    currentAccount: AmoAccountSummary | null | undefined;
    accounts: AmoAccountSummary[];
    dashboardHref: string;
    logoutHref: string;
    onMenuOpen: () => void;
};

export function Header({ currentAccount, accounts, dashboardHref, logoutHref, onMenuOpen }: Props) {
    const handleAccountChange = (value: string) => {
        if (value) {
            window.location.href = value;
        }
    };

    return (
        <header className="sticky top-0 z-30 border-b border-gray-200 bg-white">
            <div className="flex flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center lg:justify-between lg:px-6">
                <div className="flex items-center justify-between gap-3">
                    <button
                        className="flex size-10 items-center justify-center rounded-lg border border-gray-200 text-gray-500 shadow-theme-xs hover:bg-gray-50 lg:hidden"
                        type="button"
                        aria-label="Открыть боковую панель"
                        onClick={onMenuOpen}
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
                        value={currentAccount?.dashboard_url || dashboardHref}
                        onChange={(e) => handleAccountChange(e.target.value)}
                        aria-label="Выбрать аккаунт"
                    >
                        <option value={dashboardHref}>Все аккаунты</option>
                        {accounts.map((account: AmoAccountSummary) => (
                            <option key={account.id} value={account.dashboard_url}>
                                {account.name}
                            </option>
                        ))}
                    </select>

                    {currentAccount && (
                        <span className="inline-flex h-9 items-center rounded-lg bg-gray-100 px-3 text-theme-sm font-medium text-gray-700">
                            {currentAccount.base_domain}
                        </span>
                    )}

                    <button
                        className="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50"
                        type="button"
                        onClick={() => router.post(logoutHref)}
                    >
                        <LogOut size={16} />
                        Выйти
                    </button>
                </div>
            </div>
        </header>
    );
}
