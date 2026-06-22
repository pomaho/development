import { createPortal } from 'react-dom';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { CalendarDays, ChevronDown, ChevronUp, ExternalLink, Inbox, X } from 'lucide-react';

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

// ─── Primitive UI components ──────────────────────────────────────────────────

function ReportSection({ eyebrow, title, description, aside, children }: {
    eyebrow: string; title: string; description?: string; aside?: ReactNode; children: ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-2xl bg-white shadow-md ring-1 ring-slate-200/60">
            <div className="grid gap-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-5 py-5 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <div className="bg-gradient-to-r from-violet-600 to-indigo-500 bg-clip-text text-xs font-bold uppercase tracking-wider text-transparent">
                        {eyebrow}
                    </div>
                    <h2 className="mt-1.5 text-lg font-bold text-gray-900">{title}</h2>
                    {description ? <p className="mt-1 text-sm text-slate-500">{description}</p> : null}
                </div>
                {aside}
            </div>
            {children}
        </section>
    );
}

function AccentSummary({ label, value, note, tone }: {
    label: string; value: ReactNode; note?: string; tone: 'brand' | 'warning' | 'success';
}) {
    const cls = tone === 'warning'
        ? 'bg-gradient-to-br from-amber-400 to-orange-500 shadow-amber-200/60'
        : tone === 'success'
        ? 'bg-gradient-to-br from-emerald-400 to-green-600 shadow-emerald-200/60'
        : 'bg-gradient-to-br from-violet-500 to-indigo-600 shadow-violet-200/60';
    return (
        <div className={`rounded-2xl px-5 py-4 text-right text-white shadow-lg ${cls}`}>
            <div className="text-xs font-semibold uppercase tracking-wider text-white/70">{label}</div>
            <div className="mt-1 text-4xl font-extrabold tabular-nums">{value}</div>
            {note ? <div className="mt-1 text-xs text-white/70">{note}</div> : null}
        </div>
    );
}

function SectionSkeleton({ rows }: { rows: number }) {
    return (
        <section className="overflow-hidden rounded-2xl bg-white shadow-md ring-1 ring-slate-200/60">
            <div className="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-5 py-5">
                <div className="h-3 w-24 animate-pulse rounded-full bg-slate-200" />
                <div className="mt-3 h-5 w-64 animate-pulse rounded-full bg-slate-200" />
                <div className="mt-2 h-3 w-96 animate-pulse rounded-full bg-slate-100" />
            </div>
            <div className="divide-y divide-slate-100">
                {Array.from({ length: rows }).map((_, i) => (
                    <div className="flex items-center gap-4 px-5 py-4" key={i}>
                        <div className="h-4 flex-1 animate-pulse rounded-full bg-slate-100" style={{ animationDelay: `${i * 60}ms` }} />
                        <div className="h-4 w-16 animate-pulse rounded-full bg-slate-100" style={{ animationDelay: `${i * 60 + 30}ms` }} />
                        <div className="h-4 w-24 animate-pulse rounded-full bg-slate-100" style={{ animationDelay: `${i * 60 + 60}ms` }} />
                    </div>
                ))}
            </div>
        </section>
    );
}

function SectionError({ message }: { message: string }) {
    return (
        <section className="overflow-hidden rounded-2xl bg-white shadow-md ring-1 ring-red-200/60">
            <div className="flex flex-col items-center gap-3 px-5 py-10 text-center text-sm text-slate-400">
                <Inbox className="size-10 text-red-200" />
                Ошибка загрузки данных: {message}
            </div>
        </section>
    );
}

function EmptyState({ children }: { children: ReactNode }) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-2xl bg-slate-50 p-10 text-center text-sm text-slate-400 ring-1 ring-slate-200">
            <Inbox className="size-10 text-slate-300" />
            {children}
        </div>
    );
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

    useEffect(() => {
        const handleKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [onClose]);

    const title = manager ? `Сделки: ${manager}` : 'Все сделки';

    return createPortal(
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="topup-modal-title"
        >
            <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
            <div className="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-violet-500">Доплаты по менеджерам</p>
                        <h3 id="topup-modal-title" className="mt-0.5 font-bold text-gray-900">{title}</h3>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Закрыть"
                    >
                        <X className="size-5" />
                    </button>
                </div>
                <div className="overflow-y-auto">
                    {state.status === 'loading' && (
                        <div className="flex items-center justify-center gap-2 py-16 text-slate-400">
                            <svg className="mr-2 size-5 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                            </svg>
                            Загрузка...
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
                                <thead className="sticky top-0 bg-slate-50">
                                    <tr>
                                        <th className="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Сделка</th>
                                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Менеджер</th>
                                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Дата</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Бюджет</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Аванс</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Доплата</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {state.data.leads.length > 0 ? state.data.leads.map((lead) => (
                                        <tr className="transition-colors hover:bg-violet-50/50" key={lead.id}>
                                            <td className="px-5 py-3.5 font-semibold text-gray-900">{lead.name}</td>
                                            <td className="px-4 py-3.5 text-slate-600">{lead.manager}</td>
                                            <td className="px-4 py-3.5 whitespace-nowrap tabular-nums text-slate-500">{lead.topup_date ?? '—'}</td>
                                            <td className="px-4 py-3.5 text-right tabular-nums text-slate-600">{rubFull(lead.price)}</td>
                                            <td className="px-4 py-3.5 text-right tabular-nums text-slate-600">{rubFull(lead.prepayment)}</td>
                                            <td className="px-4 py-3.5 text-right font-bold tabular-nums text-emerald-700">{rubFull(lead.topup)}</td>
                                            <td className="px-4 py-3.5">
                                                <a
                                                    href={`https://${baseDomain}/leads/detail/${lead.id}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-slate-400 transition-colors hover:text-indigo-500"
                                                >
                                                    <ExternalLink className="size-4" />
                                                </a>
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td className="px-5 py-8 text-center text-slate-400" colSpan={7}>
                                                Нет сделок за выбранный период
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </>
                    )}
                </div>
                {state.status === 'loaded' && (
                    <div className="border-t border-slate-100 px-6 py-3 text-xs text-slate-400">
                        {state.data.total} сделок · отсортировано по убыванию доплаты
                    </div>
                )}
            </div>
        </div>,
        document.body,
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
                className="flex h-10 items-center gap-2 rounded-lg border-white/10 bg-white/10 px-4 text-sm font-medium text-white focus:border-violet-400 focus:ring-violet-400/20 hover:bg-white/20 transition-colors"
                onClick={() => setOpen((v) => !v)}
            >
                {label}
                {open ? <ChevronUp className="size-4 text-white/60" /> : <ChevronDown className="size-4 text-white/60" />}
            </button>
            {open && (
                <div className="absolute left-0 top-full z-20 mt-1 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                    <div className="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Менеджеры</span>
                        <button
                            type="button"
                            className="text-xs text-violet-600 hover:text-violet-700"
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

// ─── Section components ───────────────────────────────────────────────────────

function ManagerBarChart({ managers, onManagerClick }: { managers: ManagerSummary[]; onManagerClick: (name: string) => void }) {
    const max = Math.max(...managers.map((m) => m.topupTotal), 1);
    return (
        <div className="divide-y divide-slate-100 pb-5">
            {managers.map((m, i) => {
                const pct = Math.round((m.topupTotal / max) * 100);
                return (
                    <div className="flex items-center gap-4 px-5 py-3.5" key={m.name}>
                        <div className="w-6 shrink-0 text-right text-xs font-bold text-slate-400 tabular-nums">{i + 1}</div>
                        <div className="min-w-0 flex-1">
                            <div className="mb-1.5 flex items-center justify-between gap-3">
                                <span className="truncate font-semibold text-gray-900">{m.name}</span>
                                <button
                                    type="button"
                                    className="shrink-0 rounded-full bg-emerald-500 px-3 py-0.5 text-xs font-bold tabular-nums text-white transition-colors hover:bg-emerald-600"
                                    onClick={() => onManagerClick(m.name)}
                                >
                                    {rub(m.topupTotal)}
                                </button>
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-emerald-50">
                                <div
                                    className="h-full rounded-full bg-gradient-to-r from-emerald-400 to-green-500 transition-all duration-500"
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
        <div className="flex h-52 items-end gap-2 px-5 pb-3 pt-4">
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
                        <span className="whitespace-nowrap text-xs text-slate-500">{monthLabel(m.month)}</span>
                    </div>
                );
            })}
        </div>
    );
}

function ManagerSummaryTable({ managers, onRowClick }: { managers: ManagerSummary[]; onRowClick: (name: string) => void }) {
    const grandTotal = managers.reduce((s, m) => s + m.topupTotal, 0);
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                    <tr>
                        <th className="px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Менеджер</th>
                        <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Сделок</th>
                        <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Сумма доплат</th>
                        <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Доля</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {managers.map((m) => {
                        const share = grandTotal > 0 ? Math.round((m.topupTotal / grandTotal) * 100) : 0;
                        return (
                            <tr key={m.name} className="transition-colors hover:bg-violet-50/50">
                                <td className="px-5 py-3.5 font-semibold text-gray-900">{m.name}</td>
                                <td className="px-4 py-3.5 text-right tabular-nums text-slate-600">{m.dealCount}</td>
                                <td className="px-4 py-3.5 text-right">
                                    <button
                                        type="button"
                                        className="font-mono font-semibold tabular-nums text-indigo-700 underline-offset-2 hover:underline"
                                        onClick={() => onRowClick(m.name)}
                                    >
                                        {rubFull(m.topupTotal)}
                                    </button>
                                </td>
                                <td className="px-4 py-3.5">
                                    <div className="flex min-w-40 items-center gap-2.5">
                                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                className="h-2 rounded-full bg-gradient-to-r from-violet-400 to-indigo-600"
                                                style={{ width: `${share}%` }}
                                            />
                                        </div>
                                        <span className="w-12 text-right text-sm font-semibold tabular-nums text-slate-600">{share}%</span>
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                    {managers.length > 1 && (
                        <tr className="bg-slate-50">
                            <td className="px-5 py-3.5 font-bold text-gray-900">Итого</td>
                            <td className="px-4 py-3.5 text-right font-bold tabular-nums text-gray-900">{managers.reduce((s, m) => s + m.dealCount, 0)}</td>
                            <td className="px-4 py-3.5 text-right font-bold tabular-nums text-gray-900">{rubFull(grandTotal)}</td>
                            <td className="px-4 py-3.5 text-xs text-slate-400">100%</td>
                        </tr>
                    )}
                </tbody>
            </table>
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
        const url = buildUrl(links.data, { from: f, to: t, managers: managers.join(',') });
        fetch(url)
            .then((r) => r.json())
            .then((json) => setState({ status: 'loaded', data: json.data }))
            .catch((err) => setState({ status: 'error', message: String(err) }));
    };

    useEffect(() => {
        loadData(from, to, selectedManagers);
    // eslint-disable-next-line react-hooks/exhaustive-deps
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
    const periodLabel = `${period.label}: ${period.from} — ${period.to}`;

    return (
        <div className="min-h-screen bg-slate-100 px-3 py-5 text-gray-900 sm:px-5">
            <div className="mx-auto max-w-7xl space-y-5">

                {/* ── Header ─────────────────────────────────────────────── */}
                <header className="overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 shadow-2xl ring-1 ring-white/10">
                    <div className="grid gap-5 px-6 py-6 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div className="flex min-w-0 items-center gap-5">
                            <div className="flex size-14 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20">
                                <img className="size-9 object-contain" src="/assets/anyservice-logo.png" alt="AnyService" />
                            </div>
                            <div className="min-w-0">
                                <div className="inline-flex items-center rounded-full bg-violet-500/20 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-violet-300 ring-1 ring-violet-400/30">
                                    BI аналитика CRM
                                </div>
                                <h1 className="mt-2 text-2xl font-bold text-white">Доплаты по менеджерам</h1>
                                <div className="mt-1.5 flex flex-wrap items-center gap-2 text-sm text-slate-400">
                                    <span>{account.name}</span>
                                    <span className="size-1 rounded-full bg-slate-600" />
                                    <span>{account.base_domain}</span>
                                    <span className="size-1 rounded-full bg-slate-600" />
                                    <span>{periodLabel}</span>
                                </div>
                            </div>
                        </div>

                        <form className="rounded-xl bg-white/5 p-4 ring-1 ring-white/10" onSubmit={handleSubmit}>
                            <div className="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <CalendarDays className="size-3.5 text-violet-400" />
                                Период
                            </div>
                            <div className="mb-3 grid grid-cols-[1fr_1fr_auto] gap-2">
                                <input
                                    className="h-10 rounded-lg border-white/10 bg-white/10 px-3 text-sm text-white focus:border-violet-400 focus:ring-violet-400/20"
                                    type="date"
                                    value={from}
                                    onChange={(e) => setFrom(e.target.value)}
                                />
                                <input
                                    className="h-10 rounded-lg border-white/10 bg-white/10 px-3 text-sm text-white focus:border-violet-400 focus:ring-violet-400/20"
                                    type="date"
                                    value={to}
                                    onChange={(e) => setTo(e.target.value)}
                                />
                                <button
                                    className="h-10 rounded-lg bg-gradient-to-r from-violet-600 to-indigo-600 px-4 text-sm font-semibold text-white shadow-lg shadow-violet-500/30 hover:from-violet-500 hover:to-indigo-500"
                                    type="submit"
                                >
                                    Показать
                                </button>
                            </div>
                            {data && data.allManagerNames.length > 0 && (
                                <ManagerFilter
                                    allNames={data.allManagerNames}
                                    selected={selectedManagers}
                                    onChange={handleManagerFilter}
                                />
                            )}
                        </form>
                    </div>
                </header>

                {/* ── Loading ─────────────────────────────────────────────── */}
                {state.status === 'loading' && (
                    <>
                        <SectionSkeleton rows={4} />
                        <SectionSkeleton rows={3} />
                        <SectionSkeleton rows={5} />
                    </>
                )}

                {/* ── Error ───────────────────────────────────────────────── */}
                {state.status === 'error' && <SectionError message={state.message} />}

                {/* ── Data ────────────────────────────────────────────────── */}
                {data && (
                    <>
                        {/* Manager bar chart */}
                        {data.managers.length > 0 ? (
                            <ReportSection
                                eyebrow="Доплаты по менеджерам"
                                title="Рейтинг менеджеров по сумме доплат"
                                description="Доплата = Бюджет сделки − Сумма предоплаты. Учитываются только сделки с положительной доплатой. Нажмите на сумму — откроется список сделок."
                                aside={
                                    <button type="button" onClick={() => setModal({ manager: '' })}>
                                        <AccentSummary
                                            label="Итого доплат"
                                            value={rub(data.summary.topupTotal)}
                                            note={`${data.summary.dealCount} сделок · ${data.summary.managerCount} менеджеров`}
                                            tone="success"
                                        />
                                    </button>
                                }
                            >
                                <ManagerBarChart managers={data.managers} onManagerClick={(name) => setModal({ manager: name })} />
                            </ReportSection>
                        ) : (
                            <ReportSection
                                eyebrow="Доплаты по менеджерам"
                                title="Рейтинг менеджеров по сумме доплат"
                            >
                                <div className="px-5 py-8">
                                    <EmptyState>Нет сделок с доплатой за выбранный период</EmptyState>
                                </div>
                            </ReportSection>
                        )}

                        {/* Monthly chart */}
                        {data.monthlyBreakdown.length > 0 && (
                            <ReportSection
                                eyebrow="Динамика по месяцам"
                                title="Доплаты по месяцам"
                                description="Распределение суммарной доплаты по месяцу предполагаемой доплаты."
                            >
                                <MonthlyColumnChart monthly={data.monthlyBreakdown} />
                            </ReportSection>
                        )}

                        {/* Summary table */}
                        {data.managers.length > 0 && (
                            <ReportSection
                                eyebrow="Сводная таблица"
                                title="Разбивка по менеджерам"
                                description="Нажмите на сумму доплат — откроется детальный список сделок менеджера."
                            >
                                <ManagerSummaryTable managers={data.managers} onRowClick={(name) => setModal({ manager: name })} />
                            </ReportSection>
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
