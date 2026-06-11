import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Widget = {
    id: number;
    code: string;
    name: string;
};

type Pipeline = {
    id: number;
    name: string;
    is_archive: boolean;
};

type LeadField = {
    id: number;
    name: string;
    field_type: string | null;
};

type Props = {
    account: Account;
    widget: Widget;
    config: {
        pipeline_id: number | string | null;
        recruiter_field_id: number | string | null;
    };
    pipelines: Pipeline[];
    leadFields: LeadField[];
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        widgets: string;
        save: string;
        crm_fields: string;
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

export default function WidgetSettings({ account, widget, config, pipelines, leadFields, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Dashboard-блоки', href: links.widgets },
                { label: 'Настройки' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold">Настройки отчета: {widget.name}</h1>
                    <div className="text-sm text-slate-500">{account.name} · {account.base_domain}</div>
                </div>
                <a className="rounded border border-slate-300 bg-white px-4 py-2 text-sm hover:border-blue-400" href={links.widgets}>
                    Назад к блокам
                </a>
            </div>

            <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <form action={links.save} className="grid gap-4" method="post">
                    <input name="_token" type="hidden" value={csrf} />
                    <label className="block">
                        <span className="text-sm font-medium text-slate-700">Воронка для отчета</span>
                        <select className="mt-1 w-full rounded border-slate-300" defaultValue={config.pipeline_id || ''} name="pipeline_id">
                            <option value="">Все воронки</option>
                            {pipelines.map((pipeline) => (
                                <option key={pipeline.id} value={pipeline.id}>
                                    {pipeline.name} · ID {pipeline.id}{pipeline.is_archive ? ' · архивная' : ''}
                                </option>
                            ))}
                        </select>
                        <span className="mt-1 block text-xs text-slate-500">
                            Для текущего отчета выберите “Массовый подбор”.
                        </span>
                    </label>

                    <label className="block">
                        <span className="text-sm font-medium text-slate-700">Поле сделки с рекрутером</span>
                        <select className="mt-1 w-full rounded border-slate-300" defaultValue={config.recruiter_field_id || ''} name="recruiter_field_id">
                            <option value="">Авто: поле “Рекрутер”</option>
                            {leadFields.map((field) => (
                                <option key={field.id} value={field.id}>
                                    {field.name} · ID {field.id} · {field.field_type || 'без типа'}
                                </option>
                            ))}
                        </select>
                        <span className="mt-1 block text-xs text-slate-500">
                            Если поле не видно, обновите CRM-аудит структуры и проверьте список полей.
                        </span>
                    </label>

                    <div className="flex flex-wrap items-center gap-3">
                        <button className="rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800" type="submit">
                            Сохранить настройки
                        </button>
                        <a className="text-sm text-blue-700 hover:text-blue-900" href={links.crm_fields}>
                            Открыть список полей сделок и контактов
                        </a>
                    </div>
                </form>
            </section>
        </AuthenticatedLayout>
    );
}
