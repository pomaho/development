import { createPortal } from 'react-dom';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import {
    ArrowRightLeft,
    CalendarDays,
    ChevronDown,
    Database,
    Download,
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
    plan_total: number | null;
    plan_completion_percent: number | null;
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
    days_in_period: number | null;
    leads_plan_per_day: number;
    plan_total: number | null;
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
    without_team_count: number;
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

type RecruiterScheduleRow = {
    enum_id: number;
    name: string;
    schedule_count: number;
};

type RecruiterScheduleBreakdown = {
    field_name: string;
    field_found: boolean;
    success_status_id: number | null;
    success_status_name: string;
    pipeline_id: number | null;
    pipeline_name: string | null;
    total_count: number;
    recruiters: RecruiterScheduleRow[];
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
};

type TaskStatisticsGroup = {
    group_id: number | null;
    group_name: string;
    completed_count: number;
    completed_overdue_count: number;
    users: TaskStatisticsRow[];
};

type ProjectVacancy = {
    name: string;
    leads_count: number;
    sources: Record<string, number>;
};

type ProjectCity = {
    name: string;
    leads_count: number;
    vacancies: ProjectVacancy[];
};

type ProjectCityVacancyProject = {
    name: string;
    total_leads_count: number;
    cities: ProjectCity[];
};

type ProjectCityVacancyTeam = {
    name: string;
    total_leads_count: number;
    projects: ProjectCityVacancyProject[];
};

type ProjectCityVacancyBreakdown = {
    pipeline_id: number | null;
    pipeline_name: string | null;
    manager_field_found: boolean;
    manager_field_name: string;
    recruiter_field_found: boolean;
    recruiter_field_name: string;
    team_field_found: boolean;
    team_field_name: string;
    project_field_found: boolean;
    project_field_name: string;
    city_field_found: boolean;
    city_field_name: string;
    vacancy_field_found: boolean;
    vacancy_field_name: string;
    source_field_found: boolean;
    source_field_name: string;
    source_columns: string[];
    total_leads_count: number;
    projects: ProjectCityVacancyProject[];
    teams: ProjectCityVacancyTeam[];
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
    links: {
        self: string;
        recruiterLeads: string;
        recruiterTeamCityBreakdown: string;
        taskStatistics: string;
        userOverdueTasks: string;
        projectCityVacancy: string;
        projectCityVacancyLeads: string;
        recruiterSchedule: string;
        export: string;
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

type LoadState<T> = { status: 'loading' } | { status: 'ok'; data: T } | { status: 'error'; message: string };

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
    const result = sortedBreakdownRows(rows);
    if ((breakdown.without_team_count ?? 0) > 0) {
        result.push({ name: '—', count: breakdown.without_team_count });
    }
    return result;
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

const sortedBreakdownRows = (rows: Map<string, number>): BreakdownRow[] => (
    Array.from(rows.entries()).map(([name, count]) => ({ name, count })).sort((l, r) => r.count - l.count || l.name.localeCompare(r.name))
);

async function apiFetch<T>(url: string, params: Record<string, string>): Promise<T> {
    const u = new URL(url);
    Object.entries(params).forEach(([k, v]) => u.searchParams.set(k, v));
    const res = await fetch(u.toString());
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    return json.data as T;
}

function useApiData<T>(url: string, params: Record<string, string>): LoadState<T> {
    const [state, setState] = useState<LoadState<T>>({ status: 'loading' });
    const key = url + JSON.stringify(params);
    useEffect(() => {
        setState({ status: 'loading' });
        let cancelled = false;
        apiFetch<T>(url, params)
            .then((data) => { if (!cancelled) setState({ status: 'ok', data }); })
            .catch((err: unknown) => { if (!cancelled) setState({ status: 'error', message: String(err) }); });
        return () => { cancelled = true; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key]);
    return state;
}

export default function TaskOverdueDashboardV2({ account, period, links }: Props) {
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

    const periodParams = { from: period.from, to: period.to };

    const recruiterLeadsState = useApiData<RecruiterLeads>(links.recruiterLeads, periodParams);
    const breakdownState = useApiData<RecruiterTeamCityBreakdown>(links.recruiterTeamCityBreakdown, periodParams);
    const taskStatsState = useApiData<TaskStatisticsGroup[]>(links.taskStatistics, periodParams);
    const projectCityVacancyState = useApiData<ProjectCityVacancyBreakdown>(links.projectCityVacancy, periodParams);
    const scheduleState = useApiData<RecruiterScheduleBreakdown>(links.recruiterSchedule, periodParams);

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
                            <div className="mt-3">
                                <a
                                    href={`${links.export}?from=${period.from}&to=${period.to}`}
                                    className="inline-flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-emerald-600/20 px-4 text-sm font-semibold text-emerald-300 ring-1 ring-emerald-500/30 hover:bg-emerald-600/30 hover:text-emerald-200"
                                    download
                                >
                                    <Download className="size-3.5" />
                                    Экспорт в Excel
                                </a>
                            </div>
                        </form>
                    </div>
                </header>

                {debugIframe ? <DebugPanel iframeMessages={iframeMessages} /> : null}

                <RecruiterLeadsSection state={recruiterLeadsState} leadsUrl={links.projectCityVacancyLeads} periodParams={periodParams} baseDomain={account.base_domain} />

                <RecruiterScheduleSection state={scheduleState} leadsUrl={links.projectCityVacancyLeads} periodParams={periodParams} baseDomain={account.base_domain} />

                <RecruiterBreakdownSections
                    state={breakdownState}
                    leadsUrl={links.projectCityVacancyLeads}
                    periodParams={periodParams}
                    baseDomain={account.base_domain}
                />

                <ProjectCityVacancySection
                    state={projectCityVacancyState}
                    leadsUrl={links.projectCityVacancyLeads}
                    periodParams={periodParams}
                    baseDomain={account.base_domain}
                />

                <RecruiterDetailSection state={breakdownState} leadsUrl={links.projectCityVacancyLeads} periodParams={periodParams} baseDomain={account.base_domain} />

                <TaskStatisticsSection state={taskStatsState} period={period} userOverdueTasksUrl={links.userOverdueTasks} />

            </div>
        </div>
    );
}

function RecruiterScheduleSection({ state, leadsUrl, periodParams, baseDomain }: { state: LoadState<RecruiterScheduleBreakdown>; leadsUrl: string; periodParams: Record<string, string>; baseDomain: string }) {
    const [leadsFilter, setLeadsFilter] = useState<LeadsFilter | null>(null);
    if (state.status === 'loading') return <SectionSkeleton rows={4} />;
    if (state.status === 'error') return <SectionError message={state.message} />;
    const data = state.data;

    if (!data.success_status_id) {
        return null;
    }

    const maxCount = Math.max(...data.recruiters.map((r) => r.schedule_count), 1);
    const sid = data.success_status_id;

    return (
        <>
        <ReportSection
            eyebrow="Результаты рекрутинга"
            title="Встал в график"
            description={`Сделки с заполненными полями "Рекрутер" и "Менеджер", достигшие этапа "Встал в график". Воронка: ${data.pipeline_name || 'все воронки'}.`}
            aside={
                <button type="button" onClick={() => setLeadsFilter({ project: '', city: '', vacancy: '', source: '', status_id: sid, label: `Встал в график — все рекрутеры` })}>
                    <AccentSummary
                        label="Встали в график"
                        value={data.total_count}
                        note="нажмите чтобы открыть список"
                        tone="success"
                    />
                </button>
            }
        >
            {data.recruiters.length > 0 ? (
                <div className="divide-y divide-slate-100 pb-5">
                    {data.recruiters.map((recruiter, index) => {
                        const barWidth = maxCount > 0 ? Math.round((recruiter.schedule_count / maxCount) * 100) : 0;
                        return (
                            <div className="flex items-center gap-4 px-5 py-3.5" key={recruiter.enum_id}>
                                <div className="w-6 shrink-0 text-right text-xs font-bold text-slate-400 tabular-nums">
                                    {index + 1}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="mb-1.5 flex items-center justify-between gap-3">
                                        <span className="truncate font-semibold text-gray-900">{recruiter.name}</span>
                                        <button
                                            type="button"
                                            className="shrink-0 rounded-full bg-emerald-500 px-3 py-0.5 text-xs font-bold tabular-nums text-white hover:bg-emerald-600 transition-colors"
                                            onClick={() => setLeadsFilter({ project: '', city: '', vacancy: '', source: '', recruiter_enum_id: recruiter.enum_id, status_id: sid, label: `${recruiter.name} — Встал в график` })}
                                        >
                                            {recruiter.schedule_count}
                                        </button>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-emerald-50">
                                        <div
                                            className="h-full rounded-full bg-gradient-to-r from-emerald-400 to-green-500 transition-all duration-500"
                                            style={{ width: `${barWidth}%` }}
                                        />
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            ) : (
                <div className="flex flex-col items-center justify-center gap-2 py-10 text-slate-400">
                    <Inbox className="size-8" />
                    <p className="text-sm">Нет сделок на этапе "{data.success_status_name}" за выбранный период</p>
                </div>
            )}
        </ReportSection>
        {leadsFilter !== null && (
            <LeadsModal filter={leadsFilter} leadsUrl={leadsUrl} periodParams={periodParams} baseDomain={baseDomain} onClose={() => setLeadsFilter(null)} />
        )}
        </>
    );
}

function RecruiterLeadsSection({ state, leadsUrl, periodParams, baseDomain }: { state: LoadState<RecruiterLeads>; leadsUrl: string; periodParams: Record<string, string>; baseDomain: string }) {
    const [leadsFilter, setLeadsFilter] = useState<LeadsFilter | null>(null);
    if (state.status === 'loading') return <SectionSkeleton rows={4} />;
    if (state.status === 'error') return <SectionError message={state.message} />;
    const recruiterLeads = state.data;

    return (
        <>
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
                            <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Выполнение плана</th>
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
                                    <td className="px-4 py-3.5">
                                        <CountButton value={recruiter.leads_count} onClick={() => setLeadsFilter({ project: '', city: '', vacancy: '', source: '', recruiter_enum_id: recruiter.enum_id, manager_required: false, label: `${recruiter.name} — все сделки` })} />
                                    </td>
                                    <td className="px-4 py-3.5">
                                        <button type="button" className="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100 transition-colors" onClick={() => setLeadsFilter({ project: '', city: '', vacancy: '', source: '', recruiter_enum_id: recruiter.enum_id, manager_required: true, label: `${recruiter.name} — передано менеджеру` })}>
                                            <ArrowRightLeft className="size-3" />
                                            {recruiter.transferred_to_manager_count} · {transferRate}%
                                        </button>
                                    </td>
                                    <td className="px-4 py-3.5">
                                        <Progress value={rate} tone="brand" />
                                    </td>
                                    <td className="px-4 py-3.5">
                                        {recruiter.plan_completion_percent !== null ? (
                                            <div className="flex flex-col gap-1">
                                                <Progress
                                                    value={Math.min(recruiter.plan_completion_percent, 100)}
                                                    tone={recruiter.plan_completion_percent >= 100 ? 'brand' : recruiter.plan_completion_percent >= 70 ? 'warning' : 'danger'}
                                                />
                                                <span className="text-xs font-semibold text-slate-600">
                                                    {recruiter.plan_completion_percent}% ({recruiter.leads_count} / {recruiter.plan_total})
                                                </span>
                                            </div>
                                        ) : (
                                            <span className="text-xs text-slate-400">—</span>
                                        )}
                                    </td>
                                </tr>
                            );
                        }) : (
                            <tr>
                                <td className="px-5 py-8" colSpan={5}>
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
        {leadsFilter !== null && (
            <LeadsModal filter={leadsFilter} leadsUrl={leadsUrl} periodParams={periodParams} baseDomain={baseDomain} onClose={() => setLeadsFilter(null)} />
        )}
        </>
    );
}


function RecruiterBreakdownSections({ state, leadsUrl, periodParams, baseDomain }: {
    state: LoadState<RecruiterTeamCityBreakdown>;
    leadsUrl: string;
    periodParams: Record<string, string>;
    baseDomain: string;
}) {
    const [leadsFilter, setLeadsFilter] = useState<LeadsFilter | null>(null);

    if (state.status === 'loading') return (
        <>
            <SectionSkeleton rows={3} />
            <SectionSkeleton rows={3} />
            <SectionSkeleton rows={3} />
        </>
    );
    if (state.status === 'error') return <SectionError message={state.message} />;
    const breakdown = state.data;

    const openLeads = (filter: Omit<LeadsFilter, 'label'> & { label: string }) => setLeadsFilter(filter);

    return (
        <>
            <ReportSection
                eyebrow="Весь отдел рекрутинга"
                title="Всего передано менеджерам по командам"
                description={`Сделки с заполненными полями "Рекрутер" и "Менеджер", сгруппированные по полю "${breakdown.team_field_name}".`}
                aside={<AccentSummary label="Передано менеджерам" value={breakdown.total_leads_count} note="по всему отделу" tone="warning" />}
            >
                <BreakdownReportContent rows={departmentTeamRows(breakdown)} onRowClick={(name) => openLeads({ project: '', city: '', vacancy: '', source: '', team: name, label: name === '—' ? 'Без команды' : name })} />
            </ReportSection>

            <ReportSection
                eyebrow="Весь отдел рекрутинга"
                title="Всего передано менеджерам по городам"
                description={`Сделки с заполненными полями "Рекрутер" и "Менеджер", сгруппированные по полю "${breakdown.city_field_name}".`}
                aside={<AccentSummary label="Передано менеджерам" value={breakdown.total_leads_count} note="по всему отделу" tone="warning" />}
            >
                <BreakdownReportContent compactLegend rows={departmentCityRows(breakdown)} onRowClick={(name) => openLeads({ project: '', city: name, vacancy: '', source: '', label: name })} />
            </ReportSection>

            <ReportSection
                eyebrow="Весь отдел рекрутинга"
                title="Всего передано менеджерам по источникам"
                description={`Сделки с заполненными полями "Рекрутер" и "Менеджер", сгруппированные по полю "${breakdown.source_field_name}".`}
                aside={<AccentSummary label="Передано менеджерам" value={breakdown.total_leads_count} note="по всему отделу" tone="warning" />}
            >
                <BreakdownReportContent compactLegend rows={departmentSourceChartRows(breakdown)} onRowClick={(name) => openLeads({ project: '', city: '', vacancy: '', source: name, label: name })} />
            </ReportSection>

            {leadsFilter !== null && (
                <LeadsModal
                    filter={leadsFilter}
                    leadsUrl={leadsUrl}
                    periodParams={periodParams}
                    baseDomain={baseDomain}
                    onClose={() => setLeadsFilter(null)}
                />
            )}
        </>
    );
}

function RecruiterDetailSection({ state, leadsUrl, periodParams, baseDomain }: { state: LoadState<RecruiterTeamCityBreakdown>; leadsUrl: string; periodParams: Record<string, string>; baseDomain: string }) {
    if (state.status === 'loading') return <SectionSkeleton rows={5} />;
    if (state.status === 'error') return null;
    const breakdown = state.data;

    return (
        <ReportSection
            eyebrow="Передачи рекрутеров"
            title="Подробно по каждому рекрутеру"
            description="Сделки с заполненными полями «Рекрутер» и «Менеджер», сгруппированные по команде, городу и источнику."
            aside={<AccentSummary label="Сделок в разрезе" value={breakdown.total_leads_count} note={`Источник: ${breakdown.source_field_found ? breakdown.source_field_name : 'поле не найдено'}`} tone="warning" />}
        >
            <div className="grid gap-4 p-5">
                {breakdown.recruiters.length > 0 ? breakdown.recruiters.map((recruiter) => (
                    <RecruiterCard key={recruiter.enum_id} recruiter={recruiter} breakdown={breakdown} leadsUrl={leadsUrl} periodParams={periodParams} baseDomain={baseDomain} />
                )) : (
                    <EmptyState>
                        Нет данных для отчета. Проверьте, что выбраны поля «Команда» и «Город», а в сделках заполнены рекрутер и менеджер.
                    </EmptyState>
                )}
            </div>
        </ReportSection>
    );
}

type LeadItem = {
    id: number;
    name: string;
    created_at: string | null;
};

type LeadsResult = {
    leads: LeadItem[];
    total: number;
    limited: boolean;
    limit: number;
};

type LeadsFilter = {
    project: string;
    city: string;
    vacancy: string;
    source: string;
    team?: string;
    recruiter_enum_id?: number;
    manager_required?: boolean;
    status_id?: number;
    label: string;
};

function aggregateProjectSources(project: ProjectCityVacancyProject, columns: string[]): Record<string, number> {
    const result: Record<string, number> = {};
    for (const city of project.cities) {
        for (const vacancy of city.vacancies) {
            for (const col of columns) {
                result[col] = (result[col] ?? 0) + ((vacancy.sources ?? {})[col] ?? 0);
            }
        }
    }
    return result;
}

function aggregateTeamSources(team: ProjectCityVacancyTeam, columns: string[]): Record<string, number> {
    const result: Record<string, number> = {};
    for (const project of team.projects) {
        const ps = aggregateProjectSources(project, columns);
        for (const col of columns) {
            result[col] = (result[col] ?? 0) + (ps[col] ?? 0);
        }
    }
    return result;
}

function LeadsModal({
    filter,
    leadsUrl,
    periodParams,
    baseDomain,
    onClose,
}: {
    filter: LeadsFilter;
    leadsUrl: string;
    periodParams: Record<string, string>;
    baseDomain: string;
    onClose: () => void;
}) {
    const params = { ...periodParams, project: filter.project, city: filter.city, vacancy: filter.vacancy, source: filter.source, team: filter.team ?? '', recruiter_enum_id: filter.recruiter_enum_id ?? 0, manager_required: filter.manager_required === false ? '0' : '1', status_id: filter.status_id ?? 0 };
    const leadsState = useApiData<LeadsResult>(leadsUrl, params);

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
                {leadsState.status === 'loading' && (
                    <div className="flex items-center justify-center py-16 text-slate-400">
                        <svg className="mr-2 size-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" /></svg>
                        Загрузка...
                    </div>
                )}
                {leadsState.status === 'error' && (
                    <div className="px-6 py-8 text-center text-sm text-red-500">Ошибка загрузки: {leadsState.message}</div>
                )}
                {leadsState.status === 'ok' && (
                    <>
                        {leadsState.data.limited && (
                            <div className="border-b border-amber-100 bg-amber-50 px-6 py-2 text-xs text-amber-700">
                                Показаны первые {leadsState.data.limit} из {leadsState.data.total} сделок
                            </div>
                        )}
                        <div className="overflow-y-auto">
                            {leadsState.data.leads.length === 0 ? (
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
                                        {leadsState.data.leads.map((lead) => (
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
                            Итого: {leadsState.data.total} сделок
                        </div>
                    </>
                )}
            </div>
        </div>,
        document.body,
    );
}

function CountButton({ value, onClick }: { value: number; onClick: () => void }) {
    if (value === 0) {
        return <span className="font-mono tabular-nums text-slate-300">0</span>;
    }
    return (
        <button
            type="button"
            onClick={onClick}
            className="font-mono font-semibold tabular-nums text-indigo-700 underline-offset-2 hover:underline"
        >
            {value}
        </button>
    );
}

function ProjectCityVacancySection({ state, leadsUrl, periodParams, baseDomain }: {
    state: LoadState<ProjectCityVacancyBreakdown>;
    leadsUrl: string;
    periodParams: Record<string, string>;
    baseDomain: string;
}) {
    const [leadsFilter, setLeadsFilter] = useState<LeadsFilter | null>(null);

    if (state.status === 'loading') return <SectionSkeleton rows={5} />;
    if (state.status === 'error') return <SectionError message={state.message} />;
    const data = state.data;

    const openLeads = (filter: LeadsFilter) => setLeadsFilter(filter);
    const closeLeads = () => setLeadsFilter(null);

    return (
        <>
            <ReportSection
                eyebrow="Весь отдел рекрутинга"
                title="Разрез по команде, проекту, городу и вакансии"
                description={`Сделки переданные менеджерам, сгруппированные по "${data.team_field_name}" → "${data.project_field_name}" → "${data.city_field_name}" → "${data.vacancy_field_name}". Нажмите на число — откроется список сделок.`}
                aside={<AccentSummary label="Сделок в таблице" value={data.total_leads_count} note="передано менеджерам" tone="warning" />}
            >
                {data.teams.length === 0 ? (
                    <div className="px-5 py-8">
                        <EmptyState>
                            {!data.city_field_found
                                ? `Поле "${data.city_field_name}" не найдено. Запустите синхронизацию структуры CRM.`
                                : 'Нет сделок переданных менеджерам за выбранный период.'}
                        </EmptyState>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                                <tr>
                                    <th className="px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{data.team_field_name} / {data.project_field_name}</th>
                                    <th className="px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{data.city_field_name}</th>
                                    <th className="px-5 py-3.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{data.vacancy_field_name}</th>
                                    <th className="w-20 px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Всего</th>
                                    {data.source_columns.map((src) => (
                                        <th key={src} className="w-24 px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{src}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {data.teams.map((team) => {
                                    const teamSources = aggregateTeamSources(team, data.source_columns);
                                    return (
                                        <>
                                            <tr key={`team-${team.name}`} className="bg-indigo-50">
                                                <td className="border-l-4 border-indigo-500 px-5 py-2 font-bold text-indigo-900" colSpan={3}>
                                                    {team.name === '—' ? <span className="italic text-slate-400">Без команды</span> : team.name}
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    <CountButton
                                                        value={team.total_leads_count}
                                                        onClick={() => openLeads({ team: team.name, project: '', city: '', vacancy: '', source: '', label: team.name === '—' ? 'Без команды' : team.name })}
                                                    />
                                                </td>
                                                {data.source_columns.map((src) => (
                                                    <td key={src} className="px-4 py-2 text-right">
                                                        <CountButton
                                                            value={teamSources[src] ?? 0}
                                                            onClick={() => openLeads({ team: team.name, project: '', city: '', vacancy: '', source: src, label: `${team.name} / ${src}` })}
                                                        />
                                                    </td>
                                                ))}
                                            </tr>
                                            {team.projects.map((project) => {
                                                const projectSources = aggregateProjectSources(project, data.source_columns);
                                                return (
                                                    <>
                                                        <tr key={`project-${team.name}-${project.name}`} className="bg-slate-100">
                                                            <td className="border-l-4 border-slate-300 pl-9 pr-5 py-2 font-semibold text-slate-700" colSpan={3}>
                                                                {project.name}
                                                            </td>
                                                            <td className="px-4 py-2 text-right">
                                                                <CountButton
                                                                    value={project.total_leads_count}
                                                                    onClick={() => openLeads({ team: team.name, project: project.name, city: '', vacancy: '', source: '', label: `${team.name} / ${project.name}` })}
                                                                />
                                                            </td>
                                                            {data.source_columns.map((src) => (
                                                                <td key={src} className="px-4 py-2 text-right">
                                                                    <CountButton
                                                                        value={projectSources[src] ?? 0}
                                                                        onClick={() => openLeads({ team: team.name, project: project.name, city: '', vacancy: '', source: src, label: `${team.name} / ${project.name} / ${src}` })}
                                                                    />
                                                                </td>
                                                            ))}
                                                        </tr>
                                                        {project.cities.map((city) =>
                                                            (city.vacancies.length > 0 ? city.vacancies : [{ name: '—', leads_count: 0, sources: {} }]).map((vacancy, vi) => (
                                                                <tr
                                                                    key={`${team.name}-${project.name}-${city.name}-${vacancy.name}`}
                                                                    className="border-t border-slate-100 transition-colors hover:bg-violet-50/40"
                                                                >
                                                                    <td className="pl-14 pr-3 py-2 text-slate-400 text-xs" />
                                                                    <td className="px-5 py-2 text-slate-600 text-xs">
                                                                        {vi === 0 ? (city.name === '—' ? <span className="italic text-slate-400">Без города</span> : city.name) : ''}
                                                                    </td>
                                                                    <td className="px-5 py-2 text-gray-500 text-xs">
                                                                        {vacancy.name !== '—' ? vacancy.name : ''}
                                                                    </td>
                                                                    <td className="px-4 py-2 text-right">
                                                                        <CountButton
                                                                            value={vacancy.leads_count}
                                                                            onClick={() => openLeads({
                                                                                team: team.name,
                                                                                project: project.name,
                                                                                city: city.name,
                                                                                vacancy: vacancy.name,
                                                                                source: '',
                                                                                label: `${team.name} / ${project.name} / ${city.name === '—' ? 'Без города' : city.name}${vacancy.name !== '—' ? ` / ${vacancy.name}` : ''}`,
                                                                            })}
                                                                        />
                                                                    </td>
                                                                    {data.source_columns.map((src) => (
                                                                        <td key={src} className="px-4 py-2 text-right">
                                                                            <CountButton
                                                                                value={(vacancy.sources ?? {})[src] ?? 0}
                                                                                onClick={() => openLeads({
                                                                                    team: team.name,
                                                                                    project: project.name,
                                                                                    city: city.name,
                                                                                    vacancy: vacancy.name,
                                                                                    source: src,
                                                                                    label: `${team.name} / ${project.name} / ${city.name === '—' ? 'Без города' : city.name}${vacancy.name !== '—' ? ` / ${vacancy.name}` : ''} / ${src}`,
                                                                                })}
                                                                            />
                                                                        </td>
                                                                    ))}
                                                                </tr>
                                                            ))
                                                        )}
                                                    </>
                                                );
                                            })}
                                        </>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
                </ReportSection>

            {leadsFilter !== null && (
                <LeadsModal
                    filter={leadsFilter}
                    leadsUrl={leadsUrl}
                    periodParams={periodParams}
                    baseDomain={baseDomain}
                    onClose={closeLeads}
                />
            )}
        </>
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
            <div className="px-5 py-8">
                <EmptyState>Ошибка загрузки данных: {message}</EmptyState>
            </div>
        </section>
    );
}

function OverdueTasksModal({ userName, tasks, onClose }: { userName: string; tasks: OverdueTask[] | null; onClose: () => void }) {
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
                {tasks === null ? (
                    <div className="flex items-center justify-center py-16 text-slate-400">
                        <svg className="mr-2 size-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" /></svg>
                        Загрузка...
                    </div>
                ) : (
                    <>
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
                    </>
                )}
            </div>
        </div>,
        document.body,
    );
}

function TaskStatisticsSection({ state, period, userOverdueTasksUrl }: { state: LoadState<TaskStatisticsGroup[]>; period: { from: string; to: string }; userOverdueTasksUrl: string }) {
    const [overdueModal, setOverdueModal] = useState<{ userName: string; userId: number; tasks: OverdueTask[] | null } | null>(null);

    const handleOverdueClick = async (userName: string, userId: number) => {
        setOverdueModal({ userName, userId, tasks: null });
        const url = new URL(userOverdueTasksUrl);
        url.searchParams.set('user_id', String(userId));
        url.searchParams.set('from', period.from);
        url.searchParams.set('to', period.to);
        const res = await fetch(url.toString());
        const data = await res.json();
        setOverdueModal((prev) => prev?.userId === userId ? { ...prev, tasks: data.tasks } : prev);
    };

    if (state.status === 'loading') return <SectionSkeleton rows={5} />;
    if (state.status === 'error') return <SectionError message={state.message} />;

    const rows = state.data;
    const totalCompleted = rows.reduce((sum, g) => sum + g.completed_count, 0);
    const totalCompletedOverdue = rows.reduce((sum, g) => sum + g.completed_overdue_count, 0);
    const hasAny = rows.some((g) => g.users.length > 0);

    return (
        <>
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
                                                        onClick={() => handleOverdueClick(row.responsible_name ?? `ID ${row.responsible_user_id}`, row.responsible_user_id)}
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
        </>
    );
}

function RecruiterCard({ recruiter, breakdown, leadsUrl, periodParams, baseDomain }: { recruiter: RecruiterTeamCityBreakdown['recruiters'][number]; breakdown: RecruiterTeamCityBreakdown; leadsUrl: string; periodParams: Record<string, string>; baseDomain: string }) {
    const [isOpen, setIsOpen] = useState(false);
    const [leadsFilter, setLeadsFilter] = useState<LeadsFilter | null>(null);
    const rid = recruiter.enum_id;

    const openLeads = (extra: Partial<LeadsFilter> & { label: string }) =>
        setLeadsFilter({ project: '', city: '', vacancy: '', source: '', recruiter_enum_id: rid, ...extra });

    return (
        <>
        <article className="overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200/60 shadow-sm">
            <button
                type="button"
                className="flex w-full items-center justify-between gap-3 bg-gradient-to-r from-slate-50 to-white px-5 py-4 text-left transition-colors hover:from-violet-50/60 hover:to-white"
                onClick={() => setIsOpen((v) => !v)}
            >
                <h3 className="font-bold text-gray-900">{recruiter.name}</h3>
                <div className="flex items-center gap-3">
                    <button type="button" className="rounded-full bg-gradient-to-r from-violet-500 to-indigo-600 px-3 py-1 text-xs font-bold tabular-nums text-white shadow-sm hover:opacity-80 transition-opacity" onClick={(e) => { e.stopPropagation(); openLeads({ label: recruiter.name }); }}>
                        {recruiter.total_leads_count}
                    </button>
                    <ChevronDown className={`size-4 shrink-0 text-slate-400 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`} />
                </div>
            </button>
            <div className={`grid transition-[grid-template-rows] duration-300 ease-out ${isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}>
                <div className="overflow-hidden">
                    <div className="grid gap-4 border-t border-b border-slate-100 p-4 xl:grid-cols-2">
                        <BreakdownCard title="Передано менеджерам по командам" description={`Поле "${breakdown.team_field_name}"`} rows={recruiterTeamRows(recruiter)} onRowClick={(name) => openLeads({ team: name, label: `${recruiter.name} / ${name}` })} />
                        <BreakdownCard compactLegend title="Всего по городам" description={`Поле "${breakdown.city_field_name}"`} rows={recruiterCityRows(recruiter)} onRowClick={(name) => openLeads({ city: name, label: `${recruiter.name} / ${name}` })} />
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
                                                <div className="mt-0.5">
                                                    <CountButton value={team.total_leads_count} onClick={() => openLeads({ team: team.name, label: `${recruiter.name} / ${team.name}` })} />
                                                </div>
                                            </td>
                                        ) : null}
                                        <td className="px-4 py-3.5 font-medium text-gray-700">{city.name}</td>
                                        <td className="px-4 py-3.5 text-right">
                                            <CountButton value={city.leads_count} onClick={() => openLeads({ team: team.name, city: city.name, label: `${recruiter.name} / ${team.name} / ${city.name}` })} />
                                        </td>
                                        {breakdown.source_columns.map((source) => (
                                            <td className="px-4 py-3.5 text-right" key={source}>
                                                <CountButton value={city.sources[source] || 0} onClick={() => openLeads({ team: team.name, city: city.name, source, label: `${recruiter.name} / ${team.name} / ${city.name} / ${source}` })} />
                                            </td>
                                        ))}
                                    </tr>
                                )))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </article>
        {leadsFilter !== null && (
            <LeadsModal filter={leadsFilter} leadsUrl={leadsUrl} periodParams={periodParams} baseDomain={baseDomain} onClose={() => setLeadsFilter(null)} />
        )}
        </>
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

function BreakdownReportContent({ rows, compactLegend = false, onRowClick }: { rows: BreakdownRow[]; compactLegend?: boolean; onRowClick?: (name: string) => void }) {
    return (
        <div className="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start">
            <BreakdownTable rows={rows} onRowClick={onRowClick} />
            <PieChart compactLegend={compactLegend} rows={rows} total={rows.reduce((sum, r) => sum + r.count, 0)} />
        </div>
    );
}

function BreakdownCard({ title, description, rows, compactLegend = false, onRowClick }: { title: string; description: string; rows: BreakdownRow[]; compactLegend?: boolean; onRowClick?: (name: string) => void }) {
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
                    <BreakdownTable rows={rows} onRowClick={onRowClick} />
                    <PieChart compactLegend={compactLegend} rows={rows} total={total} />
                </div>
            ) : (
                <div className="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-400">Нет данных для разреза.</div>
            )}
        </div>
    );
}

function BreakdownTable({ rows, onRowClick }: { rows: BreakdownRow[]; onRowClick?: (name: string) => void }) {
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
                                {row.name === '—'
                                    ? <span className="italic text-slate-400">Без команды</span>
                                    : <span className="font-medium text-gray-900">{row.name}</span>
                                }
                            </td>
                            <td className="px-4 py-3 text-right font-bold tabular-nums text-gray-900">
                                {onRowClick
                                    ? <CountButton value={row.count} onClick={() => onRowClick(row.name)} />
                                    : row.count
                                }
                            </td>
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

function AccentSummary({ label, value, note, tone }: { label: string; value: number; note: string; tone: 'brand' | 'warning' | 'success' }) {
    const cls = tone === 'warning'
        ? 'bg-gradient-to-br from-amber-400 to-orange-500 shadow-amber-200/60'
        : tone === 'success'
        ? 'bg-gradient-to-br from-emerald-400 to-green-600 shadow-emerald-200/60'
        : 'bg-gradient-to-br from-violet-500 to-indigo-600 shadow-violet-200/60';
    return (
        <div className={`rounded-2xl px-5 py-4 text-right text-white shadow-lg ${cls}`}>
            <div className="text-xs font-semibold uppercase tracking-wider text-white/70">{label}</div>
            <div className="mt-1 text-4xl font-extrabold tabular-nums">{value}</div>
            <div className="mt-1 text-xs text-white/70">{note}</div>
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
