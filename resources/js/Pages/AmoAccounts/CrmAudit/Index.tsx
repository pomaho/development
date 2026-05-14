import DashboardMetric from '../../../Components/DashboardMetric';
import JsonDetails from '../../../Components/JsonDetails';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Pipeline = {
    id: number;
    amo_pipeline_id: number;
    name: string;
    is_main: boolean;
    is_unsorted_on: boolean;
};

type Field = {
    id: number;
    entity_type: string;
    amo_field_id: number;
    name: string;
    field_type: string;
};

type Entity = {
    id: number;
    entity_type: string;
    external_id: string;
    name: string;
    pipeline_id: number | string | null;
    status_id: number | string | null;
    synced_at: string | null;
    raw: Record<string, unknown>;
};

type Props = {
    account: Account;
    summary: {
        pipelines: number;
        statuses: number;
        custom_fields: number;
        last_sync: string | null;
        leads: number;
        contacts: number;
        events: number;
        tasks: number;
    };
    pipelines: Pipeline[];
    fields: Field[];
    recentEntities: Entity[];
    can: {
        sync: boolean;
    };
    defaults: {
        from: string;
        to: string;
        pipeline_id: string;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        sync: string;
        current_account: {
            dashboard: string;
            show: string;
            users: string;
            roles: string;
            leads: string;
            pipelines: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        };
    };
};

export default function CrmAuditIndex({ account, summary, pipelines, fields, recentEntities, can, defaults, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'CRM-аудит' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold">CRM-аудит: {account.name}</h1>
                    <div className="text-sm text-slate-500">{account.base_domain}</div>
                </div>
                {can.sync ? (
                    <form action={links.sync} className="grid gap-2 rounded border border-slate-200 bg-white p-3 text-sm md:grid-cols-5" method="post">
                        <input name="_token" type="hidden" value={csrf} />
                        <label>
                            <span className="text-xs text-slate-500">Воронка</span>
                            <select className="mt-1 w-full rounded border-slate-300" defaultValue={defaults.pipeline_id} name="pipeline_id">
                                <option value="">Все воронки</option>
                                {pipelines.map((pipeline) => (
                                    <option key={pipeline.amo_pipeline_id} value={pipeline.amo_pipeline_id}>{pipeline.name}</option>
                                ))}
                            </select>
                        </label>
                        <label>
                            <span className="text-xs text-slate-500">С даты</span>
                            <input className="mt-1 w-full rounded border-slate-300" defaultValue={defaults.from} name="from" type="date" />
                        </label>
                        <label>
                            <span className="text-xs text-slate-500">По дату</span>
                            <input className="mt-1 w-full rounded border-slate-300" defaultValue={defaults.to} name="to" type="date" />
                        </label>
                        <label className="flex items-end gap-2 pb-2">
                            <input className="rounded border-slate-300" name="structure_only" type="checkbox" value="1" />
                            <span>Только структура</span>
                        </label>
                        <button className="self-end rounded bg-blue-700 px-4 py-2 text-white hover:bg-blue-800" type="submit">Запустить</button>
                    </form>
                ) : null}
            </div>

            <div className="grid gap-4 md:grid-cols-4">
                <DashboardMetric label="Воронки" value={summary.pipelines} />
                <DashboardMetric label="Этапы" value={summary.statuses} />
                <DashboardMetric label="Поля CRM" value={summary.custom_fields} />
                <DashboardMetric label="Последняя выгрузка" value={summary.last_sync || 'нет'} />
                <DashboardMetric label="Сделки" value={summary.leads} />
                <DashboardMetric label="Контакты" value={summary.contacts} />
                <DashboardMetric label="События" value={summary.events} />
                <DashboardMetric label="Задачи" value={summary.tasks} />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 className="mb-3 font-semibold">Воронки</h2>
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500"><tr><th className="py-2">ID</th><th>Название</th><th>Главная</th><th>Неразобранное</th></tr></thead>
                        <tbody>
                            {pipelines.length > 0 ? pipelines.map((pipeline) => (
                                <tr className="border-t border-slate-100" key={pipeline.id}>
                                    <td className="py-2">{pipeline.amo_pipeline_id}</td>
                                    <td>{pipeline.name}</td>
                                    <td>{pipeline.is_main ? 'да' : 'нет'}</td>
                                    <td>{pipeline.is_unsorted_on ? 'да' : 'нет'}</td>
                                </tr>
                            )) : <EmptyRow colSpan={4} />}
                        </tbody>
                    </table>
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 className="mb-3 font-semibold">Поля CRM</h2>
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500"><tr><th className="py-2">Сущность</th><th>ID</th><th>Название</th><th>Тип</th></tr></thead>
                        <tbody>
                            {fields.length > 0 ? fields.map((field) => (
                                <tr className="border-t border-slate-100" key={field.id}>
                                    <td className="py-2">{field.entity_type}</td>
                                    <td>{field.amo_field_id}</td>
                                    <td>{field.name}</td>
                                    <td>{field.field_type}</td>
                                </tr>
                            )) : <EmptyRow colSpan={4} />}
                        </tbody>
                    </table>
                </section>
            </div>

            <section className="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 className="mb-3 font-semibold">Последние snapshots</h2>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500"><tr><th className="py-2">Тип</th><th>ID</th><th>Название</th><th>Pipeline</th><th>Status</th><th>Sync</th><th>Raw</th></tr></thead>
                        <tbody>
                            {recentEntities.length > 0 ? recentEntities.map((entity) => (
                                <tr className="border-t border-slate-100 align-top" key={entity.id}>
                                    <td className="py-2">{entity.entity_type}</td>
                                    <td>{entity.external_id}</td>
                                    <td>{entity.name}</td>
                                    <td>{entity.pipeline_id || '-'}</td>
                                    <td>{entity.status_id || '-'}</td>
                                    <td>{entity.synced_at || '-'}</td>
                                    <td><JsonDetails data={entity.raw} /></td>
                                </tr>
                            )) : <EmptyRow colSpan={7} />}
                        </tbody>
                    </table>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}

function EmptyRow({ colSpan }: { colSpan: number }) {
    return (
        <tr>
            <td className="py-4 text-slate-500" colSpan={colSpan}>Данных пока нет.</td>
        </tr>
    );
}
