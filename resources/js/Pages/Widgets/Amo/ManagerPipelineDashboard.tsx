import { createPortal } from 'react-dom';
import { useEffect, useState } from 'react';
import { Download, X } from 'lucide-react';
import {
    AccentSummary, buildUrl, EmptyState, type LoadState, ReportSection,
    SectionError, SectionSkeleton, useApiData, WidgetHeader,
} from './_shared/uiKit';

// ─── Types ────────────────────────────────────────────────────────────────────

type Account = { name: string; base_domain: string };

type Period = { from: string; to: string; source: string; preset: string | null; label: string };

type StageCount = { status_id: number; count: number; percent: number };

type OverviewRow = {
    name: string;
    total_count: number;
    success_count: number;
    conversion_rate: number;
    stage_counts: StageCount[];
};

type PipelineStage = { status_id: number; name: string };

type OverviewData = {
    pipeline_found: boolean;
    pipeline_name: string;
    manager_field_found: boolean;
    manager_field_name: string;
    success_status_name: string;
    total_count: number;
    success_count: number;
    stages: PipelineStage[];
    rows: OverviewRow[];
};

type ShiftRow = {
    name: string;
    shift_count: number;
    fifth_shift_count: number;
};

type ShiftData = {
    pipeline_found: boolean;
    pipeline_name: string;
    manager_field_found: boolean;
    manager_field_name: string;
    success_status_name: string;
    fifth_shift_field_found: boolean;
    fifth_shift_field_name: string;
    shift_count: number;
    fifth_shift_count: number;
    rows: ShiftRow[];
};

type AvitoCabinetRow = {
    name: string;
    total_count: number;
    success_count: number;
};

type AvitoCabinetData = {
    pipeline_found: boolean;
    pipeline_name: string;
    success_status_name: string;
    cabinets: AvitoCabinetRow[];
};

type FunnelRow = {
    status_id: number;
    name: string;
    funnel_count: number;
    funnel_rate: number;
    avg_seconds_in_stage: number | null;
    transitions_observed: number;
    exit_success_count: number;
    exit_fail_count: number;
    exit_other_count: number;
    exit_success_rate: number;
    exit_fail_rate: number;
};

type FunnelData = {
    pipeline_found: boolean;
    pipeline_name: string;
    total_count: number;
    rows: FunnelRow[];
};

type LeadItem = { id: string | number; name: string; created_at: string | null };
type LeadsResult = { leads: LeadItem[]; total: number; limited: boolean; limit: number };

type Links = {
    self: string;
    overview: string;
    overviewLeads: string;
    shiftBreakdown: string;
    shiftLeads: string;
    avitoCabinetBreakdown: string;
    avitoCabinetLeads: string;
    funnel: string;
    funnelLeads: string;
    export: string;
};

type Props = { account: Account; period: Period; links: Links };

function clampPercent(value: number): number {
    return Math.min(Math.max(value, 0), 100);
}

// ─── Leads modal (shared by both tables) ───────────────────────────────────────

type LeadsFilter = {
    leadsUrl: string;
    manager: string;
    extraParams: Record<string, string>;
    label: string;
};

function LeadsModal({ filter, from, to, baseDomain, onClose }: {
    filter: LeadsFilter;
    from: string;
    to: string;
    baseDomain: string;
    onClose: () => void;
}) {
    const state = useApiData<LeadsResult>(filter.leadsUrl, { from, to, manager: filter.manager, ...filter.extraParams });

    useEffect(() => {
        const handleKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [onClose]);

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
            <div className="relative z-10 flex max-h-[80vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-violet-500">Сделки</p>
                        <h2 className="mt-0.5 font-bold text-gray-900">{filter.label}</h2>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600" aria-label="Закрыть">
                        <X className="size-5" />
                    </button>
                </div>
                {state.status === 'loading' && (
                    <div className="flex items-center justify-center py-16 text-slate-400">
                        <svg className="mr-2 size-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" /></svg>
                        Загрузка...
                    </div>
                )}
                {state.status === 'error' && (
                    <div className="px-6 py-8 text-center text-sm text-red-500">Ошибка загрузки: {state.message}</div>
                )}
                {state.status === 'loaded' && (
                    <>
                        {state.data.limited && (
                            <div className="border-b border-amber-100 bg-amber-50 px-6 py-2 text-xs text-amber-700">
                                Показаны первые {state.data.limit} из {state.data.total} сделок
                            </div>
                        )}
                        <div className="overflow-y-auto">
                            {state.data.leads.length === 0 ? (
                                <div className="px-6 py-8 text-center text-sm text-slate-400">Нет сделок</div>
                            ) : (
                                <table className="w-full text-left text-sm">
                                    <thead className="sticky top-0 bg-slate-50">
                                        <tr>
                                            <th className="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Сделка</th>
                                            <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Дата создания</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {state.data.leads.map((lead) => (
                                            <tr key={lead.id} className="transition-colors hover:bg-violet-50/50">
                                                <td className="px-6 py-3">
                                                    <a
                                                        href={`https://${baseDomain}/leads/detail/${lead.id}`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="font-medium text-violet-700 hover:underline"
                                                    >
                                                        {lead.name}
                                                    </a>
                                                </td>
                                                <td className="px-4 py-3 text-slate-500">{lead.created_at ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                        <div className="border-t border-slate-100 px-6 py-3 text-right text-xs text-slate-400">
                            Итого: {state.data.total} сделок
                        </div>
                    </>
                )}
            </div>
        </div>,
        document.body,
    );
}

function CountButton({ value, tone, onClick }: { value: number; tone?: 'default' | 'success'; onClick: () => void }) {
    const cls = tone === 'success'
        ? 'text-emerald-700 hover:text-emerald-900'
        : 'text-indigo-700 hover:text-indigo-900';
    return (
        <button type="button" className={`font-mono font-semibold tabular-nums underline-offset-2 hover:underline ${cls}`} onClick={onClick}>
            {value}
        </button>
    );
}

// ─── Overview section ───────────────────────────────────────────────────────

function OverviewSection({ state, leadsUrl, funnelLeadsUrl, onOpenLeads }: {
    state: LoadState<OverviewData>;
    leadsUrl: string;
    funnelLeadsUrl: string;
    onOpenLeads: (filter: LeadsFilter) => void;
}) {
    if (state.status === 'loading') return <SectionSkeleton rows={4} />;
    if (state.status === 'error') return <SectionError message={state.message} />;
    const data = state.data;

    if (!data.pipeline_found) {
        return (
            <ReportSection eyebrow="Менеджеры подбор" title="Общее количество сделок по менеджерам">
                <div className="px-5 py-8">
                    <EmptyState>Воронка «Менеджеры подбор» не найдена. Запустите синхронизацию структуры CRM.</EmptyState>
                </div>
            </ReportSection>
        );
    }

    return (
        <ReportSection
            eyebrow="Менеджеры подбор"
            title={`Сделки по полю "${data.manager_field_name}" и конверсия в «${data.success_status_name}»`}
            description={`Воронка: ${data.pipeline_name}. Только закрытые сделки, созданные в выбранном периоде («${data.success_status_name}» + «Закрыто и не реализовано») — сделки в работе не учитываются, поэтому «Сделок за период» = «${data.success_status_name}» + отказы. Колонки по этапам — реально ли сделка была на этом статусе (по истории переходов и текущему статусу; переходы фиксируются примерно с 1 августа 2026, более ранние — только по текущему статусу). Нажмите на число — откроется список сделок.`}
            aside={<AccentSummary label="Всего сделок" value={data.total_count} note={`Встал в график: ${data.success_count}`} tone="brand" />}
        >
            {data.rows.length > 0 ? (
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                            <tr>
                                <th className="sticky left-0 bg-slate-50 px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Менеджер</th>
                                <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Сделок за период</th>
                                <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Встал в график</th>
                                <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Конверсия</th>
                                {data.stages.map((stage) => (
                                    <th key={stage.status_id} className="whitespace-nowrap px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">
                                        {stage.name}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {data.rows.map((row) => (
                                <tr key={row.name} className="transition-colors hover:bg-violet-50/50">
                                    <td className="sticky left-0 bg-white px-5 py-3.5 font-semibold text-gray-900">{row.name}</td>
                                    <td className="px-4 py-3.5 text-right">
                                        <CountButton
                                            value={row.total_count}
                                            onClick={() => onOpenLeads({ leadsUrl, manager: row.name, extraParams: { success_only: '0' }, label: `${row.name} — все сделки` })}
                                        />
                                    </td>
                                    <td className="px-4 py-3.5 text-right">
                                        <CountButton
                                            value={row.success_count}
                                            tone="success"
                                            onClick={() => onOpenLeads({ leadsUrl, manager: row.name, extraParams: { success_only: '1' }, label: `${row.name} — встал в график` })}
                                        />
                                    </td>
                                    <td className="px-4 py-3.5">
                                        <div className="flex min-w-40 items-center gap-2.5">
                                            <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                                <div className="h-2 rounded-full bg-gradient-to-r from-violet-400 to-indigo-600" style={{ width: `${clampPercent(row.conversion_rate)}%` }} />
                                            </div>
                                            <span className="w-12 text-right text-sm font-semibold tabular-nums text-slate-600">{clampPercent(row.conversion_rate)}%</span>
                                        </div>
                                    </td>
                                    {row.stage_counts.map((stage) => {
                                        const stageName = data.stages.find((s) => s.status_id === stage.status_id)?.name ?? '';
                                        return (
                                            <td key={stage.status_id} className="whitespace-nowrap px-4 py-3.5 text-right">
                                                <CountButton
                                                    value={stage.count}
                                                    onClick={() => onOpenLeads({
                                                        leadsUrl: funnelLeadsUrl,
                                                        manager: row.name,
                                                        extraParams: { status_id: String(stage.status_id), mode: 'visited' },
                                                        label: `${row.name} — ${stageName}`,
                                                    })}
                                                />
                                                <span className="ml-1.5 text-xs font-medium text-slate-400">{stage.percent}%</span>
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div className="px-5 py-8">
                    <EmptyState>Нет сделок за выбранный период</EmptyState>
                </div>
            )}
        </ReportSection>
    );
}

// ─── Shift breakdown section ────────────────────────────────────────────────

function ShiftSection({ state, leadsUrl, onOpenLeads }: {
    state: LoadState<ShiftData>;
    leadsUrl: string;
    onOpenLeads: (filter: LeadsFilter) => void;
}) {
    if (state.status === 'loading') return <SectionSkeleton rows={4} />;
    if (state.status === 'error') return <SectionError message={state.message} />;
    const data = state.data;

    if (!data.pipeline_found) {
        return null;
    }

    return (
        <ReportSection
            eyebrow="Менеджеры подбор"
            title="Выведено на смену и вышло на 5-ю смену"
            description={`Сделки в статусе «${data.success_status_name}» воронки «${data.pipeline_name}», с признаком «${data.fifth_shift_field_name}».`}
            aside={<AccentSummary label="Выведено на смену" value={data.shift_count} note={`Вышло на 5 смену: ${data.fifth_shift_count}`} tone="success" />}
        >
            {!data.fifth_shift_field_found ? (
                <div className="px-5 py-8">
                    <EmptyState>Поле «{data.fifth_shift_field_name}» не найдено. Запустите синхронизацию структуры CRM.</EmptyState>
                </div>
            ) : data.rows.length > 0 ? (
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                            <tr>
                                <th className="px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Менеджер</th>
                                <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Выведено на смену</th>
                                <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Вышло на 5 смену</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {data.rows.map((row) => (
                                <tr key={row.name} className="transition-colors hover:bg-violet-50/50">
                                    <td className="px-5 py-3.5 font-semibold text-gray-900">{row.name}</td>
                                    <td className="px-4 py-3.5 text-right">
                                        <CountButton
                                            value={row.shift_count}
                                            onClick={() => onOpenLeads({ leadsUrl, manager: row.name, extraParams: { fifth_shift_only: '0' }, label: `${row.name} — выведено на смену` })}
                                        />
                                    </td>
                                    <td className="px-4 py-3.5 text-right">
                                        <CountButton
                                            value={row.fifth_shift_count}
                                            tone="success"
                                            onClick={() => onOpenLeads({ leadsUrl, manager: row.name, extraParams: { fifth_shift_only: '1' }, label: `${row.name} — вышло на 5 смену` })}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div className="px-5 py-8">
                    <EmptyState>Нет сделок в статусе «{data.success_status_name}» за выбранный период</EmptyState>
                </div>
            )}
        </ReportSection>
    );
}

// ─── Avito cabinet section ──────────────────────────────────────────────────

function AvitoCabinetSection({ state, leadsUrl, onOpenLeads }: {
    state: LoadState<AvitoCabinetData>;
    leadsUrl: string;
    onOpenLeads: (filter: LeadsFilter) => void;
}) {
    if (state.status === 'loading') return <SectionSkeleton rows={2} />;
    if (state.status === 'error') return <SectionError message={state.message} />;
    const data = state.data;

    if (!data.pipeline_found) {
        return null;
    }

    return (
        <ReportSection
            eyebrow="Менеджеры подбор"
            title="Кабинеты Авито"
            description={`Лиды за выбранный период по каждому кабинету Авито (определяется по тегу лида) в воронке «${data.pipeline_name}» и сколько из них дошло до этапа «${data.success_status_name}». Нажмите на число — откроется список сделок.`}
        >
            {data.cabinets.length > 0 ? (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-gradient-to-r from-slate-50 to-white text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                <th className="px-5 py-3">Кабинет Авито</th>
                                <th className="px-5 py-3 text-right">Лидов</th>
                                <th className="px-5 py-3 text-right">Встал в график</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {data.cabinets.map((cabinet) => (
                                <tr key={cabinet.name}>
                                    <td className="px-5 py-3 font-semibold text-gray-900">{cabinet.name}</td>
                                    <td className="px-5 py-3 text-right">
                                        <CountButton
                                            value={cabinet.total_count}
                                            onClick={() => onOpenLeads({ leadsUrl, manager: '', extraParams: { cabinet: cabinet.name, success_only: '0' }, label: `${cabinet.name} — все лиды` })}
                                        />
                                    </td>
                                    <td className="px-5 py-3 text-right">
                                        <CountButton
                                            value={cabinet.success_count}
                                            tone="success"
                                            onClick={() => onOpenLeads({ leadsUrl, manager: '', extraParams: { cabinet: cabinet.name, success_only: '1' }, label: `${cabinet.name} — встал в график` })}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div className="px-5 py-8">
                    <EmptyState>Нет данных по кабинетам Авито за выбранный период</EmptyState>
                </div>
            )}
        </ReportSection>
    );
}

// ─── Funnel section ─────────────────────────────────────────────────────────

function formatDuration(seconds: number | null): string {
    if (seconds === null) return '—';
    const hours = seconds / 3600;
    if (hours < 1) return `${Math.round(seconds / 60)} мин`;
    if (hours < 48) return `${Math.round(hours * 10) / 10} ч`;
    return `${Math.round((hours / 24) * 10) / 10} дн`;
}

function FunnelSection({ state, leadsUrl, onOpenLeads }: {
    state: LoadState<FunnelData>;
    leadsUrl: string;
    onOpenLeads: (filter: LeadsFilter) => void;
}) {
    if (state.status === 'loading') return <SectionSkeleton rows={5} />;
    if (state.status === 'error') return <SectionError message={state.message} />;
    const data = state.data;

    if (!data.pipeline_found) {
        return null;
    }

    return (
        <ReportSection
            eyebrow="Менеджеры подбор"
            title="Воронка по этапам"
            description={`Воронка: ${data.pipeline_name}. «Дошло сделок» — сколько сделок за период когда-либо достигли этапа (по текущему статусу сделки). «Ушло со статуса» — только по наблюдаемым переходам между этапами (данные о переходах доступны примерно с 1 августа 2026, более ранние — неполные).`}
            aside={<AccentSummary label="Всего сделок в периоде" value={data.total_count} tone="brand" />}
        >
            {data.rows.length > 0 ? (
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                            <tr>
                                <th className="px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Этап</th>
                                <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Дошло сделок</th>
                                <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Доля от старта</th>
                                <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Ср. время на этапе</th>
                                <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Куда уходят со статуса</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {data.rows.map((row) => (
                                <tr key={row.status_id} className="transition-colors hover:bg-violet-50/50">
                                    <td className="px-5 py-3.5 font-semibold text-gray-900">{row.name}</td>
                                    <td className="px-4 py-3.5 text-right">
                                        <CountButton
                                            value={row.funnel_count}
                                            onClick={() => onOpenLeads({ leadsUrl, manager: '', extraParams: { status_id: String(row.status_id), mode: 'reached' }, label: `${row.name} — дошло сделок` })}
                                        />
                                    </td>
                                    <td className="px-4 py-3.5">
                                        <div className="flex min-w-40 items-center gap-2.5">
                                            <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                                <div className="h-2 rounded-full bg-gradient-to-r from-violet-400 to-indigo-600" style={{ width: `${clampPercent(row.funnel_rate)}%` }} />
                                            </div>
                                            <span className="w-14 text-right text-sm font-semibold tabular-nums text-slate-600">{clampPercent(row.funnel_rate)}%</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3.5 text-right tabular-nums text-slate-700">{formatDuration(row.avg_seconds_in_stage)}</td>
                                    <td className="px-4 py-3.5 text-right">
                                        {row.transitions_observed > 0 ? (
                                            <div className="flex items-center justify-end gap-1.5 text-xs">
                                                <button
                                                    type="button"
                                                    className="rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100"
                                                    onClick={() => onOpenLeads({ leadsUrl, manager: '', extraParams: { status_id: String(row.status_id), mode: 'success' }, label: `${row.name} — ушло в успех` })}
                                                >
                                                    ✓ {row.exit_success_count}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="rounded-full bg-red-50 px-2 py-0.5 font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100"
                                                    onClick={() => onOpenLeads({ leadsUrl, manager: '', extraParams: { status_id: String(row.status_id), mode: 'fail' }, label: `${row.name} — ушло в отказ` })}
                                                >
                                                    ✕ {row.exit_fail_count}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-200"
                                                    onClick={() => onOpenLeads({ leadsUrl, manager: '', extraParams: { status_id: String(row.status_id), mode: 'other' }, label: `${row.name} — ушло дальше` })}
                                                >
                                                    → {row.exit_other_count}
                                                </button>
                                            </div>
                                        ) : (
                                            <span className="text-xs text-slate-400">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div className="px-5 py-8">
                    <EmptyState>Нет данных за выбранный период</EmptyState>
                </div>
            )}
        </ReportSection>
    );
}

// ─── Page ────────────────────────────────────────────────────────────────────

export default function ManagerPipelineDashboard({ account, period, links }: Props) {
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);
    const [appliedFrom, setAppliedFrom] = useState(period.from);
    const [appliedTo, setAppliedTo] = useState(period.to);

    const [leadsFilter, setLeadsFilter] = useState<LeadsFilter | null>(null);

    const periodParams = { from: appliedFrom, to: appliedTo };
    const overviewState = useApiData<OverviewData>(links.overview, periodParams);
    const shiftState = useApiData<ShiftData>(links.shiftBreakdown, periodParams);
    const avitoCabinetState = useApiData<AvitoCabinetData>(links.avitoCabinetBreakdown, periodParams);
    const funnelState = useApiData<FunnelData>(links.funnel, periodParams);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setAppliedFrom(from);
        setAppliedTo(to);
    };

    return (
        <div className="min-h-screen bg-slate-100 px-3 py-5 text-gray-900 sm:px-5">
            <div className="mx-auto max-w-7xl space-y-5">
                <WidgetHeader
                    title="Менеджеры подбор"
                    account={account}
                    period={{ ...period, from: appliedFrom, to: appliedTo }}
                    from={from}
                    to={to}
                    onFromChange={setFrom}
                    onToChange={setTo}
                    onSubmit={handleSubmit}
                />

                <div className="flex justify-end">
                    <a
                        href={buildUrl(links.export, { from: appliedFrom, to: appliedTo })}
                        className="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                    >
                        <Download className="size-3.5" />
                        Экспорт в Excel
                    </a>
                </div>

                <OverviewSection
                    state={overviewState}
                    leadsUrl={links.overviewLeads}
                    funnelLeadsUrl={links.funnelLeads}
                    onOpenLeads={setLeadsFilter}
                />

                <ShiftSection
                    state={shiftState}
                    leadsUrl={links.shiftLeads}
                    onOpenLeads={setLeadsFilter}
                />

                <AvitoCabinetSection
                    state={avitoCabinetState}
                    leadsUrl={links.avitoCabinetLeads}
                    onOpenLeads={setLeadsFilter}
                />

                <FunnelSection state={funnelState} leadsUrl={links.funnelLeads} onOpenLeads={setLeadsFilter} />
            </div>

            {leadsFilter !== null && (
                <LeadsModal
                    filter={leadsFilter}
                    from={appliedFrom}
                    to={appliedTo}
                    baseDomain={account.base_domain}
                    onClose={() => setLeadsFilter(null)}
                />
            )}
        </div>
    );
}
