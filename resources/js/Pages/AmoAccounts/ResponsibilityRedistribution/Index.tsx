import { AlertTriangle, CheckCircle2, UsersRound } from 'lucide-react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type AmoUser = {
    id: number;
    name: string;
    email: string | null;
};

type TargetSummary = {
    target_user_id: number;
    contacts_count: number;
    leads_count: number;
};

type Preview = {
    source_user_id: number;
    target_user_ids: number[];
    contacts_count: number;
    leads_count: number;
    by_target: TargetSummary[];
    sample_contacts: Array<{
        id: number;
        name: string;
        lead_ids: number[];
    }>;
} | null;

type Run = {
    id: number;
    source_user_id: number;
    target_user_ids: number[];
    status: string;
    result: {
        updated_contacts?: number;
        updated_leads?: number;
        remaining_contacts_count?: number;
        remaining_leads_count?: number;
        by_target?: TargetSummary[];
    } | null;
    error_message: string | null;
    created_at: string | null;
    finished_at: string | null;
};

type Props = {
    account: Account;
    users: AmoUser[];
    form: {
        source_user_id: string;
        target_user_ids: string[];
    };
    preview: Preview;
    runs: Run[];
    error: string | null;
    can: {
        sync: boolean;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        preview: string;
        submit: string;
        current_account: {
            dashboard: string;
            show: string;
            users: string;
            roles: string;
            leads: string;
            pipelines: string;
            catalogs: string;
            responsibility_redistribution: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        };
    };
};

const userName = (users: AmoUser[], id: number) => users.find((user) => user.id === id)?.name || `ID ${id}`;

const statusLabel = (status: string) => {
    if (status === 'completed') {
        return 'завершено';
    }

    if (status === 'failed') {
        return 'ошибка';
    }

    if (status === 'running') {
        return 'выполняется';
    }

    return status;
};

export default function ResponsibilityRedistributionIndex({ account, users, form, preview, runs, error, can, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const targetIds = new Set((form.target_user_ids || []).map(String));

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Ответственные' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold">Распределение ответственного</h1>
                    <div className="mt-1 text-sm text-slate-500">{account.name} · {account.base_domain}</div>
                </div>
                <a className="rounded border border-slate-300 px-3 py-2 text-sm hover:border-blue-400 hover:text-blue-700" href={links.current_account.users}>
                    Таблица пользователей
                </a>
            </div>

            {error ? (
                <div className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {error}
                </div>
            ) : null}

            <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="flex items-start gap-3">
                    <div className="rounded bg-blue-50 p-2 text-blue-700">
                        <UsersRound size={20} />
                    </div>
                    <div>
                        <h2 className="font-semibold">Кого перераспределяем</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Контакты старого ответственного распределяются по выбранным активным пользователям. Связанные сделки получают того же нового ответственного, что и контакт.
                        </p>
                    </div>
                </div>

                <form action={links.preview} className="mt-4 grid gap-4 lg:grid-cols-[1fr_2fr_auto]" method="post">
                    <input name="_token" type="hidden" value={csrf} />
                    <label className="block text-sm">
                        <span>Текущий ответственный</span>
                        <select className="mt-1 w-full rounded border-slate-300" defaultValue={form.source_user_id} name="source_user_id" required>
                            <option value="">Выберите пользователя</option>
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>{user.name}{user.email ? ` · ${user.email}` : ''}</option>
                            ))}
                        </select>
                    </label>

                    <div className="text-sm">
                        <div>Новые ответственные</div>
                        <div className="mt-1 grid max-h-52 gap-2 overflow-y-auto rounded border border-slate-200 bg-slate-50 p-3 md:grid-cols-2">
                            {users.map((user) => (
                                <label className="flex items-start gap-2 rounded bg-white p-2" key={user.id}>
                                    <input
                                        className="mt-1 rounded border-slate-300"
                                        defaultChecked={targetIds.has(String(user.id))}
                                        name="target_user_ids[]"
                                        type="checkbox"
                                        value={user.id}
                                    />
                                    <span>
                                        <span className="block font-medium">{user.name}</span>
                                        {user.email ? <span className="text-xs text-slate-500">{user.email}</span> : null}
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-end">
                        <button className="w-full rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800 disabled:opacity-50" disabled={! can.sync} type="submit">
                            Показать план
                        </button>
                    </div>
                </form>
            </section>

            {preview ? (
                <section className="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="font-semibold">План распределения</h2>
                            <div className="mt-1 text-sm text-slate-500">Источник: {userName(users, preview.source_user_id)}</div>
                        </div>
                        <div className="flex flex-wrap gap-2 text-sm">
                            <span className="rounded bg-slate-100 px-2 py-1">Контактов: {preview.contacts_count}</span>
                            <span className="rounded bg-slate-100 px-2 py-1">Сделок: {preview.leads_count}</span>
                        </div>
                    </div>

                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-slate-500">
                                <tr><th className="py-2">Новый ответственный</th><th>Контакты</th><th>Связанные сделки</th></tr>
                            </thead>
                            <tbody>
                                {preview.by_target.map((row) => (
                                    <tr className="border-t border-slate-100" key={row.target_user_id}>
                                        <td className="py-2 font-medium">{userName(users, row.target_user_id)}</td>
                                        <td>{row.contacts_count}</td>
                                        <td>{row.leads_count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <form action={links.submit} className="mt-4 flex flex-wrap items-center gap-3" method="post">
                        <input name="_token" type="hidden" value={csrf} />
                        <input name="source_user_id" type="hidden" value={preview.source_user_id} />
                        {preview.target_user_ids.map((targetUserId) => (
                            <input key={targetUserId} name="target_user_ids[]" type="hidden" value={targetUserId} />
                        ))}
                        <button className="rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800 disabled:opacity-50" disabled={! can.sync || preview.contacts_count === 0} type="submit">
                            Запустить распределение
                        </button>
                        <span className="text-sm text-slate-500">После запуска сервис повторно проверит остатки на старом ответственном.</span>
                    </form>
                </section>
            ) : null}

            <section className="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 className="font-semibold">Последние запуски</h2>
                <div className="mt-3 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500">
                            <tr><th className="py-2">Дата</th><th>Источник</th><th>Статус</th><th>Контакты</th><th>Сделки</th><th>Остатки</th></tr>
                        </thead>
                        <tbody>
                            {runs.length > 0 ? runs.map((run) => (
                                <tr className="align-top border-t border-slate-100" key={run.id}>
                                    <td className="py-3">{run.created_at || '-'}</td>
                                    <td className="py-3">{userName(users, run.source_user_id)}</td>
                                    <td className="py-3">
                                        {run.status === 'completed' ? (
                                            <span className="inline-flex items-center gap-1 rounded bg-emerald-50 px-2 py-1 text-xs text-emerald-700"><CheckCircle2 size={14} />{statusLabel(run.status)}</span>
                                        ) : run.status === 'failed' ? (
                                            <span className="inline-flex items-center gap-1 rounded bg-red-50 px-2 py-1 text-xs text-red-700"><AlertTriangle size={14} />{statusLabel(run.status)}</span>
                                        ) : (
                                            <span className="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600">{statusLabel(run.status)}</span>
                                        )}
                                        {run.error_message ? <div className="mt-1 max-w-md text-xs text-red-700">{run.error_message}</div> : null}
                                    </td>
                                    <td className="py-3">{run.result?.updated_contacts ?? '-'}</td>
                                    <td className="py-3">{run.result?.updated_leads ?? '-'}</td>
                                    <td className="py-3">
                                        {run.result ? (
                                            <span>{run.result.remaining_contacts_count ?? 0} контактов / {run.result.remaining_leads_count ?? 0} сделок</span>
                                        ) : '-'}
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="py-4 text-slate-500" colSpan={6}>Запусков пока нет.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
