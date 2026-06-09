type Account = {
    name: string;
    base_domain: string;
};

type Widget = {
    name: string;
    code: string;
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
    widget: Widget;
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

export default function TaskOverdueDashboard({ account, widget, period, groups, links }: Props) {
    const totalCompleted = groups.reduce((sum, group) => sum + group.completed_count, 0);
    const totalOverdue = groups.reduce((sum, group) => sum + group.completed_overdue_count, 0);
    const totalRate = totalCompleted > 0 ? Math.round((totalOverdue / totalCompleted) * 1000) / 10 : 0;

    return (
        <div className="min-h-screen bg-slate-50 px-4 py-4 text-slate-900">
            <div className="mx-auto max-w-6xl">
                <header className="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="text-xs font-medium uppercase tracking-wide text-blue-700">Sonic Expert</div>
                        <h1 className="mt-1 text-xl font-semibold">{widget.name}</h1>
                        <div className="text-sm text-slate-500">{account.name} · {account.base_domain}</div>
                    </div>
                    <form className="grid grid-cols-[1fr_1fr_auto] gap-2 text-sm" method="get" action={links.self}>
                        <input className="rounded border-slate-300" name="from" type="date" defaultValue={period.from} />
                        <input className="rounded border-slate-300" name="to" type="date" defaultValue={period.to} />
                        <button className="rounded bg-blue-700 px-3 py-2 text-white hover:bg-blue-800" type="submit">Показать</button>
                    </form>
                </header>

                <section className="mb-4 grid gap-3 sm:grid-cols-3">
                    <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                        <div className="text-xs text-slate-500">Выполнено за период</div>
                        <div className="mt-1 text-2xl font-semibold">{totalCompleted}</div>
                    </div>
                    <div className="rounded-lg border border-red-100 bg-white p-3 shadow-sm">
                        <div className="text-xs text-slate-500">Выполнено с просрочкой</div>
                        <div className="mt-1 text-2xl font-semibold text-red-700">{totalOverdue}</div>
                    </div>
                    <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                        <div className="text-xs text-slate-500">Процент просрочки</div>
                        <div className="mt-1 text-2xl font-semibold">{totalRate}%</div>
                    </div>
                </section>

                <div className="space-y-4">
                    {groups.length > 0 ? groups.map((group) => (
                        <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" key={group.group_id || group.group_name}>
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <h2 className="font-semibold">{group.group_name}</h2>
                                <div className="text-sm text-slate-500">
                                    выполнено {group.completed_count} · просрочено {group.completed_overdue_count}
                                </div>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="text-slate-500">
                                        <tr>
                                            <th className="py-2">Пользователь</th>
                                            <th>Выполнено</th>
                                            <th>Просрочено</th>
                                            <th>%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {group.users.map((user) => (
                                            <tr className="border-t border-slate-100" key={user.id}>
                                                <td className="py-2 font-medium">{user.name}</td>
                                                <td>{user.completed_count}</td>
                                                <td>
                                                    <span className={user.completed_overdue_count > 0 ? 'font-semibold text-red-700' : ''}>
                                                        {user.completed_overdue_count}
                                                    </span>
                                                </td>
                                                <td>{user.overdue_rate}%</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )) : (
                        <section className="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
                            Нет данных за выбранный период. Запустите синхронизацию статистики задач в Sonic Expert.
                        </section>
                    )}
                </div>
            </div>
        </div>
    );
}
