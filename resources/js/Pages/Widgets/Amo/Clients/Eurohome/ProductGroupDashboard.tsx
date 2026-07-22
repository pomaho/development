import { createPortal } from 'react-dom';
import { useEffect, useState } from 'react';
import { ExternalLink, X } from 'lucide-react';
import {
    AccentSummary, buildUrl, EmptyState, type LoadState, ReportSection, rub, rubFull,
    SectionError, SectionSkeleton, WidgetHeader,
} from '../../_shared/uiKit';

// ─── Types ────────────────────────────────────────────────────────────────────

type Account = { name: string; base_domain: string };

type Period = { from: string; to: string; label: string };

type Props = {
    account: Account;
    period: Period;
    links: { self: string; data: string; leads: string };
};

type GroupSummary = {
    name: string;
    budgetTotal: number;
    dealCount: number;
};

type BreakdownData = {
    summary: { groupCount: number; dealCount: number; budgetTotal: number };
    groups: GroupSummary[];
};

type LeadItem = {
    id: string | number;
    name: string;
    groups: string[];
    created_date: string | null;
    price: number;
};

// ─── Leads Modal ──────────────────────────────────────────────────────────────

function LeadsModal({ leadsUrl, from, to, group, baseDomain, onClose }: {
    leadsUrl: string;
    from: string;
    to: string;
    group: string;
    baseDomain: string;
    onClose: () => void;
}) {
    const [state, setState] = useState<LoadState<{ leads: LeadItem[]; total: number; limited: boolean; limit: number }>>({ status: 'loading' });

    useEffect(() => {
        const url = buildUrl(leadsUrl, { from, to, group });
        fetch(url)
            .then((r) => r.json())
            .then((json) => setState({ status: 'loaded', data: json.data }))
            .catch(() => setState({ status: 'error', message: 'Ошибка загрузки' }));
    }, [leadsUrl, from, to, group]);

    useEffect(() => {
        const handleKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [onClose]);

    const title = group ? `Сделки: ${group}` : 'Все сделки';

    return createPortal(
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="product-group-modal-title"
        >
            <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
            <div className="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-violet-500">Товарные группы</p>
                        <h3 id="product-group-modal-title" className="mt-0.5 font-bold text-gray-900">{title}</h3>
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
                                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Товарные группы</th>
                                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Создана</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Бюджет</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {state.data.leads.length > 0 ? state.data.leads.map((lead) => (
                                        <tr className="transition-colors hover:bg-violet-50/50" key={lead.id}>
                                            <td className="px-5 py-3.5 font-semibold text-gray-900">{lead.name}</td>
                                            <td className="px-4 py-3.5 text-slate-600">{lead.groups.join(', ')}</td>
                                            <td className="px-4 py-3.5 whitespace-nowrap tabular-nums text-slate-500">{lead.created_date ?? '—'}</td>
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
                                            <td className="px-5 py-8 text-center text-slate-400" colSpan={5}>
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

function GroupBarChart({ groups, onGroupClick }: { groups: GroupSummary[]; onGroupClick: (name: string) => void }) {
    const max = Math.max(...groups.map((g) => g.budgetTotal), 1);
    return (
        <div className="divide-y divide-slate-100 pb-5">
            {groups.map((g, i) => {
                const pct = Math.round((g.budgetTotal / max) * 100);
                return (
                    <div className="flex items-center gap-4 px-5 py-3.5" key={g.name}>
                        <div className="w-6 shrink-0 text-right text-xs font-bold text-slate-400 tabular-nums">{i + 1}</div>
                        <div className="min-w-0 flex-1">
                            <div className="mb-1.5 flex items-center justify-between gap-3">
                                <span className="truncate font-semibold text-gray-900">{g.name}</span>
                                <button
                                    type="button"
                                    className="shrink-0 rounded-full bg-violet-500 px-3 py-0.5 text-xs font-bold tabular-nums text-white transition-colors hover:bg-violet-600"
                                    onClick={() => onGroupClick(g.name)}
                                >
                                    {rub(g.budgetTotal)}
                                </button>
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-violet-50">
                                <div
                                    className="h-full rounded-full bg-gradient-to-r from-violet-400 to-indigo-500 transition-all duration-500"
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

function GroupSummaryTable({ groups, onRowClick }: { groups: GroupSummary[]; onRowClick: (name: string) => void }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                    <tr>
                        <th className="px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Товарная группа</th>
                        <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Сделок</th>
                        <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Бюджет</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {groups.map((g) => (
                        <tr key={g.name} className="transition-colors hover:bg-violet-50/50">
                            <td className="px-5 py-3.5 font-semibold text-gray-900">{g.name}</td>
                            <td className="px-4 py-3.5 text-right tabular-nums text-slate-600">{g.dealCount}</td>
                            <td className="px-4 py-3.5 text-right">
                                <button
                                    type="button"
                                    className="font-mono font-semibold tabular-nums text-indigo-700 underline-offset-2 hover:underline"
                                    onClick={() => onRowClick(g.name)}
                                >
                                    {rubFull(g.budgetTotal)}
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ─── Content (reusable — no header, no own period state) ──────────────────────

export function ProductGroupContent({ account, from, to, links }: {
    account: Account;
    from: string;
    to: string;
    links: { data: string; leads: string };
}) {
    const [state, setState] = useState<LoadState<BreakdownData>>({ status: 'loading' });
    const [modal, setModal] = useState<{ group: string } | null>(null);

    useEffect(() => {
        setState({ status: 'loading' });
        const url = buildUrl(links.data, { from, to });
        fetch(url)
            .then((r) => r.json())
            .then((json) => setState({ status: 'loaded', data: json.data }))
            .catch((err) => setState({ status: 'error', message: String(err) }));
    }, [links.data, from, to]);

    const data = state.status === 'loaded' ? state.data : null;

    return (
        <>
            {state.status === 'loading' && (
                <>
                    <SectionSkeleton rows={4} />
                    <SectionSkeleton rows={5} />
                </>
            )}

            {state.status === 'error' && <SectionError message={state.message} />}

            {data && (
                <>
                    {data.groups.length > 0 ? (
                        <ReportSection
                            eyebrow="Товарные группы"
                            title="Бюджет активных сделок по товарным группам"
                            description="Активные сделки — без учёта успешно реализованных, закрытых нереализованных и отложенных/замороженных. Период — по дате создания сделки. Сделка с несколькими товарными группами учитывается в каждой из них, поэтому сумма по группам может превышать общий бюджет. Нажмите на сумму — откроется список сделок."
                            aside={
                                <button type="button" onClick={() => setModal({ group: '' })}>
                                    <AccentSummary
                                        label="Бюджет активных сделок"
                                        value={rub(data.summary.budgetTotal)}
                                        note={`${data.summary.dealCount} сделок · ${data.summary.groupCount} групп`}
                                        tone="brand"
                                    />
                                </button>
                            }
                        >
                            <GroupBarChart groups={data.groups} onGroupClick={(name) => setModal({ group: name })} />
                        </ReportSection>
                    ) : (
                        <ReportSection
                            eyebrow="Товарные группы"
                            title="Бюджет активных сделок по товарным группам"
                        >
                            <div className="px-5 py-8">
                                <EmptyState>Нет активных сделок за выбранный период</EmptyState>
                            </div>
                        </ReportSection>
                    )}

                    {data.groups.length > 0 && (
                        <ReportSection
                            eyebrow="Сводная таблица"
                            title="Разбивка по товарным группам"
                            description="Нажмите на сумму бюджета — откроется детальный список сделок группы."
                        >
                            <GroupSummaryTable groups={data.groups} onRowClick={(name) => setModal({ group: name })} />
                        </ReportSection>
                    )}
                </>
            )}

            {modal !== null && (
                <LeadsModal
                    leadsUrl={links.leads}
                    from={from}
                    to={to}
                    group={modal.group}
                    baseDomain={account.base_domain}
                    onClose={() => setModal(null)}
                />
            )}
        </>
    );
}

// ─── Standalone page ────────────────────────────────────────────────────────

export default function ProductGroupDashboard({ account, period, links }: Props) {
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);
    const [appliedFrom, setAppliedFrom] = useState(period.from);
    const [appliedTo, setAppliedTo] = useState(period.to);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setAppliedFrom(from);
        setAppliedTo(to);
    };

    return (
        <div className="min-h-screen bg-slate-100 px-3 py-5 text-gray-900 sm:px-5">
            <div className="mx-auto max-w-7xl space-y-5">
                <WidgetHeader
                    title="Товарные группы"
                    account={account}
                    period={period}
                    from={from}
                    to={to}
                    onFromChange={setFrom}
                    onToChange={setTo}
                    onSubmit={handleSubmit}
                />

                <ProductGroupContent account={account} from={appliedFrom} to={appliedTo} links={links} />
            </div>
        </div>
    );
}
