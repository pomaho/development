import { AlertTriangle, CheckCircle2, Clock } from 'lucide-react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';
import Pagination from '../../../Components/Pagination';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Task = {
    id: number;
    external_id: string;
    text: string;
    responsible_user_id: number | null;
    responsible_name: string | null;
    deadline: string | null;
    is_completed: boolean;
    is_overdue: boolean;
    completed_by_id: number | null;
    completed_by_name: string | null;
    result: string | null;
    created_at: string | null;
};

type User = {
    id: number;
    name: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    account: Account;
    tasks: {
        data: Task[];
        links: PaginationLink[];
        from: number;
        total: number;
    };
    users: User[];
    filters: {
        status: string;
        overdue: string;
        responsible_user_id: string;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        current_account: {
            dashboard: string;
            show: string;
            users: string;
            roles: string;
            leads: string;
            pipelines: string;
            catalogs: string;
            responsibility_redistribution: string;
            task_statistics: string;
            events_sync: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        };
    };
};

const inputClass = 'h-10 rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10';
const selectClass = `${inputClass} pr-8`;

export default function TasksIndex({ account, tasks, users, filters, links }: Props) {
    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Задачи' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="text-theme-sm font-medium text-brand-600">CRM данные</p>
                    <h1 className="mt-1 text-2xl font-semibold text-gray-900">Задачи: {account.name}</h1>
                    <div className="mt-1 text-theme-sm text-gray-500">{account.base_domain}</div>
                </div>
                <div className="flex items-center gap-2 rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-theme-sm">
                    <div className="text-theme-xs uppercase text-gray-500">Всего</div>
                    <div className="ml-2 text-2xl font-semibold text-gray-900">{tasks.total}</div>
                </div>
            </div>

            <form className="mb-4 flex flex-wrap gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm" method="get">
                <select className={selectClass} defaultValue={filters.status} name="status">
                    <option value="">Все задачи</option>
                    <option value="open">Открытые</option>
                    <option value="closed">Закрытые</option>
                </select>
                <select className={selectClass} defaultValue={filters.overdue} name="overdue">
                    <option value="">Все по сроку</option>
                    <option value="1">Просроченные</option>
                    <option value="0">Не просроченные</option>
                </select>
                <select className={selectClass} defaultValue={filters.responsible_user_id} name="responsible_user_id">
                    <option value="">Все пользователи</option>
                    {users.map((user) => (
                        <option key={user.id} value={user.id}>{user.name}</option>
                    ))}
                </select>
                <button className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" type="submit">
                    Фильтр
                </button>
                <a className="inline-flex h-10 items-center rounded-lg border border-gray-200 px-4 text-theme-sm text-gray-700 hover:border-brand-300" href="?">
                    Сбросить
                </a>
            </form>

            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-theme-sm">
                        <thead className="border-b border-gray-100 bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th className="px-5 py-3">#</th>
                                <th className="px-5 py-3">Задача</th>
                                <th className="px-5 py-3">Ответственный</th>
                                <th className="px-5 py-3">Дедлайн</th>
                                <th className="px-5 py-3">Статус</th>
                                <th className="px-5 py-3">Закрыл</th>
                                <th className="px-5 py-3">Результат</th>
                                <th className="px-5 py-3">Создана</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {tasks.data.length > 0 ? tasks.data.map((task, index) => (
                                <tr className="align-top" key={task.id}>
                                    <td className="px-5 py-3 text-gray-400 tabular-nums">{(tasks.from ?? 1) + index}</td>
                                    <td className="max-w-sm px-5 py-3">
                                        <div className="line-clamp-2 font-medium text-gray-900">{task.text}</div>
                                        <div className="mt-0.5 text-theme-xs text-gray-400">ID {task.external_id}</div>
                                    </td>
                                    <td className="px-5 py-3 text-gray-700">{task.responsible_name || `ID ${task.responsible_user_id}` || '—'}</td>
                                    <td className="px-5 py-3">
                                        {task.deadline ? (
                                            <span className={task.is_overdue ? 'text-error-600 font-medium' : 'text-gray-700'}>
                                                {task.deadline}
                                            </span>
                                        ) : (
                                            <span className="text-gray-400">—</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-3">
                                        <TaskStatusBadge isCompleted={task.is_completed} isOverdue={task.is_overdue} />
                                    </td>
                                    <td className="px-5 py-3 text-gray-700">
                                        {task.is_completed
                                            ? (task.completed_by_name ?? <span className="text-gray-400">—</span>)
                                            : <span className="text-gray-300">—</span>}
                                    </td>
                                    <td className="max-w-xs px-5 py-3 text-gray-600">
                                        {task.is_completed
                                            ? (task.result ? <span className="line-clamp-2">{task.result}</span> : <span className="text-gray-400">—</span>)
                                            : <span className="text-gray-300">—</span>}
                                    </td>
                                    <td className="px-5 py-3 text-gray-500">{task.created_at || '—'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="px-5 py-8 text-center text-gray-500" colSpan={8}>
                                        Задачи не найдены.{' '}
                                        {!filters.status && !filters.overdue && !filters.responsible_user_id
                                            ? 'Запустите синхронизацию задач в разделе Расписания.'
                                            : 'Попробуйте изменить фильтры.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="border-t border-gray-100 px-5 pb-5">
                    <Pagination links={tasks.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function TaskStatusBadge({ isCompleted, isOverdue }: { isCompleted: boolean; isOverdue: boolean }) {
    if (isCompleted) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-theme-xs font-medium text-success-700">
                <CheckCircle2 size={12} />
                Закрыта
            </span>
        );
    }

    if (isOverdue) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-error-50 px-2.5 py-1 text-theme-xs font-medium text-error-700">
                <AlertTriangle size={12} />
                Просрочена
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-medium text-brand-700">
            <Clock size={12} />
            Открыта
        </span>
    );
}
