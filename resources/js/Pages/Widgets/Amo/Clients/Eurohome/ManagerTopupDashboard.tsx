import { createPortal } from 'react-dom';
import { useEffect, useState } from 'react';
import { ExternalLink, X } from 'lucide-react';
import {
    AccentSummary, buildUrl, EmptyState, type LoadState, ReportSection, rub, rubFull,
    SectionError, SectionSkeleton,
} from '../../_shared/uiKit';

// ─── Types ────────────────────────────────────────────────────────────────────

type Account = { name: string; base_domain: string };

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

type DesignerSummary = {
    key: string;
    name: string;
    type: 'contacts' | 'companies';
    category: string;
    budgetTotal: number;
    dealCount: number;
};

type DesignerBreakdownData = {
    summary: { designerCount: number; dealCount: number; budgetTotal: number };
    designers: DesignerSummary[];
};

type DesignerLeadItem = {
    id: string | number;
    name: string;
    closed_date: string | null;
    price: number;
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

function monthLabel(ym: string): string {
    const [y, m] = ym.split('-');
    const months = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    return `${months[parseInt(m, 10) - 1]} ${y}`;
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

function DesignerLeadsModal({ leadsUrl, from, to, designerKey, designerLabel, baseDomain, onClose }: {
    leadsUrl: string;
    from: string;
    to: string;
    designerKey: string;
    designerLabel: string;
    baseDomain: string;
    onClose: () => void;
}) {
    const [state, setState] = useState<LoadState<{ leads: DesignerLeadItem[]; total: number; limited: boolean; limit: number }>>({ status: 'loading' });

    useEffect(() => {
        const url = buildUrl(leadsUrl, { from, to, designer: designerKey });
        fetch(url)
            .then((r) => r.json())
            .then((json) => setState({ status: 'loaded', data: json.data }))
            .catch(() => setState({ status: 'error', message: 'Ошибка загрузки' }));
    }, [leadsUrl, from, to, designerKey]);

    useEffect(() => {
        const handleKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [onClose]);

    const title = designerLabel ? `Сделки: ${designerLabel}` : 'Все сделки';

    return createPortal(
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="designer-modal-title"
        >
            <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
            <div className="relative z-10 flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-violet-500">По дизайнерам</p>
                        <h3 id="designer-modal-title" className="mt-0.5 font-bold text-gray-900">{title}</h3>
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
                                    Показаны {state.data.limit} из {state.data.total} сделок — отсортированы по бюджету
                                </div>
                            )}
                            <table className="w-full text-left text-sm">
                                <thead className="sticky top-0 bg-slate-50">
                                    <tr>
                                        <th className="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Сделка</th>
                                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Дата закрытия</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Бюджет</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {state.data.leads.length > 0 ? state.data.leads.map((lead) => (
                                        <tr className="transition-colors hover:bg-violet-50/50" key={lead.id}>
                                            <td className="px-5 py-3.5 font-semibold text-gray-900">{lead.name}</td>
                                            <td className="px-4 py-3.5 whitespace-nowrap tabular-nums text-slate-500">{lead.closed_date ?? '—'}</td>
                                            <td className="px-4 py-3.5 text-right font-bold tabular-nums text-emerald-700">{rubFull(lead.price)}</td>
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
                                            <td className="px-5 py-8 text-center text-slate-400" colSpan={4}>
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
                        {state.data.total} сделок · отсортировано по убыванию бюджета
                    </div>
                )}
            </div>
        </div>,
        document.body,
    );
}

// ─── Section components ───────────────────────────────────────────────────────

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
                                <td className="px-4 py-3.5 text-right">
                                    <button
                                        type="button"
                                        className="font-mono font-semibold tabular-nums text-indigo-700 underline-offset-2 hover:underline"
                                        onClick={() => onRowClick(m.name)}
                                    >
                                        {m.dealCount}
                                    </button>
                                </td>
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

function DesignerSummaryTable({ designers, onRowClick }: { designers: DesignerSummary[]; onRowClick: (designer: DesignerSummary) => void }) {
    const grandTotal = designers.reduce((s, d) => s + d.budgetTotal, 0);
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                    <tr>
                        <th className="px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Дизайнер</th>
                        <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Категория</th>
                        <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Сделок</th>
                        <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Сумма</th>
                        <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Доля</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {designers.map((d) => {
                        const share = grandTotal > 0 ? Math.round((d.budgetTotal / grandTotal) * 100) : 0;
                        return (
                            <tr key={d.key} className="transition-colors hover:bg-violet-50/50">
                                <td className="px-5 py-3.5 font-semibold text-gray-900">{d.name}</td>
                                <td className="px-4 py-3.5 text-slate-600">{d.category}</td>
                                <td className="px-4 py-3.5 text-right">
                                    <button
                                        type="button"
                                        className="font-mono font-semibold tabular-nums text-indigo-700 underline-offset-2 hover:underline"
                                        onClick={() => onRowClick(d)}
                                    >
                                        {d.dealCount}
                                    </button>
                                </td>
                                <td className="px-4 py-3.5 text-right">
                                    <button
                                        type="button"
                                        className="font-mono font-semibold tabular-nums text-indigo-700 underline-offset-2 hover:underline"
                                        onClick={() => onRowClick(d)}
                                    >
                                        {rubFull(d.budgetTotal)}
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
                    {designers.length > 1 && (
                        <tr className="bg-slate-50">
                            <td className="px-5 py-3.5 font-bold text-gray-900">Итого</td>
                            <td className="px-4 py-3.5" />
                            <td className="px-4 py-3.5 text-right font-bold tabular-nums text-gray-900">{designers.reduce((s, d) => s + d.dealCount, 0)}</td>
                            <td className="px-4 py-3.5 text-right font-bold tabular-nums text-gray-900">{rubFull(grandTotal)}</td>
                            <td className="px-4 py-3.5 text-xs text-slate-400">100%</td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

// ─── Content (reusable — no header, no own period state) ──────────────────────

export function ManagerTopupContent({ account, from, to, links }: {
    account: Account;
    from: string;
    to: string;
    links: { data: string; leads: string; designers: string; designerLeads: string };
}) {
    const [state, setState] = useState<LoadState<BreakdownData>>({ status: 'loading' });
    const [modal, setModal] = useState<{ manager: string } | null>(null);
    const [designerState, setDesignerState] = useState<LoadState<DesignerBreakdownData>>({ status: 'loading' });
    const [designerModal, setDesignerModal] = useState<{ key: string; label: string } | null>(null);

    useEffect(() => {
        setState({ status: 'loading' });
        const url = buildUrl(links.data, { from, to });
        fetch(url)
            .then((r) => r.json())
            .then((json) => setState({ status: 'loaded', data: json.data }))
            .catch((err) => setState({ status: 'error', message: String(err) }));
    }, [links.data, from, to]);

    useEffect(() => {
        setDesignerState({ status: 'loading' });
        const url = buildUrl(links.designers, { from, to });
        fetch(url)
            .then((r) => r.json())
            .then((json) => setDesignerState({ status: 'loaded', data: json.data }))
            .catch((err) => setDesignerState({ status: 'error', message: String(err) }));
    }, [links.designers, from, to]);

    const data = state.status === 'loaded' ? state.data : null;
    const designerData = designerState.status === 'loaded' ? designerState.data : null;

    return (
        <>
            {state.status === 'loading' && (
                <>
                    <SectionSkeleton rows={4} />
                    <SectionSkeleton rows={3} />
                    <SectionSkeleton rows={5} />
                </>
            )}

            {state.status === 'error' && <SectionError message={state.message} />}

            {data && (
                <>
                    {data.managers.length > 0 ? (
                        <ReportSection
                            eyebrow="Доплаты по менеджерам"
                            title="Разбивка по менеджерам"
                            description="Доплата = Бюджет сделки − Сумма предоплаты. Учитываются только сделки с положительной доплатой. Нажмите на количество сделок или на сумму — откроется список сделок."
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
                            <ManagerSummaryTable managers={data.managers} onRowClick={(name) => setModal({ manager: name })} />
                        </ReportSection>
                    ) : (
                        <ReportSection
                            eyebrow="Доплаты по менеджерам"
                            title="Разбивка по менеджерам"
                        >
                            <div className="px-5 py-8">
                                <EmptyState>Нет сделок с доплатой за выбранный период</EmptyState>
                            </div>
                        </ReportSection>
                    )}

                    {data.monthlyBreakdown.length > 0 && (
                        <ReportSection
                            eyebrow="Динамика по месяцам"
                            title="Доплаты по месяцам"
                            description="Распределение суммарной доплаты по месяцу предполагаемой доплаты."
                        >
                            <MonthlyColumnChart monthly={data.monthlyBreakdown} />
                        </ReportSection>
                    )}
                </>
            )}

            {designerState.status === 'loading' && <SectionSkeleton rows={4} />}
            {designerState.status === 'error' && <SectionError message={designerState.message} />}
            {designerData && (
                designerData.designers.length > 0 ? (
                    <ReportSection
                        eyebrow="По дизайнерам"
                        title="По дизайнерам (кто принёс больше денег за указанный период)"
                        description="Дизайнер сделки — привязанный контакт, а при его отсутствии — привязанная компания. Учитываются только успешно реализованные сделки по дате закрытия. Сумма — бюджет сделки. Нажмите на количество сделок или на сумму — откроется список сделок."
                        aside={
                            <AccentSummary
                                label="Итого"
                                value={rub(designerData.summary.budgetTotal)}
                                note={`${designerData.summary.dealCount} сделок · ${designerData.summary.designerCount} дизайнеров`}
                                tone="brand"
                            />
                        }
                    >
                        <DesignerSummaryTable
                            designers={designerData.designers}
                            onRowClick={(designer) => setDesignerModal({ key: designer.key, label: designer.name })}
                        />
                    </ReportSection>
                ) : (
                    <ReportSection
                        eyebrow="По дизайнерам"
                        title="По дизайнерам (кто принёс больше денег за указанный период)"
                    >
                        <div className="px-5 py-8">
                            <EmptyState>Нет успешно реализованных сделок с контактом или компанией за выбранный период</EmptyState>
                        </div>
                    </ReportSection>
                )
            )}

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

            {designerModal !== null && (
                <DesignerLeadsModal
                    leadsUrl={links.designerLeads}
                    from={from}
                    to={to}
                    designerKey={designerModal.key}
                    designerLabel={designerModal.label}
                    baseDomain={account.base_domain}
                    onClose={() => setDesignerModal(null)}
                />
            )}
        </>
    );
}
