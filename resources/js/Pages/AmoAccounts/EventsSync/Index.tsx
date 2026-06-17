import { Database } from 'lucide-react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';
import Pagination from '../../../Components/Pagination';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Coverage = {
    events_count: number;
    period_from: string | null;
    period_to: string | null;
    last_synced_at: string | null;
    cursor: string | null;
};

type Group = {
    id: number;
    name: string;
    users_count: number;
};

type Event = {
    id: number;
    external_id: string;
    event_type: string;
    entity_type: string | null;
    entity_id: number | string | null;
    created_by_id: number | null;
    created_by_name: string | null;
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
    coverage: Coverage;
    reportSettings: {
        avito_recruiting_group_id: number | string | null;
    };
    groups: Group[];
    events: {
        data: Event[];
        links: PaginationLink[];
        from: number;
        total: number;
    };
    eventTypes: string[];
    users: User[];
    filters: {
        event_type: string;
        entity_type: string;
        created_by: string;
        date_from: string;
        date_to: string;
    };
    can: {
        sync: boolean;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        events_sync_settings: string;
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

const ENTITY_TYPE_LABELS: Record<string, string> = {
    leads: 'Сделки',
    contacts: 'Контакты',
    companies: 'Компании',
    tasks: 'Задачи',
    customers: 'Покупатели',
    catalogs: 'Каталоги',
};

const EVENT_TYPE_LABELS: Record<string, string> = {
    lead_added: 'Сделка добавлена',
    lead_status_changed: 'Смена статуса',
    lead_responsible_changed: 'Смена ответственного',
    lead_deleted: 'Сделка удалена',
    lead_restored: 'Сделка восстановлена',
    task_completed: 'Задача выполнена',
    task_added: 'Задача добавлена',
    task_responsible_changed: 'Смена ответ. задачи',
    contact_added: 'Контакт добавлен',
    contact_responsible_changed: 'Смена ответ. контакта',
    note_added: 'Примечание добавлено',
    incoming_call: 'Входящий звонок',
    outgoing_call: 'Исходящий звонок',
    incoming_chat_message: 'Входящее сообщение',
    outgoing_chat_message: 'Исходящее сообщение',
};

const inputClass = 'h-10 rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10';
const selectClass = `${inputClass} pr-8`;

export default function EventsSyncIndex({ account, coverage, reportSettings, groups, events, eventTypes, users, filters, can, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'События amoCRM' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="text-theme-sm font-medium text-brand-600">CRM данные</p>
                    <h1 className="mt-1 text-2xl font-semibold text-gray-900">События amoCRM: {account.name}</h1>
                    <div className="mt-1 text-theme-sm text-gray-500">{account.base_domain}</div>
                </div>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                    <div className="text-theme-xs uppercase text-gray-500">Событий в базе</div>
                    <div className="mt-1 text-3xl font-semibold text-gray-900">{coverage.events_count.toLocaleString('ru')}</div>
                </div>
                <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                    <div className="text-theme-xs uppercase text-gray-500">Самое раннее</div>
                    <div className="mt-1 text-lg font-semibold text-gray-900">{coverage.period_from || '—'}</div>
                </div>
                <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                    <div className="text-theme-xs uppercase text-gray-500">Самое позднее</div>
                    <div className="mt-1 text-lg font-semibold text-gray-900">{coverage.period_to || '—'}</div>
                </div>
                <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                    <div className="text-theme-xs uppercase text-gray-500">Incremental cursor</div>
                    <div className="mt-1 text-lg font-semibold text-gray-900">{coverage.cursor || '—'}</div>
                </div>
            </div>

            <section className="mb-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="font-semibold">Настройки отчетов по событиям</h2>
                        <div className="mt-1 text-theme-sm text-gray-500">
                            Если список групп пустой, запустите синхронизацию пользователей или введите group_id вручную.
                        </div>
                    </div>
                </div>
                <form action={links.events_sync_settings} className="mt-4 grid gap-3 text-sm md:grid-cols-[1fr_220px_auto]" method="post">
                    <input name="_token" type="hidden" value={csrf} />
                    <label className="block">
                        <span>Отдел для отчета "Авито рекрутинг"</span>
                        <select className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" defaultValue={reportSettings.avito_recruiting_group_id || ''} name="avito_recruiting_group_id">
                            <option value="">Автоопределение по названию группы</option>
                            {groups.map((group) => (
                                <option key={group.id} value={group.id}>
                                    {group.name} · ID {group.id} · пользователей {group.users_count}
                                </option>
                            ))}
                        </select>
                        {groups.length === 0 && (
                            <span className="mt-1 block text-xs text-amber-700">Группы пока не найдены в snapshots пользователей.</span>
                        )}
                    </label>
                    <label className="block">
                        <span>group_id вручную</span>
                        <input
                            className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10"
                            min="1"
                            name="avito_recruiting_group_id_manual"
                            placeholder="например 12345"
                            type="number"
                        />
                    </label>
                    <div className="flex items-end">
                        <button
                            className="rounded bg-gray-900 px-4 py-2 text-white hover:bg-gray-800 disabled:opacity-50"
                            disabled={!can.sync}
                            type="submit"
                        >
                            Сохранить
                        </button>
                    </div>
                </form>
            </section>

            <section>
                <div className="mb-3 flex items-center gap-2">
                    <Database className="h-4 w-4 text-brand-600" />
                    <h2 className="font-semibold text-gray-900">Список событий</h2>
                    <span className="ml-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-xs font-medium text-gray-600">{events.total.toLocaleString('ru')}</span>
                </div>

                <form className="mb-4 flex flex-wrap gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm" method="get">
                    <select className={selectClass} defaultValue={filters.event_type} name="event_type">
                        <option value="">Все типы событий</option>
                        {eventTypes.map((type) => (
                            <option key={type} value={type}>
                                {EVENT_TYPE_LABELS[type] ?? type}
                            </option>
                        ))}
                    </select>
                    <select className={selectClass} defaultValue={filters.entity_type} name="entity_type">
                        <option value="">Все сущности</option>
                        {Object.entries(ENTITY_TYPE_LABELS).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                    <select className={selectClass} defaultValue={filters.created_by} name="created_by">
                        <option value="">Все пользователи</option>
                        {users.map((user) => (
                            <option key={user.id} value={user.id}>{user.name}</option>
                        ))}
                    </select>
                    <input
                        className={inputClass}
                        defaultValue={filters.date_from}
                        name="date_from"
                        placeholder="Дата от"
                        type="date"
                    />
                    <input
                        className={inputClass}
                        defaultValue={filters.date_to}
                        name="date_to"
                        placeholder="Дата до"
                        type="date"
                    />
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
                                    <th className="px-5 py-3">Тип события</th>
                                    <th className="px-5 py-3">Сущность</th>
                                    <th className="px-5 py-3">Пользователь</th>
                                    <th className="px-5 py-3">Дата</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {events.data.length > 0 ? events.data.map((event, index) => (
                                    <tr className="align-top" key={event.id}>
                                        <td className="px-5 py-3 text-gray-400 tabular-nums">{(events.from ?? 1) + index}</td>
                                        <td className="px-5 py-3">
                                            <EventTypeBadge type={event.event_type} />
                                        </td>
                                        <td className="px-5 py-3">
                                            {event.entity_type ? (
                                                <span className="text-gray-700">
                                                    {ENTITY_TYPE_LABELS[event.entity_type] ?? event.entity_type}
                                                    {event.entity_id ? <span className="ml-1 text-gray-400">#{event.entity_id}</span> : null}
                                                </span>
                                            ) : (
                                                <span className="text-gray-400">—</span>
                                            )}
                                        </td>
                                        <td className="px-5 py-3 text-gray-700">
                                            {event.created_by_name || (event.created_by_id ? `ID ${event.created_by_id}` : '—')}
                                        </td>
                                        <td className="px-5 py-3 text-gray-500">{event.created_at || '—'}</td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td className="px-5 py-8 text-center text-gray-500" colSpan={5}>
                                            {Object.values(filters).some(Boolean)
                                                ? 'По заданным фильтрам событий не найдено.'
                                                : 'Событий пока нет. Запустите синхронизацию событий через расписания.'}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-gray-100 px-5 pb-5">
                        <Pagination links={events.links} />
                    </div>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}

function EventTypeBadge({ type }: { type: string }) {
    const label = EVENT_TYPE_LABELS[type] ?? type;

    const colorMap: Record<string, string> = {
        task_completed: 'bg-success-50 text-success-700',
        lead_status_changed: 'bg-brand-50 text-brand-700',
        incoming_call: 'bg-warning-50 text-warning-700',
        outgoing_call: 'bg-warning-50 text-warning-700',
    };

    const colorClass = colorMap[type] ?? 'bg-gray-100 text-gray-700';

    return (
        <span className={`inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium ${colorClass}`}>
            {label}
        </span>
    );
}
