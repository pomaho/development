import { createPortal } from 'react-dom';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import {
    ArrowRightLeft,
    CalendarDays,
    ChevronDown,
    Database,
    Inbox,
    X,
} from 'lucide-react';

type Account = {
    name: string;
    base_domain: string;
};

type RecruiterLeadRow = {
    enum_id: number;
    name: string;
    leads_count: number;
    transferred_to_manager_count: number;
};

type RecruiterLeads = {
    field_name: string;
    field_id: number | null;
    field_found: boolean;
    pipeline_id: number | null;
    pipeline_name: string | null;
    total_leads_count: number;
    assigned_leads_count: number;
    transferred_to_manager_count: number;
    recruiters: RecruiterLeadRow[];
};

type RecruiterTeamCityBreakdown = {
    pipeline_id: number | null;
    pipeline_name: string | null;
    recruiter_field_found: boolean;
    manager_field_found: boolean;
    team_field_found: boolean;
    city_field_found: boolean;
    source_field_found: boolean;
    team_field_name: string;
    city_field_name: string;
    source_field_name: string;
    total_leads_count: number;
    source_columns: string[];
    recruiters: Array<{
        enum_id: number;
        name: string;
        total_leads_count: number;
        teams: Array<{
            name: string;
            total_leads_count: number;
            cities: Array<{
                name: string;
                leads_count: number;
                sources: Record<string, number>;
            }>;
        }>;
    }>;
};

type OverdueTask = {
    text: string | null;
    complete_till: string;
    completed_at: string;
    days_overdue: number;
};

type TaskStatisticsRow = {
    responsible_user_id: number;
    responsible_name: string | null;
    completed_count: number;
    completed_overdue_count: number;
    open_count: number;
    open_overdue_count: number;
    overdue_count: number;
    total_count: number;
    overdue_rate: number;
    overdue_tasks: OverdueTask[];
};

type TaskStatisticsGroup = {
    group_id: number | null;
    group_name: string;
    completed_count: number;
    completed_overdue_count: number;
    users: TaskStatisticsRow[];
};

type Props = {
    account: Account;
    period: {
        from: string;
        to: string;
        source: string;
        preset: string | null;
        label: string;
    };
    recruiterLeads: RecruiterLeads;
    recruiterTeamCityBreakdown: RecruiterTeamCityBreakdown;
    taskStatistics: TaskStatisticsGroup[];
    links: {
        self: string;
        api: string;
    };
};

type MessageLog = {
    received_at: string;
    origin: string;
    data: unknown;
};

type BreakdownRow = {
    name: string;
    count: number;
};

type SourceBreakdownRow = {
    team: string;
    city: string;
    count: number;
    sources: Record<string, number>;
};

const chartColors = ['#7C3AED', '#4F46E5', '#0EA5E9', '#10B981', '#F59E0B', '#EF4444', '#EC4899', '#64748B'];

const progressWidth = (value: number) => `${Math.min(Math.max(value, 0), 100)}%`;

const percentOf = (value: number, total: number) => total > 0 ? Math.round((value / total) * 1000) / 10 : 0;

const recruiterTeamRows = (recruiter: RecruiterTeamCityBreakdown['recruiters'][number]): BreakdownRow[] => (
    recruiter.teams.map((team) => ({ name: team.name, count: team.total_leads_count }))
);

const recruiterCityRows = (recruiter: RecruiterTeamCityBreakdown['recruiters'][number]): BreakdownRow[] => {
    const rows = new Map<string, number>();
    recruiter.teams.forEach((team) => {
        team.cities.forEach((city) => {
            rows.set(city.name, (rows.get(city.name) || 0) + city.leads_count);
        });
    });
    return Array.from(rows.entries())
        .map(([name, count]) => ({ name, count }))
        .sort((l, r) => r.count - l.count || l.name.localeCompare(r.name));
};

const departmentTeamRows = (breakdown: RecruiterTeamCityBreakdown): BreakdownRow[] => {
    const rows = new Map<string, number>();
    breakdown.recruiters.forEach((r) => r.teams.forEach((t) => rows.set(t.name, (rows.get(t.name) || 0) + t.total_leads_count)));
    return sortedBreakdownRows(rows);
};

const departmentCityRows = (breakdown: RecruiterTeamCityBreakdown): BreakdownRow[] => {
    const rows = new Map<string, number>();
    breakdown.recruiters.forEach((r) => r.teams.forEach((t) => t.cities.forEach((c) => rows.set(c.name, (rows.get(c.name) || 0) + c.leads_count))));
    return sortedBreakdownRows(rows);
};

const departmentSourceChartRows = (breakdown: RecruiterTeamCityBreakdown): BreakdownRow[] => {
    const rows = new Map<string, number>();
    breakdown.recruiters.forEach((r) => r.teams.forEach((t) => t.cities.forEach((c) => {
        breakdown.source_columns.forEach((s) => rows.set(s, (rows.get(s) || 0) + (c.sources[s] || 0)));
    })));
    return sortedBreakdownRows(rows);
};

const departmentSourceRows = (breakdown: RecruiterTeamCityBreakdown): SourceBreakdownRow[] => {
    const rows = new Map<string, SourceBreakdownRow>();
    breakdown.recruiters.forEach((r) => r.teams.forEach((t) => t.cities.forEach((c) => {
        const key = `${t.name}|||${c.name}`;
        const row = rows.get(key) || { team: t.name, city: c.name, count: 0, sources: {} };
        row.count += c.leads_count;
        breakdown.source_columns.forEach((s) => { row.sources[s] = (row.sources[s] || 0) + (c.sources[s] || 0); });
        rows.set(key, row);
    })));
    return Array.from(rows.values()).sort((l, r) => l.team.localeCompare(r.team) || r.count - l.count || l.city.localeCompare(r.city));
};

const sortedBreakdownRows = (rows: Map<string, number>): BreakdownRow[] => (
    Array.from(rows.entries()).map(([name, count]) => ({ name, count })).sort((l, r) => r.count - l.count || l.name.localeCompare(r.name))
);

export default function TaskOverdueDashboardV2({ account, period, recruiterLeads, recruiterTeamCityBreakdown, taskStatistics, links }: Props) {
    const debugIframe = useMemo(() => {
        if (typeof window === 'undefined') return false;
        return new URLSearchParams(window.location.search).get('debug_iframe') === '1';
    }, []);
    const preservedIframeParams = useMemo(() => {
        if (typeof window === 'undefined') return [] as Array<[string, string]>;
        const params = new URLSearchParams(window.location.search);
        const keys = ['currency', 'date_from', 'date_to', 'lang', 'period', 't'];
        return keys.flatMap((key) => params.getAll(key).map((value) => [key, value] as [string, string]));
    }, []);
    const [iframeMessages, setIframeMessages] = useState<MessageLog[]>([]);
    const periodLabel = `${period.label}: ${period.from} — ${period.to}`;
    const periodSourceLabel = period.source === 'amo_period' || period.source === 'amo_dates'
        ? 'Период с рабочего стола amoCRM'
        : 'Период выбран вручную';

    useEffect(() => {
        if (!debugIframe || typeof window === 'undefined') return;
        const listener = (event: MessageEvent) => {
            setIframeMessages((msgs) => [
                { received_at: new Date().toISOString(), origin: event.origin || 'unknown', data: event.data },
                ...msgs,
            ].slice(0, 10));
        };
        window.addEventListener('message', listener);
        return () => window.removeEventListener('message', listener);
    }, [debugIframe]);

    return (
        <div className="min-h-screen bg-slate-100 px-3 py-5 text-gray-900 sm:px-5">
            <div className="mx-auto max-w-7xl space-y-5">

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
                                <h1 className="mt-2 text-2xl font-bold text-white">Отчеты рабочего стола</h1>
                                <div className="mt-1.5 flex flex-wrap items-center gap-2 text-sm text-slate-400">
                                    <span>{account.name}</span>
                                    <span className="size-1 rounded-full bg-slate-600" />
                                    <span>{account.base_domain}</span>
                                    <span className="size-1 rounded-full bg-slate-600" />
                                    <span>{periodLabel}</span>
                                </div>
                            </div>
                        </div>

                        <form className="rounded-xl bg-white/5 p-4 ring-1 ring-white/10" method="get" action={links.self}>
                            {debugIframe ? <input type="hidden" name="debug_iframe" value="1" /> : null}
                            {preservedIframeParams.map(([key, value], index) => (
                                <input key={`${key}-${index}`} type="hidden" name={key} value={value} />
                            ))}
                            <div className="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <CalendarDays className="size-3.5 text-violet-400" />
                                Период
                            </div>
                            <div className="mb-2 text-xs text-slate-500">{periodSourceLabel}</div>
                            <div className="grid grid-cols-[1fr_1fr_auto] gap-2">
                                <input className="h-10 rounded-lg border-white/10 bg-white/10 px-3 text-sm text-white focus:border-violet-400 focus:ring-violet-400/20" name="from" type="date" defaultValue={period.from} />
                                <input className="h-10 rounded-lg border-white/10 bg-white/10 px-3 text-sm text-white focus:border-violet-400 focus:ring-violet-400/20" name="to" type="date" defaultValue={period.to} />
                                <button className="h-10 rounded-lg bg-gradient-to-r from-violet-600 to-indigo-600 px-4 text-sm font-semibold text-white shadow-lg shadow-violet-500/30 hover:from-violet-500 hover:to-indigo-500" type="submit">
                                    Показать
                                </button>
                            </div>
                        </form>
                    </div>
                </header>

                {debugIframe ? <DebugPanel iframeMessages={iframeMessages} /> : null}

                <ReportSection
                    eyebrow="Отчет по сделкам"
                    title={`Поле "${recruiterLeads.field_name}"`}
                    description={`Воронка: ${recruiterLeads.pipeline_name || (recruiterLeads.pipeline_id ? `ID ${recruiterLeads.pipeline_id}` : 'все воронки')}. Учитываются все этапы, включая успешные и закрытые нереализованные.`}
                    aside={<AccentSummary label="Сделок с рекрутером" value={recruiterLeads.assigned_leads_count} note={`Передано менеджеру: ${recruiterLeads.transferred_to_manager_count}`} tone="brand" />}
                >
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                                <tr>
                                    <th className="px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Рекрутер из списка</th>
                                    <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Сделок</th>
                                    <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Передано менеджеру</th>
                                    <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Доля</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {recruiterLeads.recruiters.length > 0 ? recruiterLeads.recruiters.map((recruiter) => {
                                    const rate = recruiterLeads.assigned_leads_count > 0
                                        ? Math.round((recruiter.leads_count / recruiterLeads.assigned_leads_count) * 1000) / 10 : 0;
                                    const transferRate = recruiter.leads_count > 0
                                        ? Math.round((recruiter.transferred_to_manager_count / recruiter.leads_count) * 1000) / 10 : 0;
                                    return (
                                        <tr className="transition-colors hover:bg-violet-50/50" key={recruiter.enum_id}>
                                            <td className="px-5 py-3.5 font-semibold text-gray-900">{recruiter.name}</td>
                                            <td className="px-4 py-3.5 font-mono font-semibold tabular-nums text-gray-900">{recruiter.leads_count}</td>
                                            <td className="px-4 py-3.5">
                                                <div className="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                                                    <ArrowRightLeft className="size-3" />
                                                    {recruiter.transferred_to_manager_count} · {transferRate}%
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <Progress value={rate} tone="brand" />
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td className="px-5 py-8" colSpan={4}>
                                            <EmptyState>
                                                {recruiterLeads.field_found
                                                    ? 'В поле "Рекрутер" пока нет значений или нет сделок за выбранный период.'
                                                    : 'Поле сделки "Рекрутер" не найдено. Запустите синхронизацию структуры CRM.'}
                                            </EmptyState>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </ReportSection>

                <ReportSection
                    eyebrow="Весь отдел рекрутинга"
                    title="Всего передано менеджерам по командам"
                    description={`Сделки с заполненными полями "Рекрутер" и "Менеджер", сгруппированные по полю "${recruiterTeamCityBreakdown.team_field_name}".`}
                    aside={<AccentSummary label="Передано менеджерам" value={recruiterTeamCityBreakdown.total_leads_count} note="по всему отделу" tone="warning" />}
                >
                    <BreakdownReportContent rows={departmentTeamRows(recruiterTeamCityBreakdown)} />
                </ReportSection>

                <ReportSection
                    eyebrow="Весь отдел рекрутинга"
                    title="Всего передано менеджерам по городам"
                    description={`Сделки с заполненными полями "Рекрутер" и "Менеджер", сгруппированные по полю "${recruiterTeamCityBreakdown.city_field_name}".`}
                    aside={<AccentSummary label="Передано менеджерам" value={recruiterTeamCityBreakdown.total_leads_count} note="по всему отделу" tone="warning" />}
                >
                    <BreakdownReportContent compactLegend rows={departmentCityRows(recruiterTeamCityBreakdown)} />
                </ReportSection>

                <ReportSection
                    eyebrow="Весь отдел рекрутинга"
                    title="Всего передано менеджерам по источникам"
                    description={`Сделки с заполненными полями "Рекрутер" и "Менеджер", сгруппированные по полю "${recruiterTeamCityBreakdown.source_field_name}".`}
                    aside={<AccentSummary label="Передано менеджерам" value={recruiterTeamCityBreakdown.total_leads_count} note="по всему отделу" tone="warning" />}
                >
                    <BreakdownReportContent compactLegend rows={departmentSourceChartRows(recruiterTeamCityBreakdown)} />
                </ReportSection>

                <ReportSection
                    eyebrow="Весь отдел рекрутинга"
                    title="Общая таблица по городам и источникам"
                    description={`Разрез по командам, городам и источникам. Источник: ${recruiterTeamCityBreakdown.source_field_found ? recruiterTeamCityBreakdown.source_field_name : 'поле не найдено'}.`}
                    aside={<AccentSummary label="Сделок в таблице" value={recruiterTeamCityBreakdown.total_leads_count} note="по всему отделу" tone="warning" />}
                >
                    <SourceBreakdownTable sourceColumns={recruiterTeamCityBreakdown.source_columns} rows={departmentSourceRows(recruiterTeamCityBreakdown)} />
                </ReportSection>

                <TaskStatisticsSection rows={taskStatistics} period={period} />

                <ReportSection
                    eyebrow="Передачи рекрутеров"
                    title="Подробно по каждому рекрутеру"
                    description="Сделки с заполненными полями «Рекрутер» и «Менеджер», сгруппированные по команде, городу и источнику."
                    aside={<AccentSummary label="Сделок в разрезе" value={recruiterTeamCityBreakdown.total_leads_count} note={`Источник: ${recruiterTeamCityBreakdown.source_field_found ? recruiterTeamCityBreakdown.source_field_name : 'поле не найдено'}`} tone="warning" />}
                >
                    <div className="grid gap-4 p-5">
                        {recruiterTeamCityBreakdown.recruiters.length > 0 ? recruiterTeamCityBreakdown.recruiters.map((recruiter) => (
                            <RecruiterCard key={recruiter.enum_id} recruiter={recruiter} breakdown={recruiterTeamCityBreakdown} />
                        )) : (
                            <EmptyState>
                                Нет данных для отчета. Проверьте, что выбраны поля «Команда» и «Город», а в сделках заполнены рекрутер и менеджер.
                            </EmptyState>
                        )}
                    </div>
                </ReportSection>

            </div>
        </div>
    );
}

function OverdueTasksModal({ userName, tasks, onClose }: { userName: string; tasks: OverdueTask[]; onClose: () => void }) {
    useEffect(() => {
        const handleKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [onClose]);

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="overdue-modal-title">
            <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
            <div className="relative z-10 flex max-h-[80vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-red-500">Просроченные задачи</p>
                        <h2 id="overdue-modal-title" className="mt-0.5 font-bold text-gray-900">{userName}</h2>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600" aria-label="Закрыть">
                        <X className="size-5" />
                    </button>
                </div>
                <div className="overflow-y-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="sticky top-0 bg-slate-50">
                            <tr>
                                <th className="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Задача</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Дедлайн</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Закрыта</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Просрочка</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {tasks.map((task, i) => (
                                <tr className="hover:bg-red-50/40" key={i}>
                                    <td className="max-w-xs px-5 py-3.5 text-gray-800">
                                        <span className="line-clamp-2">{task.text ?? '—'}</span>
                                    </td>
                                    <td className="px-4 py-3.5 text-right tabular-nums text-slate-500">{task.complete_till}</td>
                                    <td className="px-4 py-3.5 text-right tabular-nums text-slate-500">{task.completed_at}</td>
                                    <td className="px-4 py-3.5 text-right">
                                        <span className="inline-flex items-center justify-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-red-700 ring-1 ring-red-200">
                                            +{task.days_overdue} дн.
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="border-t border-slate-100 px-6 py-3 text-xs text-slate-400">
                    {tasks.length} задач · отсортировано по убыванию просрочки
                </div>
            </div>
        </div>,
        document.body,
    );
}

function TaskStatisticsSection({ rows, period }: { rows: TaskStatisticsGroup[]; period: { from: string; to: string } }) {
    const [overdueModal, setOverdueModal] = useState<{ userName: string; tasks: OverdueTask[] } | null>(null);
    const totalCompleted = rows.reduce((sum, g) => sum + g.completed_count, 0);
    const totalCompletedOverdue = rows.reduce((sum, g) => sum + g.completed_overdue_count, 0);
    const hasAny = rows.some((g) => g.users.length > 0);

    return (
        <ReportSection
            eyebrow="Отчет по задачам"
            title={`Задачи сотрудников: ${period.from} — ${period.to}`}
            description="Выполненные задачи за выбранный период, сгруппированные по группам. Просрочено — закрыты позже дедлайна. Доля просрочки считается от числа выполненных."
            aside={<AccentSummary label="Выполнено" value={totalCompleted} note={`просрочено: ${totalCompletedOverdue}`} tone="brand" />}
        >
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                        <tr>
                            <th className="px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Сотрудник</th>
                            <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Выполнено</th>
                            <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Просрочено</th>
                            <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">% просрочки</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {hasAny ? rows.map((group) => (
                            group.users.length === 0 ? null : (
                                <>
                                    <tr className="bg-slate-50" key={`group-${group.group_id ?? 'none'}`}>
                                        <td className="px-5 py-2.5" colSpan={2}>
                                            <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
                                                {group.group_name}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5 text-right">
                                            {group.completed_overdue_count > 0 ? (
                                                <span className="inline-flex items-center justify-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-red-600 ring-1 ring-red-200">
                                                    {group.completed_overdue_count}
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-2.5 text-xs tabular-nums text-slate-400">
                                            итого: {group.completed_count}
                                        </td>
                                    </tr>
                                    {group.users.map((row) => (
                                        <tr className="transition-colors hover:bg-violet-50/50" key={row.responsible_user_id}>
                                            <td className="px-5 py-3.5 pl-8 font-semibold text-gray-900">
                                                {row.responsible_name ?? `ID ${row.responsible_user_id}`}
                                            </td>
                                            <td className="px-4 py-3.5 text-right font-mono font-semibold tabular-nums text-gray-900">
                                                {row.completed_count}
                                            </td>
                                            <td className="px-4 py-3.5 text-right">
                                                {row.completed_overdue_count > 0 ? (
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center justify-center rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-red-700 ring-1 ring-red-200 transition-colors hover:bg-red-100 hover:ring-red-300"
                                                        title="Показать просроченные задачи"
                                                        onClick={() => setOverdueModal({ userName: row.responsible_name ?? `ID ${row.responsible_user_id}`, tasks: row.overdue_tasks })}
                                                    >
                                                        {row.completed_overdue_count}
                                                    </button>
                                                ) : (
                                                    <span className="text-slate-300 tabular-nums">0</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <Progress value={row.overdue_rate} tone={row.overdue_rate >= 50 ? 'danger' : row.overdue_rate >= 20 ? 'warning' : 'brand'} />
                                            </td>
                                        </tr>
                                    ))}
                                </>
                            )
                        )) : (
                            <tr>
                                <td className="px-5 py-8" colSpan={4}>
                                    <EmptyState>
                                        Нет данных по задачам за выбранный период. Запустите синхронизацию задач.
                                    </EmptyState>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </ReportSection>
        {overdueModal && (
            <OverdueTasksModal
                userName={overdueModal.userName}
                tasks={overdueModal.tasks}
                onClose={() => setOverdueModal(null)}
            />
        )}
    );
}

function RecruiterCard({ recruiter, breakdown }: { recruiter: RecruiterTeamCityBreakdown['recruiters'][number]; breakdown: RecruiterTeamCityBreakdown }) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <article className="overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200/60 shadow-sm">
            <button
                type="button"
                className="flex w-full items-center justify-between gap-3 bg-gradient-to-r from-slate-50 to-white px-5 py-4 text-left transition-colors hover:from-violet-50/60 hover:to-white"
                onClick={() => setIsOpen((v) => !v)}
            >
                <h3 className="font-bold text-gray-900">{recruiter.name}</h3>
                <div className="flex items-center gap-3">
                    <span className="rounded-full bg-gradient-to-r from-violet-500 to-indigo-600 px-3 py-1 text-xs font-bold tabular-nums text-white shadow-sm">
                        {recruiter.total_leads_count}
                    </span>
                    <ChevronDown className={`size-4 shrink-0 text-slate-400 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`} />
                </div>
            </button>
            <div className={`grid transition-[grid-template-rows] duration-300 ease-out ${isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}>
                <div className="overflow-hidden">
                    <div className="grid gap-4 border-t border-b border-slate-100 p-4 xl:grid-cols-2">
                        <BreakdownCard title="Передано менеджерам по командам" description={`Поле "${breakdown.team_field_name}"`} rows={recruiterTeamRows(recruiter)} />
                        <BreakdownCard compactLegend title="Всего по городам" description={`Поле "${breakdown.city_field_name}"`} rows={recruiterCityRows(recruiter)} />
                    </div>
                    <div className="overflow-x-auto">
                        <div className="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-5 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Детализация по городам и источникам
                        </div>
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                                <tr>
                                    <th className="px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Команда</th>
                                    <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Город</th>
                                    <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Всего</th>
                                    {breakdown.source_columns.map((source) => (
                                        <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500" key={source}>{source}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white">
                                {recruiter.teams.flatMap((team) => team.cities.map((city, index) => (
                                    <tr className="transition-colors hover:bg-violet-50/40" key={`${team.name}-${city.name}`}>
                                        {index === 0 ? (
                                            <td className="px-5 py-3.5 align-top font-bold text-gray-900" rowSpan={team.cities.length}>
                                                {team.name}
                                                <div className="mt-0.5 text-xs font-normal tabular-nums text-slate-400">{team.total_leads_count}</div>
                                            </td>
                                        ) : null}
                                        <td className="px-4 py-3.5 font-medium text-gray-700">{city.name}</td>
                                        <td className="px-4 py-3.5 text-right font-bold tabular-nums text-gray-900">{city.leads_count}</td>
                                        {breakdown.source_columns.map((source) => {
                                            const count = city.sources[source] || 0;
                                            return (
                                                <td className={count > 0 ? 'px-4 py-3.5 text-right font-semibold tabular-nums text-violet-600' : 'px-4 py-3.5 text-right tabular-nums text-slate-300'} key={source}>
                                                    {count}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                )))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </article>
    );
}

function SourceBreakdownTable({ rows, sourceColumns }: { rows: SourceBreakdownRow[]; sourceColumns: string[] }) {
    return (
        <div className="overflow-x-auto p-5">
            <table className="w-full min-w-[760px] text-left text-sm">
                <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                    <tr>
                        <th className="px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Команда</th>
                        <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Город</th>
                        <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Всего</th>
                        {sourceColumns.map((source) => (
                            <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500" key={source}>{source}</th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 bg-white">
                    {rows.map((row) => (
                        <tr className="transition-colors hover:bg-violet-50/40" key={`${row.team}-${row.city}`}>
                            <td className="px-5 py-3.5 font-bold text-gray-900">{row.team}</td>
                            <td className="px-4 py-3.5 font-medium text-gray-700">{row.city}</td>
                            <td className="px-4 py-3.5 text-right font-bold tabular-nums text-gray-900">{row.count}</td>
                            {sourceColumns.map((source) => {
                                const count = row.sources[source] || 0;
                                return (
                                    <td className={count > 0 ? 'px-4 py-3.5 text-right font-semibold tabular-nums text-violet-600' : 'px-4 py-3.5 text-right tabular-nums text-slate-300'} key={source}>
                                        {count}
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function BreakdownReportContent({ rows, compactLegend = false }: { rows: BreakdownRow[]; compactLegend?: boolean }) {
    return (
        <div className="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start">
            <BreakdownTable rows={rows} />
            <PieChart compactLegend={compactLegend} rows={rows} total={rows.reduce((sum, r) => sum + r.count, 0)} />
        </div>
    );
}

function BreakdownCard({ title, description, rows, compactLegend = false }: { title: string; description: string; rows: BreakdownRow[]; compactLegend?: boolean }) {
    const total = rows.reduce((sum, r) => sum + r.count, 0);
    return (
        <div className="rounded-2xl bg-white p-4 ring-1 ring-slate-200/80 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h4 className="text-sm font-bold text-gray-900">{title}</h4>
                    <div className="mt-1 text-xs text-slate-500">{description}</div>
                </div>
                <div className="rounded-full bg-gradient-to-r from-violet-500 to-indigo-600 px-2.5 py-1 text-xs font-bold tabular-nums text-white shadow-sm">
                    {total}
                </div>
            </div>
            {rows.length > 0 ? (
                <div className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_280px] xl:items-start">
                    <BreakdownTable rows={rows} />
                    <PieChart compactLegend={compactLegend} rows={rows} total={total} />
                </div>
            ) : (
                <div className="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-400">Нет данных для разреза.</div>
            )}
        </div>
    );
}

function BreakdownTable({ rows }: { rows: BreakdownRow[] }) {
    const total = rows.reduce((sum, r) => sum + r.count, 0);
    if (rows.length === 0) {
        return <div className="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">Нет данных для разреза.</div>;
    }
    return (
        <div className="overflow-hidden rounded-xl ring-1 ring-slate-200/80 shadow-sm">
            <div className="overflow-x-auto">
            <table className="w-full min-w-[260px] text-left text-sm">
                <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                    <tr>
                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Значение</th>
                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Сделок</th>
                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Доля</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 bg-white">
                    {rows.map((row, index) => (
                        <tr className="transition-colors hover:bg-violet-50/40" key={row.name}>
                            <td className="px-4 py-3">
                                <span className="mr-2 inline-block size-2.5 rounded-full" style={{ backgroundColor: chartColors[index % chartColors.length] }} />
                                <span className="font-medium text-gray-900">{row.name}</span>
                            </td>
                            <td className="px-4 py-3 text-right font-bold tabular-nums text-gray-900">{row.count}</td>
                            <td className="px-4 py-3 text-right tabular-nums text-slate-500">{percentOf(row.count, total)}%</td>
                        </tr>
                    ))}
                </tbody>
            </table>
            </div>
        </div>
    );
}

function PieChart({ rows, total, compactLegend = false }: { rows: BreakdownRow[]; total: number; compactLegend?: boolean }) {
    const [ready, setReady] = useState(false);
    useEffect(() => {
        const id = requestAnimationFrame(() => requestAnimationFrame(() => setReady(true)));
        return () => cancelAnimationFrame(id);
    }, []);

    let cumulative = 0;
    const radius = 15.91549430918954;
    const slices = rows.map((row, index) => {
        const value = total > 0 ? (row.count / total) * 100 : 0;
        const slice = { ...row, color: chartColors[index % chartColors.length], dasharray: `${value} ${100 - value}`, dashoffset: 25 - cumulative };
        cumulative += value;
        return slice;
    });

    return (
        <div className="rounded-xl bg-white p-4 ring-1 ring-slate-200/80 shadow-sm">
            <div className="flex justify-center">
                <svg className="size-36 -rotate-90" viewBox="0 0 36 36" role="img" aria-label="Круговая диаграмма">
                    <circle cx="18" cy="18" r={radius} fill="transparent" stroke="#F1F5F9" strokeWidth="4" />
                    {slices.map((slice, index) => (
                        <circle
                            cx="18"
                            cy="18"
                            fill="transparent"
                            key={slice.name}
                            r={radius}
                            stroke={slice.color}
                            strokeDasharray={ready ? slice.dasharray : '0 100'}
                            strokeDashoffset={slice.dashoffset}
                            strokeLinecap="butt"
                            strokeWidth="4"
                            style={{ transition: `stroke-dasharray 0.5s cubic-bezier(0.4, 0, 0.2, 1) ${index * 0.08}s` }}
                        />
                    ))}
                </svg>
            </div>
            <div className={compactLegend ? 'mt-3 grid gap-1.5 sm:grid-cols-2 xl:grid-cols-1' : 'mt-3 space-y-1.5'}>
                {slices.map((slice) => (
                    <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-lg bg-slate-50 px-3 py-2 text-xs ring-1 ring-slate-100" key={slice.name}>
                        <div className="flex min-w-0 items-center gap-2">
                            <span className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: slice.color }} />
                            <span className="truncate text-slate-600">{slice.name}</span>
                        </div>
                        <span className="font-bold tabular-nums text-gray-900">{slice.count}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function DebugPanel({ iframeMessages }: { iframeMessages: MessageLog[] }) {
    return (
        <ReportSection eyebrow="Iframe diagnostics" title="Диагностика параметров amoCRM" description="Этот блок виден только при debug_iframe=1.">
            <div className="grid gap-4 p-5 lg:grid-cols-2">
                <div className="rounded-2xl bg-sky-50 p-4 ring-1 ring-sky-200">
                    <div className="text-xs font-semibold uppercase tracking-wider text-sky-700">Location</div>
                    <dl className="mt-3 grid gap-2">
                        <DebugValue label="href" value={typeof window !== 'undefined' ? window.location.href : '-'} />
                        <DebugValue label="search" value={typeof window !== 'undefined' ? window.location.search || '-' : '-'} />
                        <DebugValue label="document.referrer" value={typeof document !== 'undefined' ? document.referrer || '-' : '-'} />
                    </dl>
                </div>
                <div className="rounded-2xl bg-white p-4 ring-1 ring-slate-200">
                    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <Database className="size-4 text-violet-500" />
                        postMessage events
                    </div>
                    <div className="mt-3 max-h-64 overflow-auto rounded-xl bg-slate-950 p-3 font-mono text-xs text-slate-100">
                        {iframeMessages.length > 0 ? iframeMessages.map((msg, i) => (
                            <pre className="mb-3 whitespace-pre-wrap break-words last:mb-0" key={`${msg.received_at}-${i}`}>
                                {JSON.stringify(msg, null, 2)}
                            </pre>
                        )) : (
                            <div className="text-slate-500">Сообщений пока нет.</div>
                        )}
                    </div>
                </div>
            </div>
        </ReportSection>
    );
}

function DebugValue({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-slate-500">{label}</dt>
            <dd className="break-all font-mono text-xs text-gray-900">{value}</dd>
        </div>
    );
}

function ReportSection({ eyebrow, title, description, aside, children }: { eyebrow: string; title: string; description?: string; aside?: ReactNode; children: ReactNode }) {
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

function AccentSummary({ label, value, note, tone }: { label: string; value: number; note: string; tone: 'brand' | 'warning' }) {
    const cls = tone === 'warning'
        ? 'bg-gradient-to-br from-amber-400 to-orange-500 shadow-amber-200/60'
        : 'bg-gradient-to-br from-violet-500 to-indigo-600 shadow-violet-200/60';
    return (
        <div className={`rounded-2xl px-5 py-4 text-right text-white shadow-lg ${cls}`}>
            <div className="text-xs font-semibold uppercase tracking-wider text-white/70">{label}</div>
            <div className="mt-1 text-4xl font-extrabold tabular-nums">{value}</div>
            <div className="mt-1 text-xs text-white/70">{note}</div>
        </div>
    );
}

function MetricCard({ label, value, icon, tone, progress, note }: { label: string; value: ReactNode; icon: ReactNode; tone: 'brand' | 'success' | 'warning' | 'danger' | 'neutral'; progress?: number; note?: string }) {
    const iconGradient = {
        brand: 'from-violet-500 to-indigo-600 shadow-violet-200',
        success: 'from-emerald-400 to-green-600 shadow-emerald-200',
        warning: 'from-amber-400 to-orange-500 shadow-amber-200',
        danger: 'from-red-400 to-rose-600 shadow-red-200',
        neutral: 'from-slate-400 to-slate-600 shadow-slate-200',
    }[tone];
    const barGradient = {
        brand: 'from-violet-400 to-indigo-600',
        success: 'from-emerald-400 to-green-500',
        warning: 'from-amber-400 to-orange-500',
        danger: 'from-red-400 to-rose-500',
        neutral: 'from-slate-400 to-slate-500',
    }[tone];

    return (
        <div className="rounded-2xl bg-white p-5 shadow-md ring-1 ring-slate-200/60 transition-shadow hover:shadow-lg">
            <div className={`flex size-12 items-center justify-center rounded-xl bg-gradient-to-br ${iconGradient} shadow-lg text-white`}>
                {icon}
            </div>
            <div className="mt-4 flex items-end justify-between gap-3">
                <div>
                    <div className="text-sm text-slate-500">{label}</div>
                    <div className="mt-1.5 text-4xl font-extrabold tabular-nums text-gray-900">{value}</div>
                </div>
                {note ? <div className="text-xs text-slate-400">{note}</div> : null}
            </div>
            {progress !== undefined ? (
                <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100">
                    <div className={`h-1.5 rounded-full bg-gradient-to-r ${barGradient} transition-all duration-700`} style={{ width: progressWidth(progress) }} />
                </div>
            ) : null}
        </div>
    );
}

function Progress({ value, tone }: { value: number; tone: 'brand' | 'warning' | 'danger' }) {
    const barGradient = {
        brand: 'from-violet-400 to-indigo-600',
        warning: 'from-amber-400 to-orange-500',
        danger: 'from-red-400 to-rose-500',
    }[tone];
    return (
        <div className="flex min-w-40 items-center gap-2.5">
            <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                <div className={`h-2 rounded-full bg-gradient-to-r ${barGradient}`} style={{ width: progressWidth(value) }} />
            </div>
            <span className="w-12 text-right text-sm font-semibold tabular-nums text-slate-600">{value}%</span>
        </div>
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
