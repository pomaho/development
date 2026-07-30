import { createPortal } from 'react-dom';
import { useEffect, useState } from 'react';
import { ExternalLink, X } from 'lucide-react';
import {
    AccentSummary, buildUrl, EmptyState, type LoadState, ReportSection, rub, rubFull,
    SectionError, SectionSkeleton,
} from '../../_shared/uiKit';

// ─── Types ────────────────────────────────────────────────────────────────────

type Account = { name: string; base_domain: string };

type SupplierSummary = {
    name: string;
    budgetTotal: number;
    dealCount: number;
};

type BreakdownData = {
    summary: { supplierCount: number; dealCount: number; budgetTotal: number };
    suppliers: SupplierSummary[];
};

type LeadItem = {
    id: string | number;
    name: string;
    suppliers: string[];
    created_date: string | null;
    price: number;
};

// ─── Leads Modal ──────────────────────────────────────────────────────────────

function LeadsModal({ leadsUrl, from, to, supplier, baseDomain, onClose }: {
    leadsUrl: string;
    from: string;
    to: string;
    supplier: string;
    baseDomain: string;
    onClose: () => void;
}) {
    const [state, setState] = useState<LoadState<{ leads: LeadItem[]; total: number; limited: boolean; limit: number }>>({ status: 'loading' });

    useEffect(() => {
        const url = buildUrl(leadsUrl, { from, to, supplier });
        fetch(url)
            .then((r) => r.json())
            .then((json) => setState({ status: 'loaded', data: json.data }))
            .catch(() => setState({ status: 'error', message: 'Ошибка загрузки' }));
    }, [leadsUrl, from, to, supplier]);

    useEffect(() => {
        const handleKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [onClose]);

    const title = supplier ? `Сделки: ${supplier}` : 'Все сделки';

    return createPortal(
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="supplier-modal-title"
        >
            <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
            <div className="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-violet-500">Поставщики</p>
                        <h3 id="supplier-modal-title" className="mt-0.5 font-bold text-gray-900">{title}</h3>
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
                                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Поставщики</th>
                                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Создана</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Бюджет</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {state.data.leads.length > 0 ? state.data.leads.map((lead) => (
                                        <tr className="transition-colors hover:bg-violet-50/50" key={lead.id}>
                                            <td className="px-5 py-3.5 font-semibold text-gray-900">{lead.name}</td>
                                            <td className="px-4 py-3.5 text-slate-600">{lead.suppliers.join(', ')}</td>
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

function SupplierSummaryTable({ suppliers, onRowClick }: { suppliers: SupplierSummary[]; onRowClick: (name: string) => void }) {
    const grandTotal = suppliers.reduce((s, sup) => s + sup.budgetTotal, 0);
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                    <tr>
                        <th className="px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Поставщик</th>
                        <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Сделок</th>
                        <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Бюджет</th>
                        <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Доля</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {suppliers.map((s) => {
                        const share = grandTotal > 0 ? Math.round((s.budgetTotal / grandTotal) * 100) : 0;
                        return (
                            <tr key={s.name} className="transition-colors hover:bg-violet-50/50">
                                <td className="px-5 py-3.5 font-semibold text-gray-900">{s.name}</td>
                                <td className="px-4 py-3.5 text-right">
                                    <button
                                        type="button"
                                        className="font-mono font-semibold tabular-nums text-indigo-700 underline-offset-2 hover:underline"
                                        onClick={() => onRowClick(s.name)}
                                    >
                                        {s.dealCount}
                                    </button>
                                </td>
                                <td className="px-4 py-3.5 text-right">
                                    <button
                                        type="button"
                                        className="font-mono font-semibold tabular-nums text-indigo-700 underline-offset-2 hover:underline"
                                        onClick={() => onRowClick(s.name)}
                                    >
                                        {rubFull(s.budgetTotal)}
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
                </tbody>
            </table>
        </div>
    );
}

// ─── Content (reusable — no header, no own period state) ──────────────────────

export function SupplierContent({ account, from, to, links }: {
    account: Account;
    from: string;
    to: string;
    links: { data: string; leads: string };
}) {
    const [state, setState] = useState<LoadState<BreakdownData>>({ status: 'loading' });
    const [modal, setModal] = useState<{ supplier: string } | null>(null);

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
                data.suppliers.length > 0 ? (
                    <ReportSection
                        eyebrow="Поставщики"
                        title="Бюджет активных сделок по поставщикам"
                        description="Активные сделки — без учёта успешно реализованных, закрытых нереализованных и отложенных/замороженных. Период — по дате создания сделки. Сделка с несколькими поставщиками учитывается у каждого из них, поэтому сумма по поставщикам может превышать общий бюджет. Нажмите на количество сделок или на сумму — откроется список сделок."
                        aside={
                            <button type="button" onClick={() => setModal({ supplier: '' })}>
                                <AccentSummary
                                    label="Бюджет активных сделок"
                                    value={rub(data.summary.budgetTotal)}
                                    note={`${data.summary.dealCount} сделок · ${data.summary.supplierCount} поставщиков`}
                                    tone="brand"
                                />
                            </button>
                        }
                    >
                        <SupplierSummaryTable suppliers={data.suppliers} onRowClick={(name) => setModal({ supplier: name })} />
                    </ReportSection>
                ) : (
                    <ReportSection
                        eyebrow="Поставщики"
                        title="Бюджет активных сделок по поставщикам"
                    >
                        <div className="px-5 py-8">
                            <EmptyState>Нет активных сделок за выбранный период</EmptyState>
                        </div>
                    </ReportSection>
                )
            )}

            {modal !== null && (
                <LeadsModal
                    leadsUrl={links.leads}
                    from={from}
                    to={to}
                    supplier={modal.supplier}
                    baseDomain={account.base_domain}
                    onClose={() => setModal(null)}
                />
            )}
        </>
    );
}
