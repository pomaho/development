import { AlertTriangle, CheckCircle2, Clock3, DatabaseZap, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type IntervalOption = {
    minutes: number;
    label: string;
};

type Pipeline = {
    amo_pipeline_id: number;
    name: string;
    is_archive: boolean;
};

type Schedule = {
    id: number;
    amo_pipeline_id: number;
    pipeline_name: string | null;
    interval_minutes: number;
    interval_label: string;
    lookback_days: number;
    is_enabled: boolean;
    last_run_at: string | null;
    last_finished_at: string | null;
    next_run_at: string | null;
    last_status: string | null;
    last_synced_count: number | null;
    last_error: string | null;
};

type Props = {
    account: Account;
    can: {
        manage: boolean;
    };
    intervals: IntervalOption[];
    pipelines: Pipeline[];
    schedules: Schedule[];
    defaults: {
        interval_minutes: number;
        lookback_days: number;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        store: string;
        current_account: {
            dashboard: string;
            show: string;
            users: string;
            roles: string;
            leads: string;
            pipelines: string;
            lead_sync_schedules: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        };
    };
};

export default function LeadSyncSchedulesIndex({ account, can, intervals, pipelines, schedules, defaults, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const enabledCount = schedules.filter((schedule) => schedule.is_enabled).length;

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Sync сделок' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div className="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-theme-xs font-medium text-brand-700">
                        <DatabaseZap size={14} />
                        Управляемые расписания
                    </div>
                    <h1 className="mt-3 text-2xl font-semibold text-gray-900">Синхронизация сделок: {account.name}</h1>
                    <div className="mt-1 text-theme-sm text-gray-500">{account.base_domain}</div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <Metric label="Всего правил" value={schedules.length} />
                    <Metric label="Включено" value={enabledCount} tone="brand" />
                </div>
            </div>

            {pipelines.length === 0 ? (
                <section className="rounded-2xl border border-warning-200 bg-warning-50 p-5 text-warning-800">
                    <div className="flex gap-3">
                        <AlertTriangle className="mt-0.5 shrink-0" size={20} />
                        <div>
                            <h2 className="font-semibold">Воронки еще не загружены</h2>
                            <p className="mt-1 text-theme-sm">Сначала запустите CRM-аудит структуры, чтобы сервис увидел список воронок этого аккаунта.</p>
                            <a className="mt-3 inline-flex rounded-lg bg-warning-500 px-4 py-2 text-theme-sm font-medium text-white" href={links.current_account.crm_audit}>
                                Открыть CRM-аудит
                            </a>
                        </div>
                    </div>
                </section>
            ) : null}

            {can.manage ? (
                <section className="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold text-gray-900">Добавить расписание</h2>
                            <p className="text-theme-sm text-gray-500">Правило будет синхронизировать только выбранную воронку конкретного аккаунта.</p>
                        </div>
                    </div>
                    <form action={links.store} className="grid gap-4 lg:grid-cols-[minmax(260px,1fr)_180px_160px_120px_auto]" method="post">
                        <input name="_token" type="hidden" value={csrf} />
                        <Field label="Воронка">
                            <select className={inputClass} name="amo_pipeline_id" required>
                                <option value="">Выберите воронку</option>
                                {pipelines.map((pipeline) => (
                                    <option key={pipeline.amo_pipeline_id} value={pipeline.amo_pipeline_id}>
                                        {pipeline.name}{pipeline.is_archive ? ' (архив)' : ''}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Периодичность">
                            <select className={inputClass} defaultValue={defaults.interval_minutes} name="interval_minutes" required>
                                {intervals.map((interval) => (
                                    <option key={interval.minutes} value={interval.minutes}>{interval.label}</option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Окно, дней">
                            <input className={inputClass} defaultValue={defaults.lookback_days} max={365} min={1} name="lookback_days" required type="number" />
                        </Field>
                        <label className="flex items-end gap-2 pb-3 text-theme-sm text-gray-700">
                            <input className="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20" defaultChecked name="is_enabled" type="checkbox" value="1" />
                            Включено
                        </label>
                        <button className="self-end rounded-lg bg-brand-500 px-5 py-3 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" type="submit">
                            Добавить
                        </button>
                    </form>
                </section>
            ) : null}

            <section className="mt-6 rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="border-b border-gray-200 p-5">
                    <h2 className="text-lg font-semibold text-gray-900">Настроенные синхронизации</h2>
                    <p className="mt-1 text-theme-sm text-gray-500">Scheduler запускает только включенные правила, у которых наступил следующий запуск.</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1100px] text-left text-theme-sm">
                        <thead className="border-b border-gray-100 bg-gray-50 text-theme-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-5 py-3">Воронка</th>
                                <th className="px-5 py-3">Периодичность</th>
                                <th className="px-5 py-3">Окно</th>
                                <th className="px-5 py-3">Статус</th>
                                <th className="px-5 py-3">Последний запуск</th>
                                <th className="px-5 py-3">Следующий запуск</th>
                                <th className="px-5 py-3">Сделок</th>
                                {can.manage ? <th className="px-5 py-3 text-right">Действия</th> : null}
                            </tr>
                        </thead>
                        <tbody>
                            {schedules.length > 0 ? schedules.map((schedule) => (
                                <ScheduleRow
                                    canManage={can.manage}
                                    csrf={csrf}
                                    intervals={intervals}
                                    key={schedule.id}
                                    pipelines={pipelines}
                                    schedule={schedule}
                                    updateUrl={`${links.current_account.lead_sync_schedules}/${schedule.id}`}
                                />
                            )) : (
                                <tr>
                                    <td className="px-5 py-6 text-gray-500" colSpan={can.manage ? 8 : 7}>Настроенных синхронизаций пока нет.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}

function ScheduleRow({ canManage, csrf, intervals, pipelines, schedule, updateUrl }: {
    canManage: boolean;
    csrf: string;
    intervals: IntervalOption[];
    pipelines: Pipeline[];
    schedule: Schedule;
    updateUrl: string;
}) {
    return (
        <tr className="border-b border-gray-100 align-top last:border-b-0">
            <td className="px-5 py-4">
                {canManage ? (
                    <form action={updateUrl} className="contents" id={`schedule-${schedule.id}`} method="post">
                        <input name="_token" type="hidden" value={csrf} />
                        <input name="_method" type="hidden" value="put" />
                    </form>
                ) : null}
                {canManage ? (
                    <select className={inputClass} defaultValue={schedule.amo_pipeline_id} form={`schedule-${schedule.id}`} name="amo_pipeline_id" required>
                        {pipelines.map((pipeline) => (
                            <option key={pipeline.amo_pipeline_id} value={pipeline.amo_pipeline_id}>
                                {pipeline.name}{pipeline.is_archive ? ' (архив)' : ''}
                            </option>
                        ))}
                    </select>
                ) : (
                    <div>
                        <div className="font-medium text-gray-900">{schedule.pipeline_name || schedule.amo_pipeline_id}</div>
                        <div className="text-theme-xs text-gray-500">ID {schedule.amo_pipeline_id}</div>
                    </div>
                )}
                {schedule.last_error ? <div className="mt-2 max-w-md text-theme-xs text-error-600">{schedule.last_error}</div> : null}
            </td>
            <td className="px-5 py-4">
                {canManage ? (
                    <select className={inputClass} defaultValue={schedule.interval_minutes} form={`schedule-${schedule.id}`} name="interval_minutes" required>
                        {intervals.map((interval) => (
                            <option key={interval.minutes} value={interval.minutes}>{interval.label}</option>
                        ))}
                    </select>
                ) : schedule.interval_label}
            </td>
            <td className="px-5 py-4">
                {canManage ? (
                    <input className={inputClass} defaultValue={schedule.lookback_days} form={`schedule-${schedule.id}`} max={365} min={1} name="lookback_days" required type="number" />
                ) : `${schedule.lookback_days} дн.`}
            </td>
            <td className="px-5 py-4">
                {canManage ? (
                    <label className="flex items-center gap-2">
                        <input className="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20" defaultChecked={schedule.is_enabled} form={`schedule-${schedule.id}`} name="is_enabled" type="checkbox" value="1" />
                        <StatusBadge status={schedule.is_enabled ? schedule.last_status : 'disabled'} />
                    </label>
                ) : (
                    <StatusBadge status={schedule.is_enabled ? schedule.last_status : 'disabled'} />
                )}
            </td>
            <td className="px-5 py-4 text-gray-600">{schedule.last_run_at || '-'}</td>
            <td className="px-5 py-4 text-gray-600">{schedule.next_run_at || '-'}</td>
            <td className="px-5 py-4 font-medium text-gray-900">{schedule.last_synced_count ?? '-'}</td>
            {canManage ? (
                <td className="px-5 py-4">
                    <div className="flex justify-end gap-2">
                        <button className="rounded-lg border border-gray-200 px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-50" form={`schedule-${schedule.id}`} type="submit">
                            Сохранить
                        </button>
                        <form action={updateUrl} method="post">
                            <input name="_token" type="hidden" value={csrf} />
                            <input name="_method" type="hidden" value="delete" />
                            <button className="inline-flex size-10 items-center justify-center rounded-lg border border-error-200 text-error-600 hover:bg-error-50" type="submit" aria-label="Удалить расписание">
                                <Trash2 size={17} />
                            </button>
                        </form>
                    </div>
                </td>
            ) : null}
        </tr>
    );
}

function Metric({ label, value, tone = 'gray' }: { label: string; value: number; tone?: 'gray' | 'brand' }) {
    return (
        <div className={`rounded-2xl border bg-white px-5 py-4 shadow-theme-sm ${tone === 'brand' ? 'border-brand-200' : 'border-gray-200'}`}>
            <div className="text-theme-xs uppercase text-gray-500">{label}</div>
            <div className="mt-1 text-2xl font-semibold text-gray-900">{value}</div>
        </div>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label>
            <span className="text-theme-xs font-medium text-gray-500">{label}</span>
            <div className="mt-1.5">{children}</div>
        </label>
    );
}

function StatusBadge({ status }: { status: string | null }) {
    const meta = statusMeta(status);

    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-theme-xs font-medium ${meta.className}`}>
            {meta.icon}
            {meta.label}
        </span>
    );
}

function statusMeta(status: string | null) {
    if (status === 'completed') {
        return { label: 'Успешно', className: 'bg-success-50 text-success-700', icon: <CheckCircle2 size={13} /> };
    }

    if (status === 'failed') {
        return { label: 'Ошибка', className: 'bg-error-50 text-error-700', icon: <AlertTriangle size={13} /> };
    }

    if (status === 'running') {
        return { label: 'В работе', className: 'bg-brand-50 text-brand-700', icon: <DatabaseZap size={13} /> };
    }

    if (status === 'disabled') {
        return { label: 'Выключено', className: 'bg-gray-100 text-gray-500', icon: <Clock3 size={13} /> };
    }

    return { label: 'Ожидает', className: 'bg-warning-50 text-warning-700', icon: <Clock3 size={13} /> };
}

const inputClass = 'h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10';
