import { AlertTriangle, CalendarDays, CheckCircle2, Percent, TimerReset } from 'lucide-react';

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

type Props = {
    account: Account;
    period: {
        from: string;
        to: string;
    };
    groups: GroupRow[];
    links: {
        self: string;
        api: string;
    };
};

export default function TaskOverdueDashboard({ account, period, groups, links }: Props) {
    const totalCompleted = groups.reduce((sum, group) => sum + group.completed_count, 0);
    const totalOverdue = groups.reduce((sum, group) => sum + group.completed_overdue_count, 0);
    const totalRate = totalCompleted > 0 ? Math.round((totalOverdue / totalCompleted) * 1000) / 10 : 0;
    const periodLabel = `${period.from} - ${period.to}`;

    return (
        <div className="relative min-h-screen overflow-hidden bg-[#050505] px-4 py-5 text-white">
            <div className="pointer-events-none absolute inset-0 opacity-80">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_78%_18%,rgba(255,161,0,0.24),transparent_30%),linear-gradient(135deg,rgba(255,169,0,0.10),transparent_38%),linear-gradient(180deg,#050505_0%,#11100c_100%)]" />
                <div className="absolute right-8 top-6 h-52 w-52 border border-amber-500/30 [clip-path:polygon(50%_0,100%_86%,0_86%)]" />
                <div className="absolute right-24 top-16 h-72 w-72 bg-gradient-to-br from-yellow-300/80 via-amber-500/55 to-orange-600/20 [clip-path:polygon(50%_0,100%_86%,0_86%)]" />
                <div className="absolute right-44 top-28 h-36 w-36 bg-black/70 [clip-path:polygon(50%_0,100%_86%,0_86%)]" />
                <div className="absolute left-5 top-28 h-40 w-px bg-gradient-to-b from-transparent via-amber-400 to-transparent" />
            </div>

            <div className="relative mx-auto max-w-7xl">
                <header className="mb-5 grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                    <div className="max-w-3xl">
                        <div className="mb-4 flex items-center gap-4">
                            <div className="h-14 w-16 bg-gradient-to-br from-yellow-300 via-amber-500 to-orange-700 [clip-path:polygon(50%_0,100%_86%,0_86%)]" />
                            <div>
                                <div className="text-3xl font-semibold leading-none text-white">{account.name}</div>
                                <div className="mt-1 text-sm text-zinc-300">{account.base_domain}</div>
                            </div>
                        </div>
                        <div className="border-l-2 border-amber-400 pl-5">
                            <h1 className="text-4xl font-semibold leading-tight text-white lg:text-5xl">
                                Отчет по выполненным просроченным задачам
                            </h1>
                            <p className="mt-3 max-w-2xl text-base text-zinc-300">
                                Пользователи сгруппированы по отделам. Период отчета: {periodLabel}.
                            </p>
                        </div>
                    </div>

                    <form className="rounded-lg border border-amber-300/45 bg-black/55 p-3 shadow-[0_0_32px_rgba(245,158,11,0.18)] backdrop-blur" method="get" action={links.self}>
                        <div className="mb-2 flex items-center gap-2 text-sm font-medium text-amber-200">
                            <CalendarDays className="h-4 w-4" />
                            Период отчета
                        </div>
                        <div className="grid grid-cols-[1fr_1fr_auto] gap-2 text-sm">
                            <input className="h-10 rounded-md border-amber-300/35 bg-zinc-950/80 text-white focus:border-amber-400 focus:ring-amber-400" name="from" type="date" defaultValue={period.from} />
                            <input className="h-10 rounded-md border-amber-300/35 bg-zinc-950/80 text-white focus:border-amber-400 focus:ring-amber-400" name="to" type="date" defaultValue={period.to} />
                            <button className="h-10 rounded-md bg-amber-500 px-4 font-semibold text-black shadow-[0_0_18px_rgba(245,158,11,0.35)] hover:bg-amber-400" type="submit">Показать</button>
                        </div>
                    </form>
                </header>

                <section className="mb-5 grid gap-3 md:grid-cols-3">
                    <div className="rounded-lg border border-amber-300/35 bg-zinc-950/82 p-4 shadow-[0_0_26px_rgba(245,158,11,0.12)]">
                        <div className="flex items-center justify-between gap-3 text-sm text-zinc-300">
                            Выполнено за период
                            <CheckCircle2 className="h-5 w-5 text-amber-400" />
                        </div>
                        <div className="mt-2 text-4xl font-semibold text-white">{totalCompleted}</div>
                    </div>
                    <div className="rounded-lg border border-amber-300/35 bg-zinc-950/82 p-4 shadow-[0_0_26px_rgba(245,158,11,0.12)]">
                        <div className="flex items-center justify-between gap-3 text-sm text-zinc-300">
                            Выполнено с просрочкой
                            <AlertTriangle className="h-5 w-5 text-amber-400" />
                        </div>
                        <div className="mt-2 text-4xl font-semibold text-amber-300">{totalOverdue}</div>
                    </div>
                    <div className="rounded-lg border border-amber-300/35 bg-zinc-950/82 p-4 shadow-[0_0_26px_rgba(245,158,11,0.12)]">
                        <div className="flex items-center justify-between gap-3 text-sm text-zinc-300">
                            Процент просрочки
                            <Percent className="h-5 w-5 text-amber-400" />
                        </div>
                        <div className="mt-2 text-4xl font-semibold text-white">{totalRate}%</div>
                    </div>
                </section>

                <div className="space-y-4">
                    {groups.length > 0 ? groups.map((group) => (
                        <section className="rounded-lg border border-amber-300/30 bg-black/62 p-4 shadow-[0_0_30px_rgba(245,158,11,0.10)] backdrop-blur" key={group.group_id || group.group_name}>
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <h2 className="flex items-center gap-2 text-lg font-semibold text-white">
                                    <TimerReset className="h-5 w-5 text-amber-400" />
                                    {group.group_name}
                                </h2>
                                <div className="text-sm text-zinc-300">
                                    выполнено {group.completed_count} · просрочено {group.completed_overdue_count}
                                </div>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-y border-amber-300/25 bg-amber-400/8 text-amber-100">
                                        <tr>
                                            <th className="px-3 py-3 font-semibold">Пользователь</th>
                                            <th className="px-3 py-3 font-semibold">Выполнено</th>
                                            <th className="px-3 py-3 font-semibold">Просрочено</th>
                                            <th className="px-3 py-3 font-semibold">%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {group.users.map((user) => (
                                            <tr className="border-b border-white/8 text-zinc-200 hover:bg-amber-400/6" key={user.id}>
                                                <td className="px-3 py-3 font-medium text-white">{user.name}</td>
                                                <td className="px-3 py-3">{user.completed_count}</td>
                                                <td className="px-3 py-3">
                                                    <span className={user.completed_overdue_count > 0 ? 'font-semibold text-amber-300' : ''}>
                                                        {user.completed_overdue_count}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-3">{user.overdue_rate}%</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )) : (
                        <section className="rounded-lg border border-amber-300/30 bg-black/62 p-6 text-sm text-zinc-300 shadow-[0_0_30px_rgba(245,158,11,0.10)]">
                            Нет данных за выбранный период. Запустите синхронизацию статистики задач в сервисе.
                        </section>
                    )}
                </div>
            </div>
        </div>
    );
}
