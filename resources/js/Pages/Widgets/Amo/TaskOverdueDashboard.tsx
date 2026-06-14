import { useEffect, useMemo, useState, type ReactNode } from 'react';
import {
    AlertTriangle,
    ArrowRightLeft,
    CalendarDays,
    CheckCircle2,
    Database,
    Percent,
    Users,
} from 'lucide-react';

type Account = {
    name: string;
    base_domain: string;
};

type UserRow = {
    id: number;
    name: string;
    completed_count: number;
    completed_overdue_count: number;
    overdue_rate: number;
};

type GroupRow = {
    group_id: number | null;
    group_name: string;
    completed_count: number;
    completed_overdue_count: number;
    users: UserRow[];
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

type Props = {
    account: Account;
    period: {
        from: string;
        to: string;
        source: string;
        preset: string | null;
        label: string;
    };
    groups: GroupRow[];
    recruiterLeads: RecruiterLeads;
    recruiterTeamCityBreakdown: RecruiterTeamCityBreakdown;
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

const chartColors = ['#465FFF', '#12B76A', '#F79009', '#F04438', '#7A5AF8', '#06AED4', '#DB2777', '#64748B'];

const progressWidth = (value: number) => `${Math.min(Math.max(value, 0), 100)}%`;

const percentOf = (value: number, total: number) => total > 0 ? Math.round((value / total) * 1000) / 10 : 0;

const recruiterTeamRows = (recruiter: RecruiterTeamCityBreakdown['recruiters'][number]): BreakdownRow[] => (
    recruiter.teams.map((team) => ({
        name: team.name,
        count: team.total_leads_count,
    }))
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
        .sort((left, right) => right.count - left.count || left.name.localeCompare(right.name));
};

const departmentTeamRows = (breakdown: RecruiterTeamCityBreakdown): BreakdownRow[] => {
    const rows = new Map<string, number>();

    breakdown.recruiters.forEach((recruiter) => {
        recruiter.teams.forEach((team) => {
            rows.set(team.name, (rows.get(team.name) || 0) + team.total_leads_count);
        });
    });

    return sortedBreakdownRows(rows);
};

const departmentCityRows = (breakdown: RecruiterTeamCityBreakdown): BreakdownRow[] => {
    const rows = new Map<string, number>();

    breakdown.recruiters.forEach((recruiter) => {
        recruiter.teams.forEach((team) => {
            team.cities.forEach((city) => {
                rows.set(city.name, (rows.get(city.name) || 0) + city.leads_count);
            });
        });
    });

    return sortedBreakdownRows(rows);
};

const departmentSourceChartRows = (breakdown: RecruiterTeamCityBreakdown): BreakdownRow[] => {
    const rows = new Map<string, number>();

    breakdown.recruiters.forEach((recruiter) => {
        recruiter.teams.forEach((team) => {
            team.cities.forEach((city) => {
                breakdown.source_columns.forEach((source) => {
                    rows.set(source, (rows.get(source) || 0) + (city.sources[source] || 0));
                });
            });
        });
    });

    return sortedBreakdownRows(rows);
};

const departmentSourceRows = (breakdown: RecruiterTeamCityBreakdown): SourceBreakdownRow[] => {
    const rows = new Map<string, SourceBreakdownRow>();

    breakdown.recruiters.forEach((recruiter) => {
        recruiter.teams.forEach((team) => {
            team.cities.forEach((city) => {
                const key = `${team.name}|||${city.name}`;
                const row = rows.get(key) || {
                    team: team.name,
                    city: city.name,
                    count: 0,
                    sources: {},
                };

                row.count += city.leads_count;
                breakdown.source_columns.forEach((source) => {
                    row.sources[source] = (row.sources[source] || 0) + (city.sources[source] || 0);
                });
                rows.set(key, row);
            });
        });
    });

    return Array.from(rows.values())
        .sort((left, right) => left.team.localeCompare(right.team) || right.count - left.count || left.city.localeCompare(right.city));
};

const sortedBreakdownRows = (rows: Map<string, number>): BreakdownRow[] => (
    Array.from(rows.entries())
        .map(([name, count]) => ({ name, count }))
        .sort((left, right) => right.count - left.count || left.name.localeCompare(right.name))
);

export default function TaskOverdueDashboard({ account, period, groups, recruiterLeads, recruiterTeamCityBreakdown, links }: Props) {
    const debugIframe = useMemo(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return new URLSearchParams(window.location.search).get('debug_iframe') === '1';
    }, []);
    const preservedIframeParams = useMemo(() => {
        if (typeof window === 'undefined') {
            return [] as Array<[string, string]>;
        }

        const params = new URLSearchParams(window.location.search);
        const keys = ['currency', 'date_from', 'date_to', 'lang', 'period', 't'];

        return keys.flatMap((key) => params.getAll(key).map((value) => [key, value] as [string, string]));
    }, []);
    const [iframeMessages, setIframeMessages] = useState<MessageLog[]>([]);
    const totalCompleted = groups.reduce((sum, group) => sum + group.completed_count, 0);
    const totalOverdue = groups.reduce((sum, group) => sum + group.completed_overdue_count, 0);
    const totalRate = totalCompleted > 0 ? Math.round((totalOverdue / totalCompleted) * 1000) / 10 : 0;
    const totalUsers = groups.reduce((sum, group) => sum + group.users.length, 0);
    const periodLabel = `${period.label}: ${period.from} - ${period.to}`;
    const periodSourceLabel = period.source === 'amo_period' || period.source === 'amo_dates'
        ? 'Период с рабочего стола amoCRM'
        : 'Период выбран вручную';

    useEffect(() => {
        if (!debugIframe || typeof window === 'undefined') {
            return;
        }

        const listener = (event: MessageEvent) => {
            setIframeMessages((messages) => [
                {
                    received_at: new Date().toISOString(),
                    origin: event.origin || 'unknown',
                    data: event.data,
                },
                ...messages,
            ].slice(0, 10));
        };

        window.addEventListener('message', listener);

        return () => window.removeEventListener('message', listener);
    }, [debugIframe]);

    return (
        <div className="min-h-screen bg-gray-50 px-3 py-4 text-gray-900 sm:px-4">
            <div className="mx-auto max-w-7xl space-y-4">
                <header className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                    <div className="grid gap-4 border-b border-gray-100 px-5 py-5 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div className="flex min-w-0 items-start gap-4">
                            <img className="size-14 shrink-0 rounded-xl border border-gray-200 bg-gray-950 object-contain p-1.5" src="/assets/anyservice-logo.png" alt="AnyService" />
                            <div className="min-w-0">
                                <div className="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-medium text-brand-600">
                                    BI аналитика CRM
                                </div>
                                <h1 className="mt-2 text-2xl font-semibold text-gray-900">Отчеты рабочего стола</h1>
                                <div className="mt-2 flex flex-wrap items-center gap-2 text-theme-sm text-gray-500">
                                    <span>{account.name}</span>
                                    <span className="size-1 rounded-full bg-gray-300" />
                                    <span>{account.base_domain}</span>
                                    <span className="size-1 rounded-full bg-gray-300" />
                                    <span>{periodLabel}</span>
                                </div>
                            </div>
                        </div>

                        <form className="rounded-2xl border border-gray-200 bg-gray-50 p-3" method="get" action={links.self}>
                            {debugIframe ? <input type="hidden" name="debug_iframe" value="1" /> : null}
                            {preservedIframeParams.map(([key, value], index) => (
                                <input key={`${key}-${index}`} type="hidden" name={key} value={value} />
                            ))}
                            <div className="mb-2 flex items-center gap-2 text-theme-xs font-semibold uppercase text-gray-500">
                                <CalendarDays className="size-4 text-brand-500" />
                                Период
                            </div>
                            <div className="mb-2 text-theme-xs text-gray-500">{periodSourceLabel}</div>
                            <div className="grid grid-cols-[1fr_1fr_auto] gap-2 text-theme-sm">
                                <input className="h-10 rounded-lg border-gray-200 bg-white px-3 text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" name="from" type="date" defaultValue={period.from} />
                                <input className="h-10 rounded-lg border-gray-200 bg-white px-3 text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" name="to" type="date" defaultValue={period.to} />
                                <button className="h-10 rounded-lg bg-brand-500 px-4 font-medium text-white shadow-theme-xs hover:bg-brand-600" type="submit">Показать</button>
                            </div>
                        </form>
                    </div>
                </header>

                {debugIframe ? <DebugPanel iframeMessages={iframeMessages} /> : null}

                <ReportSection
                    eyebrow="Отчет по сделкам"
                    title={`Поле “${recruiterLeads.field_name}”`}
                    description={`Воронка: ${recruiterLeads.pipeline_name || (recruiterLeads.pipeline_id ? `ID ${recruiterLeads.pipeline_id}` : 'все воронки')}. Учитываются все этапы, включая успешные и закрытые нереализованные.`}
                    aside={(
                        <AccentSummary
                            label="Сделок с рекрутером"
                            value={recruiterLeads.assigned_leads_count}
                            note={`Передано менеджеру: ${recruiterLeads.transferred_to_manager_count}`}
                            tone="brand"
                        />
                    )}
                >
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-theme-sm">
                            <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                                <tr>
                                    <th className="px-5 py-3">Рекрутер из списка</th>
                                    <th className="px-4 py-3">Сделок</th>
                                    <th className="px-4 py-3">Передано менеджеру</th>
                                    <th className="px-4 py-3">Доля</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {recruiterLeads.recruiters.length > 0 ? recruiterLeads.recruiters.map((recruiter) => {
                                    const rate = recruiterLeads.assigned_leads_count > 0
                                        ? Math.round((recruiter.leads_count / recruiterLeads.assigned_leads_count) * 1000) / 10
                                        : 0;
                                    const transferRate = recruiter.leads_count > 0
                                        ? Math.round((recruiter.transferred_to_manager_count / recruiter.leads_count) * 1000) / 10
                                        : 0;

                                    return (
                                        <tr className="text-gray-700 hover:bg-brand-50/40" key={recruiter.enum_id}>
                                            <td className="px-5 py-3 font-medium text-gray-900">{recruiter.name}</td>
                                            <td className="px-4 py-3 tabular-nums">{recruiter.leads_count}</td>
                                            <td className="px-4 py-3">
                                                <div className="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-2.5 py-1 text-theme-xs font-medium text-emerald-700">
                                                    <ArrowRightLeft className="size-3.5" />
                                                    {recruiter.transferred_to_manager_count} · {transferRate}%
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Progress value={rate} tone="brand" />
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td className="px-5 py-6 text-gray-500" colSpan={4}>
                                            {recruiterLeads.field_found
                                                ? 'В поле “Рекрутер” пока нет значений списка или нет сделок с заполненным рекрутером за выбранный период.'
                                                : 'Поле сделки “Рекрутер” не найдено в CRM-аудите. Запустите синхронизацию структуры CRM.'}
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
                    description={`Сделки с заполненными полями “Рекрутер” и “Менеджер”, сгруппированные по полю “${recruiterTeamCityBreakdown.team_field_name}”.`}
                    aside={(
                        <AccentSummary
                            label="Передано менеджерам"
                            value={recruiterTeamCityBreakdown.total_leads_count}
                            note="по всему отделу"
                            tone="warning"
                        />
                    )}
                >
                    <BreakdownReportContent rows={departmentTeamRows(recruiterTeamCityBreakdown)} />
                </ReportSection>

                <ReportSection
                    eyebrow="Весь отдел рекрутинга"
                    title="Всего передано менеджерам по городам"
                    description={`Сделки с заполненными полями “Рекрутер” и “Менеджер”, сгруппированные по полю “${recruiterTeamCityBreakdown.city_field_name}”.`}
                    aside={(
                        <AccentSummary
                            label="Передано менеджерам"
                            value={recruiterTeamCityBreakdown.total_leads_count}
                            note="по всему отделу"
                            tone="warning"
                        />
                    )}
                >
                    <BreakdownReportContent compactLegend rows={departmentCityRows(recruiterTeamCityBreakdown)} />
                </ReportSection>

                <ReportSection
                    eyebrow="Весь отдел рекрутинга"
                    title="Всего передано менеджерам по источникам"
                    description={`Сделки с заполненными полями “Рекрутер” и “Менеджер”, сгруппированные по полю “${recruiterTeamCityBreakdown.source_field_name}”.`}
                    aside={(
                        <AccentSummary
                            label="Передано менеджерам"
                            value={recruiterTeamCityBreakdown.total_leads_count}
                            note="по всему отделу"
                            tone="warning"
                        />
                    )}
                >
                    <BreakdownReportContent compactLegend rows={departmentSourceChartRows(recruiterTeamCityBreakdown)} />
                </ReportSection>

                <ReportSection
                    eyebrow="Весь отдел рекрутинга"
                    title="Общая таблица по городам и источникам"
                    description={`Разрез по командам, городам и источникам. Источник: ${recruiterTeamCityBreakdown.source_field_found ? recruiterTeamCityBreakdown.source_field_name : 'поле не найдено'}.`}
                    aside={(
                        <AccentSummary
                            label="Сделок в таблице"
                            value={recruiterTeamCityBreakdown.total_leads_count}
                            note="по всему отделу"
                            tone="warning"
                        />
                    )}
                >
                    <SourceBreakdownTable
                        sourceColumns={recruiterTeamCityBreakdown.source_columns}
                        rows={departmentSourceRows(recruiterTeamCityBreakdown)}
                    />
                </ReportSection>

                <ReportSection
                    eyebrow="Передачи рекрутеров"
                    title="Подробно по каждому рекрутеру"
                    description="Сделки с заполненными полями “Рекрутер” и “Менеджер”, сгруппированные по команде, городу и источнику."
                    aside={(
                        <AccentSummary
                            label="Сделок в разрезе"
                            value={recruiterTeamCityBreakdown.total_leads_count}
                            note={`Источник: ${recruiterTeamCityBreakdown.source_field_found ? recruiterTeamCityBreakdown.source_field_name : 'поле не найдено'}`}
                            tone="warning"
                        />
                    )}
                >
                    <div className="grid gap-4 p-5">
                        {recruiterTeamCityBreakdown.recruiters.length > 0 ? recruiterTeamCityBreakdown.recruiters.map((recruiter) => (
                            <article className="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50" key={recruiter.enum_id}>
                                <div className="flex items-start justify-between gap-3 border-b border-gray-200 bg-white px-5 py-4">
                                    <h3 className="text-base font-semibold text-gray-900">{recruiter.name}</h3>
                                    <span className="rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-medium tabular-nums text-brand-600">
                                        {recruiter.total_leads_count}
                                    </span>
                                </div>

                                <div className="grid gap-4 border-b border-gray-200 p-4">
                                    <BreakdownCard
                                        title="Передано менеджерам по командам"
                                        description={`Поле “${recruiterTeamCityBreakdown.team_field_name}”`}
                                        rows={recruiterTeamRows(recruiter)}
                                    />
                                    <BreakdownCard
                                        compactLegend
                                        title="Всего по городам"
                                        description={`Поле “${recruiterTeamCityBreakdown.city_field_name}”`}
                                        rows={recruiterCityRows(recruiter)}
                                    />
                                </div>

                                <div className="overflow-x-auto">
                                    <div className="border-b border-gray-200 bg-white px-5 py-3 text-theme-xs font-semibold uppercase text-gray-500">
                                        Детализация по городам и источникам
                                    </div>
                                    <table className="w-full min-w-[760px] text-left text-theme-sm">
                                        <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                                            <tr>
                                                <th className="px-5 py-3">Команда</th>
                                                <th className="px-4 py-3">Город</th>
                                                <th className="px-4 py-3 text-right">Всего</th>
                                                {recruiterTeamCityBreakdown.source_columns.map((source) => (
                                                    <th className="px-4 py-3 text-right" key={source}>{source}</th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100 bg-white">
                                            {recruiter.teams.flatMap((team) => team.cities.map((city, index) => (
                                                <tr className="text-gray-700 hover:bg-brand-50/40" key={`${team.name}-${city.name}`}>
                                                    {index === 0 ? (
                                                        <td className="px-5 py-3 align-top font-semibold text-gray-900" rowSpan={team.cities.length}>
                                                            {team.name}
                                                            <div className="mt-1 text-theme-xs font-normal tabular-nums text-gray-500">{team.total_leads_count}</div>
                                                        </td>
                                                    ) : null}
                                                    <td className="px-4 py-3 font-medium text-gray-900">{city.name}</td>
                                                    <td className="px-4 py-3 text-right font-semibold tabular-nums text-gray-900">{city.leads_count}</td>
                                                    {recruiterTeamCityBreakdown.source_columns.map((source) => {
                                                        const count = city.sources[source] || 0;

                                                        return (
                                                            <td className={count > 0 ? 'px-4 py-3 text-right font-medium tabular-nums text-brand-600' : 'px-4 py-3 text-right tabular-nums text-gray-400'} key={source}>
                                                                {count}
                                                            </td>
                                                        );
                                                    })}
                                                </tr>
                                            )))}
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        )) : (
                            <EmptyState>
                                Нет данных для отчета. Проверьте, что выбраны поля “Команда” и “Город”, а в сделках заполнены рекрутер и менеджер.
                            </EmptyState>
                        )}
                    </div>
                </ReportSection>

                <ReportSection
                    eyebrow="Отчет по задачам"
                    title="Выполненные просроченные задачи"
                    description="Показывает задачи, которые были завершены после наступления крайнего срока выполнения."
                >
                    <div className="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4">
                        <MetricCard label="Выполнено за период" value={totalCompleted} icon={<CheckCircle2 className="size-5" />} tone="success" progress={100} />
                        <MetricCard label="Выполнено с просрочкой" value={totalOverdue} icon={<AlertTriangle className="size-5" />} tone="warning" progress={totalRate} />
                        <MetricCard label="Процент просрочки" value={`${totalRate}%`} icon={<Percent className="size-5" />} tone={totalRate > 20 ? 'danger' : 'neutral'} progress={totalRate} />
                        <MetricCard label="Пользователей в отчете" value={totalUsers} icon={<Users className="size-5" />} tone="brand" note={`групп: ${groups.length}`} />
                    </div>

                    <div className="space-y-4 p-5 pt-0">
                        {groups.length > 0 ? groups.map((group) => (
                            <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white" key={group.group_id || group.group_name}>
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-5 py-4">
                                    <h3 className="flex items-center gap-2 text-base font-semibold text-gray-900">
                                        <span className="size-2.5 rounded-full bg-brand-500" />
                                        {group.group_name}
                                    </h3>
                                    <div className="text-theme-sm text-gray-500">
                                        выполнено {group.completed_count} · просрочено {group.completed_overdue_count}
                                    </div>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-theme-sm">
                                        <thead className="bg-white text-theme-xs font-semibold uppercase text-gray-500">
                                            <tr>
                                                <th className="px-5 py-3">Пользователь</th>
                                                <th className="px-4 py-3">Выполнено</th>
                                                <th className="px-4 py-3">Просрочено</th>
                                                <th className="px-4 py-3">Доля</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {group.users.map((user) => (
                                                <tr className="text-gray-700 hover:bg-brand-50/40" key={user.id}>
                                                    <td className="px-5 py-3 font-medium text-gray-900">{user.name}</td>
                                                    <td className="px-4 py-3 tabular-nums">{user.completed_count}</td>
                                                    <td className="px-4 py-3 tabular-nums">
                                                        <span className={user.completed_overdue_count > 0 ? 'font-semibold text-amber-700' : ''}>
                                                            {user.completed_overdue_count}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Progress value={user.overdue_rate} tone={user.overdue_rate > 20 ? 'danger' : 'warning'} />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        )) : (
                            <EmptyState>Нет данных за выбранный период. Запустите синхронизацию статистики задач в сервисе.</EmptyState>
                        )}
                    </div>
                </ReportSection>
            </div>
        </div>
    );
}

function SourceBreakdownTable({ rows, sourceColumns }: { rows: SourceBreakdownRow[]; sourceColumns: string[] }) {
    return (
        <div className="overflow-x-auto p-5">
            <table className="w-full min-w-[760px] text-left text-theme-sm">
                <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th className="px-5 py-3">Команда</th>
                        <th className="px-4 py-3">Город</th>
                        <th className="px-4 py-3 text-right">Всего</th>
                        {sourceColumns.map((source) => (
                            <th className="px-4 py-3 text-right" key={source}>{source}</th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 bg-white">
                    {rows.map((row) => (
                        <tr className="text-gray-700 hover:bg-brand-50/40" key={`${row.team}-${row.city}`}>
                            <td className="px-5 py-3 align-top font-semibold text-gray-900">{row.team}</td>
                            <td className="px-4 py-3 font-medium text-gray-900">{row.city}</td>
                            <td className="px-4 py-3 text-right font-semibold tabular-nums text-gray-900">{row.count}</td>
                            {sourceColumns.map((source) => {
                                const count = row.sources[source] || 0;

                                return (
                                    <td className={count > 0 ? 'px-4 py-3 text-right font-medium tabular-nums text-brand-600' : 'px-4 py-3 text-right tabular-nums text-gray-400'} key={source}>
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
            <PieChart compactLegend={compactLegend} rows={rows} total={rows.reduce((sum, row) => sum + row.count, 0)} />
        </div>
    );
}

function BreakdownCard({ title, description, rows, compactLegend = false }: { title: string; description: string; rows: BreakdownRow[]; compactLegend?: boolean }) {
    const total = rows.reduce((sum, row) => sum + row.count, 0);

    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h4 className="text-theme-sm font-semibold text-gray-900">{title}</h4>
                    <div className="mt-1 text-theme-xs text-gray-500">{description}</div>
                </div>
                <div className="rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-semibold tabular-nums text-gray-700">
                    {total}
                </div>
            </div>

            {rows.length > 0 ? (
                <div className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px] xl:items-start">
                    <BreakdownTable rows={rows} />
                    <PieChart compactLegend={compactLegend} rows={rows} total={total} />
                </div>
            ) : (
                <div className="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 text-theme-sm text-gray-500">
                    Нет данных для разреза.
                </div>
            )}
        </div>
    );
}

function BreakdownTable({ rows }: { rows: BreakdownRow[] }) {
    const total = rows.reduce((sum, row) => sum + row.count, 0);

    if (rows.length === 0) {
        return (
            <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 text-theme-sm text-gray-500">
                Нет данных для разреза.
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border border-gray-200">
            <table className="w-full text-left text-theme-sm">
                <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th className="px-4 py-3">Значение</th>
                        <th className="px-4 py-3 text-right">Сделок</th>
                        <th className="px-4 py-3 text-right">Доля</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {rows.map((row, index) => (
                        <tr className="hover:bg-brand-50/40" key={row.name}>
                            <td className="px-4 py-3">
                                <span className="mr-2 inline-block size-2.5 rounded-full" style={{ backgroundColor: chartColors[index % chartColors.length] }} />
                                <span className="font-medium text-gray-900">{row.name}</span>
                            </td>
                            <td className="px-4 py-3 text-right font-semibold tabular-nums text-gray-900">{row.count}</td>
                            <td className="px-4 py-3 text-right tabular-nums text-gray-600">{percentOf(row.count, total)}%</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function PieChart({ rows, total, compactLegend = false }: { rows: BreakdownRow[]; total: number; compactLegend?: boolean }) {
    let cumulative = 0;
    const radius = 15.91549430918954;
    const slices = rows.map((row, index) => {
        const value = total > 0 ? (row.count / total) * 100 : 0;
        const slice = {
            ...row,
            color: chartColors[index % chartColors.length],
            dasharray: `${value} ${100 - value}`,
            dashoffset: 25 - cumulative,
        };
        cumulative += value;

        return slice;
    });

    return (
        <div className="rounded-xl border border-gray-200 bg-gray-50 p-4">
            <div className="flex justify-center">
                <svg className="size-36 -rotate-90" viewBox="0 0 36 36" role="img" aria-label="Круговая диаграмма">
                    <circle cx="18" cy="18" r={radius} fill="transparent" stroke="#E5E7EB" strokeWidth="4" />
                    {slices.map((slice) => (
                        <circle
                            cx="18"
                            cy="18"
                            fill="transparent"
                            key={slice.name}
                            r={radius}
                            stroke={slice.color}
                            strokeDasharray={slice.dasharray}
                            strokeDashoffset={slice.dashoffset}
                            strokeLinecap="butt"
                            strokeWidth="4"
                        />
                    ))}
                </svg>
            </div>
            <div className={compactLegend ? 'mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-1' : 'mt-3 space-y-2'}>
                {slices.map((slice) => (
                    <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-lg bg-white px-2.5 py-2 text-theme-xs shadow-theme-xs" key={slice.name}>
                        <div className="flex min-w-0 items-center gap-2">
                            <span className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: slice.color }} />
                            <span className="truncate text-gray-700">{slice.name}</span>
                        </div>
                        <span className="font-semibold tabular-nums text-gray-900">{slice.count}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function DebugPanel({ iframeMessages }: { iframeMessages: MessageLog[] }) {
    return (
        <ReportSection
            eyebrow="Iframe diagnostics"
            title="Диагностика параметров amoCRM"
            description="Этот блок виден только при debug_iframe=1 и помогает проверить, передает ли рабочий стол amoCRM период в URL или через postMessage."
        >
            <div className="grid gap-4 p-5 lg:grid-cols-2">
                <div className="rounded-2xl border border-sky-100 bg-sky-50 p-4">
                    <div className="text-theme-xs font-semibold uppercase text-sky-700">Location</div>
                    <dl className="mt-3 grid gap-2">
                        <DebugValue label="href" value={typeof window !== 'undefined' ? window.location.href : '-'} />
                        <DebugValue label="search" value={typeof window !== 'undefined' ? window.location.search || '-' : '-'} />
                        <DebugValue label="document.referrer" value={typeof document !== 'undefined' ? document.referrer || '-' : '-'} />
                    </dl>
                </div>
                <div className="rounded-2xl border border-gray-200 bg-white p-4">
                    <div className="flex items-center gap-2 text-theme-xs font-semibold uppercase text-gray-500">
                        <Database className="size-4 text-brand-500" />
                        postMessage events
                    </div>
                    <div className="mt-3 max-h-64 overflow-auto rounded-xl bg-gray-950 p-3 font-mono text-theme-xs text-gray-100">
                        {iframeMessages.length > 0 ? iframeMessages.map((message, index) => (
                            <pre className="mb-3 whitespace-pre-wrap break-words last:mb-0" key={`${message.received_at}-${index}`}>
                                {JSON.stringify(message, null, 2)}
                            </pre>
                        )) : (
                            <div className="text-gray-400">Сообщений пока нет. Измените период на рабочем столе amoCRM и посмотрите, появятся ли события.</div>
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
            <dt className="text-theme-xs text-gray-500">{label}</dt>
            <dd className="break-all font-mono text-theme-xs text-gray-900">{value}</dd>
        </div>
    );
}

function ReportSection({ eyebrow, title, description, aside, children }: { eyebrow: string; title: string; description?: string; aside?: ReactNode; children: ReactNode }) {
    return (
        <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
            <div className="grid gap-4 border-b border-gray-200 bg-gray-50 px-5 py-4 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <div className="text-theme-xs font-semibold uppercase text-brand-600">{eyebrow}</div>
                    <h2 className="mt-1 text-lg font-semibold text-gray-900">{title}</h2>
                    {description ? <p className="mt-1 text-theme-sm text-gray-500">{description}</p> : null}
                </div>
                {aside}
            </div>
            {children}
        </section>
    );
}

function AccentSummary({ label, value, note, tone }: { label: string; value: number; note: string; tone: 'brand' | 'warning' }) {
    const classes = tone === 'warning'
        ? 'border-amber-200 bg-amber-50 text-amber-700'
        : 'border-brand-200 bg-brand-50 text-brand-700';

    return (
        <div className={`rounded-2xl border px-4 py-3 text-right ${classes}`}>
            <div className="text-theme-xs font-medium uppercase">{label}</div>
            <div className="mt-1 text-3xl font-semibold text-gray-900">{value}</div>
            <div className="mt-1 text-theme-xs">{note}</div>
        </div>
    );
}

function MetricCard({ label, value, icon, tone, progress, note }: { label: string; value: ReactNode; icon: ReactNode; tone: 'brand' | 'success' | 'warning' | 'danger' | 'neutral'; progress?: number; note?: string }) {
    const toneClass = {
        brand: 'bg-brand-50 text-brand-600',
        success: 'bg-emerald-50 text-emerald-600',
        warning: 'bg-amber-50 text-amber-600',
        danger: 'bg-red-50 text-red-600',
        neutral: 'bg-gray-100 text-gray-600',
    }[tone];
    const barClass = {
        brand: 'bg-brand-500',
        success: 'bg-emerald-500',
        warning: 'bg-amber-500',
        danger: 'bg-red-500',
        neutral: 'bg-gray-600',
    }[tone];

    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs">
            <div className={`flex size-11 items-center justify-center rounded-xl ${toneClass}`}>
                {icon}
            </div>
            <div className="mt-4 flex items-end justify-between gap-3">
                <div>
                    <div className="text-theme-sm text-gray-500">{label}</div>
                    <div className="mt-2 text-3xl font-semibold text-gray-900">{value}</div>
                </div>
                {note ? <div className="text-theme-xs text-gray-500">{note}</div> : null}
            </div>
            {progress !== undefined ? (
                <div className="mt-4 h-1.5 rounded-full bg-gray-100">
                    <div className={`h-1.5 rounded-full ${barClass}`} style={{ width: progressWidth(progress) }} />
                </div>
            ) : null}
        </div>
    );
}

function Progress({ value, tone }: { value: number; tone: 'brand' | 'warning' | 'danger' }) {
    const barClass = {
        brand: 'bg-brand-500',
        warning: 'bg-amber-500',
        danger: 'bg-red-500',
    }[tone];

    return (
        <div className="flex min-w-40 items-center gap-2">
            <div className="h-2 flex-1 rounded-full bg-gray-100">
                <div className={`h-2 rounded-full ${barClass}`} style={{ width: progressWidth(value) }} />
            </div>
            <span className="w-12 text-right tabular-nums text-gray-600">{value}%</span>
        </div>
    );
}

function EmptyState({ children }: { children: ReactNode }) {
    return (
        <div className="rounded-2xl border border-gray-200 bg-gray-50 p-6 text-theme-sm text-gray-500">
            {children}
        </div>
    );
}
