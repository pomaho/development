import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Pipeline = {
    id: number;
    name: string;
};

type Status = {
    id: number;
    pipeline_id: number;
    name: string;
};

type PlanRow = {
    source_status_id: number;
    source_status_name: string;
    target_status_id: number | null;
    target_status_name: string | null;
    lead_count: number;
    can_transfer: boolean;
};

type Plan = {
    rows: PlanRow[];
    total_leads: number;
    transferable_leads: number;
    blocked_leads: number;
} | null;

type Props = {
    account: Account;
    pipelines: Pipeline[];
    statuses: Status[];
    filters: {
        source_pipeline_id: string;
        target_pipeline_id: string;
        status_map: Record<string, number>;
    };
    plan: Plan;
    can: {
        sync: boolean;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        submit: string;
        preview: string;
        current_account: {
            dashboard: string;
            show: string;
            users: string;
            roles: string;
            leads: string;
            pipelines: string;
            catalogs: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        };
    };
};

export default function TransferLeads({ account, pipelines, statuses, filters, plan, can, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const targetStatuses = statuses.filter((status) => String(status.pipeline_id) === filters.target_pipeline_id);

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Воронки', href: links.current_account.pipelines },
                { label: 'Перенос сделок' },
            ]}
            links={links}
        >
            <div className="mb-6">
                <a className="text-sm text-blue-700 hover:text-blue-900" href={links.current_account.pipelines}>← Все воронки</a>
                <h1 className="mt-2 text-2xl font-semibold">Перенос сделок между воронками</h1>
                <div className="text-sm text-slate-500">{account.name} · {account.base_domain}</div>
            </div>

            <form action={links.preview} className="mb-4 grid gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_1fr_auto]" method="get">
                <label className="block text-sm">
                    <span>Исходная воронка</span>
                    <select className="mt-1 w-full rounded border-slate-300" defaultValue={filters.source_pipeline_id} name="source_pipeline_id" required>
                        <option value="">Выберите воронку</option>
                        {pipelines.map((pipeline) => <option key={pipeline.id} value={pipeline.id}>{pipeline.name}</option>)}
                    </select>
                </label>
                <label className="block text-sm">
                    <span>Целевая воронка</span>
                    <select className="mt-1 w-full rounded border-slate-300" defaultValue={filters.target_pipeline_id} name="target_pipeline_id" required>
                        <option value="">Выберите воронку</option>
                        {pipelines.map((pipeline) => <option key={pipeline.id} value={pipeline.id}>{pipeline.name}</option>)}
                    </select>
                </label>
                <div className="flex items-end">
                    <button className="w-full rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800" type="submit">Показать план</button>
                </div>
            </form>

            {plan ? (
                <form action={links.submit} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" method="post">
                    <input name="_token" type="hidden" value={csrf} />
                    <input name="source_pipeline_id" type="hidden" value={filters.source_pipeline_id} />
                    <input name="target_pipeline_id" type="hidden" value={filters.target_pipeline_id} />

                    <div className="mb-4 grid gap-3 md:grid-cols-3">
                        <div className="rounded border border-slate-200 bg-slate-50 p-3">
                            <div className="text-sm text-slate-500">Всего в исходной воронке</div>
                            <div className="mt-1 text-2xl font-semibold">{plan.total_leads}</div>
                        </div>
                        <div className="rounded border border-emerald-200 bg-emerald-50 p-3">
                            <div className="text-sm text-emerald-700">Будет перенесено</div>
                            <div className="mt-1 text-2xl font-semibold text-emerald-800">{plan.transferable_leads}</div>
                        </div>
                        <div className="rounded border border-amber-200 bg-amber-50 p-3">
                            <div className="text-sm text-amber-700">Без сопоставления</div>
                            <div className="mt-1 text-2xl font-semibold text-amber-800">{plan.blocked_leads}</div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-slate-500">
                                <tr>
                                    <th className="py-2">Исходный этап</th>
                                    <th>Сделок</th>
                                    <th>Целевой этап</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                {plan.rows.map((row) => (
                                    <tr className="align-top border-t border-slate-100" key={row.source_status_id}>
                                        <td className="py-3">
                                            <div className="font-medium">{row.source_status_name}</div>
                                            <div className="text-xs text-slate-500">ID {row.source_status_id}</div>
                                        </td>
                                        <td className="py-3">{row.lead_count}</td>
                                        <td className="py-3">
                                            <select className="w-full rounded border-slate-300" defaultValue={row.target_status_id || ''} name={`status_map[${row.source_status_id}]`}>
                                                <option value="">Не переносить</option>
                                                {targetStatuses.map((status) => <option key={status.id} value={status.id}>{status.name}</option>)}
                                            </select>
                                        </td>
                                        <td className="py-3">
                                            {row.can_transfer ? (
                                                <span className="rounded bg-emerald-50 px-2 py-1 text-xs text-emerald-700">готово</span>
                                            ) : (
                                                <span className="rounded bg-amber-50 px-2 py-1 text-xs text-amber-700">нужен маппинг</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-4 flex flex-wrap items-center gap-3">
                        <button className="rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800 disabled:opacity-50" disabled={! can.sync || plan.transferable_leads === 0} type="submit">Перенести сделки</button>
                        <div className="text-sm text-slate-500">Перед переносом проверьте маппинг этапов. Сделки без целевого этапа не будут изменены.</div>
                    </div>
                </form>
            ) : (
                <div className="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-500 shadow-sm">
                    Выберите исходную и целевую воронку, чтобы увидеть план переноса. Данные берутся из последнего CRM-аудита/снимка сделок.
                </div>
            )}
        </AuthenticatedLayout>
    );
}
