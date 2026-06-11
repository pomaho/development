import { AlertTriangle, BarChart3, CalendarDays, CheckCircle2, Percent, Users } from 'lucide-react';

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
};

type RecruiterLeads = {
    field_name: string;
    field_id: number | null;
    field_found: boolean;
    pipeline_id: number | null;
    pipeline_name: string | null;
    total_leads_count: number;
    assigned_leads_count: number;
    recruiters: RecruiterLeadRow[];
};

type Props = {
    account: Account;
    period: {
        from: string;
        to: string;
    };
    groups: GroupRow[];
    recruiterLeads: RecruiterLeads;
    links: {
        self: string;
        api: string;
    };
};

export default function TaskOverdueDashboard({ account, period, groups, recruiterLeads, links }: Props) {
    const totalCompleted = groups.reduce((sum, group) => sum + group.completed_count, 0);
    const totalOverdue = groups.reduce((sum, group) => sum + group.completed_overdue_count, 0);
    const totalRate = totalCompleted > 0 ? Math.round((totalOverdue / totalCompleted) * 1000) / 10 : 0;
    const totalUsers = groups.reduce((sum, group) => sum + group.users.length, 0);
    const periodLabel = `${period.from} - ${period.to}`;

    return (
        <div className="min-h-screen bg-slate-100 px-4 py-4 text-slate-900">
            <div className="mx-auto max-w-7xl">
                <header className="mb-4 rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div className="grid gap-4 border-b border-slate-200 p-4 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div className="flex min-w-0 items-start gap-4">
                            <img className="h-14 w-14 shrink-0 rounded-md border border-amber-100 bg-slate-950 object-contain p-1.5" src="/assets/anyservice-logo.png" alt="AnyService" />
                            <div>
                                <div className="text-xs font-semibold uppercase tracking-wide text-amber-600">BI аналитика CRM</div>
                                <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">BI-отчеты рабочего стола</h1>
                                <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
                                    <span>{account.name}</span>
                                    <span className="hidden h-1 w-1 rounded-full bg-slate-300 sm:inline-block" />
                                    <span>{account.base_domain}</span>
                                    <span className="hidden h-1 w-1 rounded-full bg-slate-300 sm:inline-block" />
                                    <span>{periodLabel}</span>
                                </div>
                            </div>
                        </div>

                        <form className="rounded-md border border-slate-200 bg-slate-50 p-3" method="get" action={links.self}>
                            <div className="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <CalendarDays className="h-4 w-4 text-amber-500" />
                                Период
                            </div>
                            <div className="grid grid-cols-[1fr_1fr_auto] gap-2 text-sm">
                                <input className="h-9 rounded-md border-slate-300 bg-white text-slate-900 focus:border-amber-500 focus:ring-amber-500" name="from" type="date" defaultValue={period.from} />
                                <input className="h-9 rounded-md border-slate-300 bg-white text-slate-900 focus:border-amber-500 focus:ring-amber-500" name="to" type="date" defaultValue={period.to} />
                                <button className="h-9 rounded-md bg-slate-950 px-4 font-semibold text-white hover:bg-slate-800" type="submit">Показать</button>
                            </div>
                        </form>
                    </div>
                </header>

                <section className="mb-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="mb-4">
                        <div className="text-xs font-semibold uppercase tracking-wide text-amber-600">Отчет по задачам</div>
                        <h2 className="mt-1 text-lg font-semibold text-slate-950">Выполненные просроченные задачи</h2>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div className="flex items-center justify-between gap-3 text-sm font-medium text-slate-500">
                                Выполнено за период
                                <CheckCircle2 className="h-5 w-5 text-emerald-600" />
                            </div>
                            <div className="mt-2 text-3xl font-semibold text-slate-950">{totalCompleted}</div>
                            <div className="mt-3 h-1.5 rounded-full bg-slate-100">
                                <div className="h-1.5 w-full rounded-full bg-emerald-500" />
                            </div>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div className="flex items-center justify-between gap-3 text-sm font-medium text-slate-500">
                                Выполнено с просрочкой
                                <AlertTriangle className="h-5 w-5 text-amber-600" />
                            </div>
                            <div className="mt-2 text-3xl font-semibold text-slate-950">{totalOverdue}</div>
                            <div className="mt-3 h-1.5 rounded-full bg-slate-100">
                                <div className="h-1.5 rounded-full bg-amber-500" style={{ width: `${Math.min(totalRate, 100)}%` }} />
                            </div>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div className="flex items-center justify-between gap-3 text-sm font-medium text-slate-500">
                                Процент просрочки
                                <Percent className="h-5 w-5 text-slate-500" />
                            </div>
                            <div className="mt-2 text-3xl font-semibold text-slate-950">{totalRate}%</div>
                            <div className="mt-3 h-1.5 rounded-full bg-slate-100">
                                <div className="h-1.5 rounded-full bg-slate-700" style={{ width: `${Math.min(totalRate, 100)}%` }} />
                            </div>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div className="flex items-center justify-between gap-3 text-sm font-medium text-slate-500">
                                Пользователей в отчете
                                <Users className="h-5 w-5 text-amber-600" />
                            </div>
                            <div className="mt-2 text-3xl font-semibold text-slate-950">{totalUsers}</div>
                            <div className="mt-3 flex items-center gap-2 text-xs text-slate-500">
                                <BarChart3 className="h-4 w-4 text-amber-500" />
                                групп: {groups.length}
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 space-y-4">
                        {groups.length > 0 ? groups.map((group) => (
                            <section className="overflow-hidden rounded-lg border border-slate-200 bg-white" key={group.group_id || group.group_name}>
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
                                    <h3 className="flex items-center gap-2 text-base font-semibold text-slate-950">
                                        <span className="h-2.5 w-2.5 rounded-full bg-amber-500" />
                                        {group.group_name}
                                    </h3>
                                    <div className="text-sm text-slate-500">
                                        выполнено {group.completed_count} · просрочено {group.completed_overdue_count}
                                    </div>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-white text-xs uppercase tracking-wide text-slate-500">
                                            <tr>
                                                <th className="px-4 py-3 font-semibold">Пользователь</th>
                                                <th className="px-3 py-3 font-semibold">Выполнено</th>
                                                <th className="px-3 py-3 font-semibold">Просрочено</th>
                                                <th className="px-3 py-3 font-semibold">Доля</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {group.users.map((user) => (
                                                <tr className="border-t border-slate-100 text-slate-700 hover:bg-amber-50/60" key={user.id}>
                                                    <td className="px-4 py-3 font-medium text-slate-950">{user.name}</td>
                                                    <td className="px-3 py-3 tabular-nums">{user.completed_count}</td>
                                                    <td className="px-3 py-3 tabular-nums">
                                                        <span className={user.completed_overdue_count > 0 ? 'font-semibold text-amber-700' : ''}>
                                                            {user.completed_overdue_count}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        <div className="flex min-w-36 items-center gap-2">
                                                            <div className="h-2 flex-1 rounded-full bg-slate-100">
                                                                <div className="h-2 rounded-full bg-amber-500" style={{ width: `${Math.min(user.overdue_rate, 100)}%` }} />
                                                            </div>
                                                            <span className="w-12 text-right tabular-nums text-slate-600">{user.overdue_rate}%</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        )) : (
                            <section className="rounded-lg border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                                Нет данных за выбранный период. Запустите синхронизацию статистики задач в сервисе.
                            </section>
                        )}
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div className="grid gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <div className="text-xs font-semibold uppercase tracking-wide text-amber-600">Отчет по сделкам</div>
                            <h2 className="mt-1 text-lg font-semibold text-slate-950">Поле “{recruiterLeads.field_name}”</h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Количество сделок, в которых выбрано каждое значение списка.
                                Воронка: {recruiterLeads.pipeline_name || (recruiterLeads.pipeline_id ? `ID ${recruiterLeads.pipeline_id}` : 'все воронки')}.
                                Учитываются все этапы, включая успешные и закрытые нереализованные.
                            </p>
                        </div>
                        <div className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-right">
                            <div className="text-xs font-medium uppercase tracking-wide text-amber-700">Сделок с рекрутером</div>
                            <div className="mt-1 text-3xl font-semibold text-slate-950">{recruiterLeads.assigned_leads_count}</div>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-white text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">Рекрутер из списка</th>
                                    <th className="px-3 py-3 font-semibold">Сделок</th>
                                    <th className="px-3 py-3 font-semibold">Доля</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recruiterLeads.recruiters.length > 0 ? recruiterLeads.recruiters.map((recruiter) => {
                                    const rate = recruiterLeads.assigned_leads_count > 0
                                        ? Math.round((recruiter.leads_count / recruiterLeads.assigned_leads_count) * 1000) / 10
                                        : 0;

                                    return (
                                        <tr className="border-t border-slate-100 text-slate-700 hover:bg-amber-50/60" key={recruiter.enum_id}>
                                            <td className="px-4 py-3 font-medium text-slate-950">{recruiter.name}</td>
                                            <td className="px-3 py-3 tabular-nums">{recruiter.leads_count}</td>
                                            <td className="px-3 py-3">
                                                <div className="flex min-w-40 items-center gap-2">
                                                    <div className="h-2 flex-1 rounded-full bg-slate-100">
                                                        <div className="h-2 rounded-full bg-amber-500" style={{ width: `${Math.min(rate, 100)}%` }} />
                                                    </div>
                                                    <span className="w-12 text-right tabular-nums text-slate-600">{rate}%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                }) : (
                                    <tr>
                                        <td className="px-4 py-5 text-sm text-slate-500" colSpan={3}>
                                            {recruiterLeads.field_found
                                                ? 'В поле “Рекрутер” пока нет значений списка или нет сделок с заполненным рекрутером за выбранный период.'
                                                : 'Поле сделки “Рекрутер” не найдено в CRM-аудите. Запустите синхронизацию структуры CRM.'}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    );
}
