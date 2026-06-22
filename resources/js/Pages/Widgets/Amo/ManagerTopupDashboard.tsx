import { createPortal } from 'react-dom';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { ChevronDown, ChevronUp, ExternalLink, Loader2, X } from 'lucide-react';

// ─── Types ────────────────────────────────────────────────────────────────────

type Account = { name: string; base_domain: string };

type Period = { from: string; to: string; label: string };

type Props = {
    account: Account;
    period: Period;
    links: { self: string; data: string; leads: string };
};

type ManagerSummary = {
    name: string;
    topupTotal: number;
    dealCount: number;
};

type MonthlyTotal = {
    month: string;
    total: number;
};

type BreakdownData = {
    summary: { managerCount: number; dealCount: number; topupTotal: number };
    allManagerNames: string[];
    managers: ManagerSummary[];
    monthlyBreakdown: MonthlyTotal[];
};

type LeadItem = {
    id: string | number;
    name: string;
    manager: string;
    topup_date: string | null;
    price: number;
    prepayment: number;
    topup: number;
};

type LoadState<T> =
    | { status: 'loading' }
    | { status: 'error'; message: string }
    | { status: 'loaded'; data: T };

// ─── Helpers ──────────────────────────────────────────────────────────────────

function rub(value: number): string {
    if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1).replace('.0', '')} млн ₽`;
    if (value >= 1_000) return `${(value / 1_000).toFixed(0)} тыс ₽`;
    return `${value.toLocaleString('ru-RU')} ₽`;
}

function rubFull(value: number): string {
    return value.toLocaleString('ru-RU') + ' ₽';
}

function monthLabel(ym: string): string {
    const [y, m] = ym.split('-');
    const months = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    return `${months[parseInt(m, 10) - 1]} ${y}`;
}

function buildUrl(base: string, params: Record<string, string | number | undefined>): string {
    const url = new URL(base, window.location.origin);
    for (const [k, v] of Object.entries(params)) {
        if (v !== undefined && v !== '') url.searchParams.set(k, String(v));
    }
    return url.toString();
}

// ─── Leads Modal ──────────────────────────────────────────────────────────────

function LeadsModal({ leadsUrl, from, to, manager, baseDomain, onClose }: {
    leadsUrl: string;
    from: string;
    to: string;
    manager: string;
    baseDomain: string;
    onClose: () => void;
}) {
    const [state, setState] = useState<LoadState<{ leads: LeadItem[]; total: number; limited: boolean; limit: number }>>({ status: 'loading' });

    useEffect(() => {
        const url = buildUrl(leadsUrl, { from, to, manager });
        fetch(url)
            .then((r) => r.json())
            .then((json) => setState({ status: 'loaded', data: json.data }))
            .catch(() => setState({ status: 'error', message: 'Ошибка загрузки' }));
    }, [leadsUrl, from, to, manager]);

    const title = manager ? `Сделки: ${manager}` : 'Все сделки';

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm" onClick={onClose}>
            <div className="flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <div className="text-xs font-semibold uppercase tracking-wider text-indigo-500">Доплаты по менеджерам</div>
                        <h3 className="mt-0.5 text-base font-bold text-gray-900">{title}</h3>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <X className="size-5" />
                    </button>
                </div>
                <div className="overflow-y-auto">
                    {state.status === 'loading' && (
                        <div className="flex items-center justify-center gap-2 py-16 text-slate-400">
                            <Loader2 className="size-5 animate-spin" />
                            <span className="text-sm">Загрузка...</span>
                        </div>
                    )}
                    {state.status === 'error' && (
                        <div className="px-6 py-10 text-center text-sm text-red-500">{state.message}</div>
                    )}
                    {state.status === 'loaded' && (
                        <>
                            {state.data.limited && (
                                <div className="border-b border-amber-100 bg-amber-50 px-6 py-2 text-xs text-amber-700">
                                    Показаны {state.data.limit} из {state.data.total} сделок — отсортированы по сумме доплаты
                                </div>
                            )}
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-5 py-3">Сделка</th>
                                        <th className="px-4 py-3">Менеджер</th>
                                        <th className="px-4 py-3">Дата</th>
                                        <th className="px-4 py-3 text-right">Бюджет</th>
                                        <th className="px-4 py-3 text-right">Аванс</th>
                                        <th className="px-4 py-3 text-right">Доплата</th>
                                        <th className="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {state.data.leads.length > 0 ? state.data.leads.map((lead) => (
                                        <tr className="hover:bg-violet-50/40 transition-colors" key={lead.id}>
                                            <td className="px-5 py-3 font-medium text-gray-900">{lead.name}</td>
                                            <td className="px-4 py-3 text-slate-600">{lead.manager}</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-slate-500">{lead.topup_date ?? '—'}</td>
                                            <td className="px-4 py-3 text-right tabular-nums text-slate-600">{rubFull(lead.price)}</td>
                                            <td className="px-4 py-3 text-right tabular-nums text-slate-600">{rubFull(lead.prepayment)}</td>
                                            <td className="px-4 py-3 text-right font-bold tabular-nums text-emerald-700">{rubFull(lead.topup)}</td>
                                            <td className="px-4 py-3">
                                                <a href={`https://${baseDomain}/leads/detail/${lead.id}`} target="_blank" rel="noopener noreferrer" className="text-slate-400 hover:text-indigo-500">
                                                    <ExternalLink className="size-4" />
                                                </a>
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td className="px-5 py-8 text-center text-slate-400" colSpan={7}>Нет сделок за выбранный период</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </>
                    )}
                </div>
                {state.status === 'loaded' && (
                    <div className="border-t border-slate-100 bg-slate-50 px-6 py-3 text-right text-xs text-slate-400">
                        Всего сделок: {state.data.total}
                    </div>
                )}
            </div>
        </div>,
        document.body,
    );
}

// ─── Charts ───────────────────────────────────────────────────────────────────

function ManagerBarChart({ managers, onManagerClick }: { managers: ManagerSummary[]; onManagerClick: (name: string) => void }) {
    const max = Math.max(...managers.map((m) => m.topupTotal), 1);
    return (
        <div className="divide-y divide-slate-100">
            {managers.map((m, i) => {
                const pct = Math.round((m.topupTotal / max) * 100);
                return (
                    <div className="flex items-center gap-4 px-5 py-3" key={m.name}>
                        <div className="w-5 shrink-0 text-right text-xs font-bold text-slate-300 tabular-nums">{i + 1}</div>
                        <div className="min-w-0 flex-1">
                            <div className="mb-1.5 flex items-center justify-between gap-3">
                                <span className="truncate text-sm font-semibold text-gray-900">{m.name}</span>
                                <button
                                    type="button"
                                    className="shrink-0 rounded-full bg-indigo-600 px-3 py-0.5 text-xs font-bold tabular-nums text-white hover:bg-indigo-700 transition-colors"
                                    onClick={() => onManagerClick(m.name)}
                                >
                                    {rub(m.topupTotal)}
                                </button>
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    className="h-full rounded-full bg-gradient-to-r from-violet-500 to-indigo-600 transition-all duration-500"
                                    style={{ width: `${pct}%` }}
                                />
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function MonthlyColumnChart({ monthly }: { monthly: MonthlyTotal[] }) {
    const max = Math.max(...monthly.map((m) => m.total), 1);
    return (
        <div className="flex h-48 items-end gap-2 px-5 pb-2">
            {monthly.map((m) => {
                const pct = Math.round((m.total / max) * 100);
                return (
                    <div className="flex flex-1 flex-col items-center gap-1" key={m.month}>
                        <span className="text-xs font-semibold text-indigo-700">{rub(m.total)}</span>
                        <div className="flex w-full flex-col justify-end" style={{ height: '120px' }}>
                            <div
                                className="w-full rounded-t-lg bg-gradient-to-t from-violet-500 to-indigo-400 transition-all duration-500"
                                style={{ height: `${pct}%`, minHeight: '4px' }}
                            />
                        </div>
                        <span className="text-xs text-slate-500 whitespace-nowrap">{monthLabel(m.month)}</span>
                    </div>
                );
            })}
        </div>
    );
}

// ─── Manager Filter ───────────────────────────────────────────────────────────

function ManagerFilter({ allNames, selected, onChange }: {
    allNames: string[];
    selected: string[];
    onChange: (names: string[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        function handleClick(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    const label = selected.length === 0 ? 'Все менеджеры' : selected.length === 1 ? selected[0] : `${selected.length} менеджера`;

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                className="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:border-indigo-300 hover:text-indigo-700 transition-colors"
                onClick={() => setOpen((v) => !v)}
            >
                {label}
                {open ? <ChevronUp className="size-4 text-slate-400" /> : <ChevronDown className="size-4 text-slate-400" />}
            </button>
            {open && (
                <div className="absolute left-0 top-full z-20 mt-1 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                    <div className="border-b border-slate-100 px-3 py-2 flex items-center justify-between">
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Менеджеры</span>
                        <button
                            type="button"
                            className="text-xs text-indigo-600 hover:text-indigo-700"
                            onClick={() => onChange([])}
                        >
                            Сбросить
                        </button>
                    </div>
                    <div className="max-h-64 overflow-y-auto py-1">
                        {allNames.map((name) => {
                            const checked = selected.includes(name);
                            return (
                                <label key={name} className="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-slate-50">
                                    <input
                                        type="checkbox"
                                        checked={checked}
                                        className="size-4 rounded border-slate-300 text-indigo-600"
                                        onChange={() => {
                                            const next = checked
                                                ? selected.filter((s) => s !== name)
                                                : [...selected, name];
                                            onChange(next);
                                        }}
                                    />
                                    <span className="text-sm text-gray-700">{name}</span>
                                </label>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}

// ─── Summary Cards ────────────────────────────────────────────────────────────

function SummaryCard({ label, value, sub, onClick }: { label: string; value: ReactNode; sub?: string; onClick?: () => void }) {
    const cls = 'rounded-2xl bg-white p-5 shadow-md ring-1 ring-slate-200/60';
    if (onClick) {
        return (
            <button type="button" className={`${cls} text-left hover:ring-indigo-300 transition-shadow w-full`} onClick={onClick}>
                <div className="text-xs font-semibold uppercase tracking-wider text-slate-400">{label}</div>
                <div className="mt-2 text-3xl font-extrabold tabular-nums text-gray-900">{value}</div>
                {sub && <div className="mt-1 text-xs text-slate-400">{sub}</div>}
            </button>
        );
    }
    return (
        <div className={cls}>
            <div className="text-xs font-semibold uppercase tracking-wider text-slate-400">{label}</div>
            <div className="mt-2 text-3xl font-extrabold tabular-nums text-gray-900">{value}</div>
            {sub && <div className="mt-1 text-xs text-slate-400">{sub}</div>}
        </div>
    );
}

// ─── Sections ─────────────────────────────────────────────────────────────────

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="overflow-hidden rounded-2xl bg-white shadow-md ring-1 ring-slate-200/60">
            <div className="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-5 py-4">
                <h2 className="text-base font-bold text-gray-900">{title}</h2>
            </div>
            {children}
        </div>
    );
}

function ManagerSummaryTable({ managers, onRowClick }: { managers: ManagerSummary[]; onRowClick: (name: string) => void }) {
    const grandTotal = managers.reduce((s, m) => s + m.topupTotal, 0);
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th className="px-5 py-3">Менеджер</th>
                        <th className="px-4 py-3 text-right">Сделок</th>
                        <th className="px-4 py-3 text-right">Сумма доплат</th>
                        <th className="px-4 py-3 text-right">Доля</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {managers.map((m) => {
                        const share = grandTotal > 0 ? Math.round((m.topupTotal / grandTotal) * 100) : 0;
                        return (
                            <tr key={m.name} className="hover:bg-violet-50/40 transition-colors">
                                <td className="px-5 py-3 font-semibold text-gray-900">{m.name}</td>
                                <td className="px-4 py-3 text-right tabular-nums text-slate-600">{m.dealCount}</td>
                                <td className="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        className="font-bold tabular-nums text-indigo-600 hover:text-indigo-800 transition-colors"
                                        onClick={() => onRowClick(m.name)}
                                    >
                                        {rubFull(m.topupTotal)}
                                    </button>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <div className="flex items-center justify-end gap-2">
                                        <div className="h-1.5 w-20 overflow-hidden rounded-full bg-slate-100">
                                            <div className="h-full rounded-full bg-indigo-500" style={{ width: `${share}%` }} />
                                        </div>
                                        <span className="text-xs tabular-nums text-slate-500">{share}%</span>
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                    {managers.length > 1 && (
                        <tr className="bg-slate-50 font-bold">
                            <td className="px-5 py-3 text-gray-900">Итого</td>
                            <td className="px-4 py-3 text-right tabular-nums text-gray-900">{managers.reduce((s, m) => s + m.dealCount, 0)}</td>
                            <td className="px-4 py-3 text-right tabular-nums text-gray-900">{rubFull(grandTotal)}</td>
                            <td className="px-4 py-3 text-right text-slate-400">100%</td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function Skeleton() {
    return (
        <div className="animate-pulse space-y-4">
            <div className="grid grid-cols-3 gap-4">
                {[1, 2, 3].map((i) => <div key={i} className="h-28 rounded-2xl bg-slate-200" />)}
            </div>
            <div className="h-64 rounded-2xl bg-slate-200" />
            <div className="h-48 rounded-2xl bg-slate-200" />
        </div>
    );
}

// ─── Main Component ───────────────────────────────────────────────────────────

export default function ManagerTopupDashboard({ account, period, links }: Props) {
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);
    const [selectedManagers, setSelectedManagers] = useState<string[]>([]);
    const [state, setState] = useState<LoadState<BreakdownData>>({ status: 'loading' });
    const [modal, setModal] = useState<{ manager: string } | null>(null);

    const loadData = (f: string, t: string, managers: string[]) => {
        setState({ status: 'loading' });
        const url = buildUrl(links.data, {
            from: f,
            to: t,
            managers: managers.join(','),
        });
        fetch(url)
            .then((r) => r.json())
            .then((json) => setState({ status: 'loaded', data: json.data }))
            .catch((err) => setState({ status: 'error', message: String(err) }));
    };

    useEffect(() => {
        loadData(from, to, selectedManagers);
    }, []);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        loadData(from, to, selectedManagers);
    };

    const handleManagerFilter = (managers: string[]) => {
        setSelectedManagers(managers);
        loadData(from, to, managers);
    };

    const data = state.status === 'loaded' ? state.data : null;

    return (
        <div className="min-h-screen bg-slate-50">
            {/* Header */}
            <div className="border-b border-slate-200 bg-white shadow-sm">
                <div className="mx-auto max-w-7xl px-4 py-5">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="bg-gradient-to-r from-violet-600 to-indigo-500 bg-clip-text text-xs font-bold uppercase tracking-wider text-transparent">
                                {account.name}
                            </div>
                            <h1 className="mt-1 text-2xl font-extrabold text-gray-900">Доплаты по менеджерам</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                Детальный список сделок за выбранный период — по каждому менеджеру отдельно
                            </p>
                        </div>
                        {data && (
                            <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-500">
                                Данные по полю «Месяц предполагаемой доплаты»
                            </div>
                        )}
                    </div>

                    {/* Filters */}
                    <form onSubmit={handleSubmit} className="mt-5 flex flex-wrap items-end gap-3">
                        <label className="block">
                            <span className="text-xs font-medium text-slate-500">Дата от</span>
                            <input
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                                className="mt-1 block h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm text-gray-700 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-1 focus:ring-indigo-500/30"
                            />
                        </label>
                        <label className="block">
                            <span className="text-xs font-medium text-slate-500">Дата до</span>
                            <input
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                                className="mt-1 block h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm text-gray-700 shadow-sm focus:border-indigo-300 focus:outline-none focus:ring-1 focus:ring-indigo-500/30"
                            />
                        </label>
                        <button
                            type="submit"
                            className="h-10 rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors"
                        >
                            Показать
                        </button>
                        {data && (
                            <ManagerFilter
                                allNames={data.allManagerNames}
                                selected={selectedManagers}
                                onChange={handleManagerFilter}
                            />
                        )}
                    </form>
                </div>
            </div>

            {/* Content */}
            <div className="mx-auto max-w-7xl space-y-6 px-4 py-6">
                {state.status === 'loading' && <Skeleton />}

                {state.status === 'error' && (
                    <div className="rounded-2xl bg-red-50 p-6 text-center text-sm text-red-600 ring-1 ring-red-200">
                        Ошибка загрузки данных. Попробуйте обновить страницу.
                    </div>
                )}

                {data && (
                    <>
                        {/* Summary cards */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <SummaryCard label="Менеджеров" value={data.summary.managerCount} />
                            <SummaryCard label="Сделок" value={data.summary.dealCount} />
                            <SummaryCard
                                label="Итого доплат"
                                value={<span className="text-emerald-600">{rub(data.summary.topupTotal)}</span>}
                                sub="нажмите чтобы открыть список"
                                onClick={() => setModal({ manager: '' })}
                            />
                        </div>

                        {/* Manager bar chart */}
                        {data.managers.length > 0 ? (
                            <Section title="Доплаты по менеджерам">
                                <div className="pb-4 pt-2">
                                    <ManagerBarChart
                                        managers={data.managers}
                                        onManagerClick={(name) => setModal({ manager: name })}
                                    />
                                </div>
                            </Section>
                        ) : (
                            <div className="rounded-2xl bg-white p-10 text-center text-sm text-slate-400 ring-1 ring-slate-200">
                                Нет данных за выбранный период
                            </div>
                        )}

                        {/* Monthly chart */}
                        {data.monthlyBreakdown.length > 0 && (
                            <Section title="Доплаты по месяцам">
                                <div className="pt-4">
                                    <MonthlyColumnChart monthly={data.monthlyBreakdown} />
                                </div>
                            </Section>
                        )}

                        {/* Manager summary table */}
                        {data.managers.length > 0 && (
                            <Section title="Сводка по менеджерам">
                                <ManagerSummaryTable
                                    managers={data.managers}
                                    onRowClick={(name) => setModal({ manager: name })}
                                />
                            </Section>
                        )}
                    </>
                )}
            </div>

            {modal !== null && (
                <LeadsModal
                    leadsUrl={links.leads}
                    from={from}
                    to={to}
                    manager={modal.manager}
                    baseDomain={account.base_domain}
                    onClose={() => setModal(null)}
                />
            )}
        </div>
    );
}
