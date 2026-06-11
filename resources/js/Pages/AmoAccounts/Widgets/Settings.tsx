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

type Diagnostics = {
    pipeline_id: number | null;
    pipeline_name: string | null;
    field_id: number | null;
    field_name: string;
    field_found: boolean;
    field_type: string | null;
    field_enum_count: number;
    synced_leads_total: number;
    period_leads_total: number;
    pipeline_leads_total: number;
    pipeline_period_leads_total: number;
    pipeline_first_lead_created_at: string | null;
    pipeline_last_lead_created_at: string | null;
    leads_with_field: number;
    assigned_leads: number;
    field_values: Array<{
        enum_id: number | null;
        value: string;
        count: number;
        matched_enum: boolean;
    }>;
    sample_leads: Array<{
        id: string;
        name: string;
        pipeline_id: number | null;
        status_id: number | null;
        created_at: string | null;
        field_values: Array<{
            enum_id: number | null;
            value: string;
        }>;
    }>;
};

type Props = {
    account: Account;
    widget: Widget;
    config: {
        pipeline_id: number | string | null;
        recruiter_field_id: number | string | null;
        manager_field_id: number | string | null;
        team_field_id: number | string | null;
        city_field_id: number | string | null;
    };
    diagnostics: Diagnostics;
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

export default function WidgetSettings({ account, widget, config, diagnostics, pipelines, leadFields, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const diagnosticMetrics = [
        ['Поле найдено', diagnostics.field_found ? 'да' : 'нет'],
        ['ID поля', diagnostics.field_id || '-'],
        ['Тип поля', diagnostics.field_type || '-'],
        ['Значений списка', diagnostics.field_enum_count],
        ['Всего сделок в базе', diagnostics.synced_leads_total],
        ['Сделок в выбранной воронке', diagnostics.pipeline_leads_total],
        ['Сделок с заполненным полем', diagnostics.leads_with_field],
        ['Сделок с распознанным значением', diagnostics.assigned_leads],
        ['Первая сделка воронки', diagnostics.pipeline_first_lead_created_at || '-'],
        ['Последняя сделка воронки', diagnostics.pipeline_last_lead_created_at || '-'],
    ];

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

                    <label className="block">
                        <span className="text-sm font-medium text-slate-700">Поле сделки с менеджером</span>
                        <select className="mt-1 w-full rounded border-slate-300" defaultValue={config.manager_field_id || ''} name="manager_field_id">
                            <option value="">Авто: поле “Менеджер”</option>
                            {leadFields.map((field) => (
                                <option key={field.id} value={field.id}>
                                    {field.name} · ID {field.id} · {field.field_type || 'без типа'}
                                </option>
                            ))}
                        </select>
                        <span className="mt-1 block text-xs text-slate-500">
                            Колонка “Передано менеджеру” считает сделки, где заполнены и рекрутер, и менеджер.
                        </span>
                    </label>

                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="block">
                            <span className="text-sm font-medium text-slate-700">Поле сделки с командой</span>
                            <select className="mt-1 w-full rounded border-slate-300" defaultValue={config.team_field_id || ''} name="team_field_id">
                                <option value="">Авто: поле “Команда”</option>
                                {leadFields.map((field) => (
                                    <option key={field.id} value={field.id}>
                                        {field.name} · ID {field.id} · {field.field_type || 'без типа'}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label className="block">
                            <span className="text-sm font-medium text-slate-700">Поле сделки с городом</span>
                            <select className="mt-1 w-full rounded border-slate-300" defaultValue={config.city_field_id || ''} name="city_field_id">
                                <option value="">Авто: поле “Город”</option>
                                {leadFields.map((field) => (
                                    <option key={field.id} value={field.id}>
                                        {field.name} · ID {field.id} · {field.field_type || 'без типа'}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>

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

            <section className="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-950">Диагностика отчета по рекрутерам</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Проверяет локально сохраненные сделки, выбранную воронку и поле “{diagnostics.field_name}”.
                        </p>
                    </div>
                    <div className="rounded bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                        Без запросов к amoCRM
                    </div>
                </div>

                <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    {diagnosticMetrics.map(([label, value]) => (
                        <div className="rounded border border-slate-100 bg-slate-50 p-3" key={label}>
                            <div className="text-xs text-slate-500">{label}</div>
                            <div className="mt-1 break-words text-sm font-semibold text-slate-950">{value}</div>
                        </div>
                    ))}
                </div>

                <div className="mt-5 overflow-x-auto">
                    <h3 className="mb-2 text-sm font-semibold text-slate-800">Найденные значения поля</h3>
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th className="px-3 py-2">Enum ID</th>
                                <th className="px-3 py-2">Значение</th>
                                <th className="px-3 py-2">Сделок</th>
                                <th className="px-3 py-2">Распознано</th>
                            </tr>
                        </thead>
                        <tbody>
                            {diagnostics.field_values.length > 0 ? diagnostics.field_values.map((value) => (
                                <tr className="border-t border-slate-100" key={`${value.enum_id || 'text'}-${value.value}`}>
                                    <td className="px-3 py-2 tabular-nums">{value.enum_id || '-'}</td>
                                    <td className="px-3 py-2 font-medium text-slate-900">{value.value || '-'}</td>
                                    <td className="px-3 py-2 tabular-nums">{value.count}</td>
                                    <td className="px-3 py-2">{value.matched_enum ? 'да' : 'нет'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="px-3 py-4 text-slate-500" colSpan={4}>
                                        Значения не найдены. Проверьте, что сделки синхронизированы за нужную воронку и поле выбрано верно.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="mt-5 overflow-x-auto">
                    <h3 className="mb-2 text-sm font-semibold text-slate-800">Примеры сделок с этим полем</h3>
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th className="px-3 py-2">ID</th>
                                <th className="px-3 py-2">Название</th>
                                <th className="px-3 py-2">Воронка</th>
                                <th className="px-3 py-2">Этап</th>
                                <th className="px-3 py-2">Создана</th>
                                <th className="px-3 py-2">Значения</th>
                            </tr>
                        </thead>
                        <tbody>
                            {diagnostics.sample_leads.length > 0 ? diagnostics.sample_leads.map((lead) => (
                                <tr className="border-t border-slate-100" key={lead.id}>
                                    <td className="px-3 py-2 tabular-nums">{lead.id}</td>
                                    <td className="px-3 py-2 font-medium text-slate-900">{lead.name || '-'}</td>
                                    <td className="px-3 py-2 tabular-nums">{lead.pipeline_id || '-'}</td>
                                    <td className="px-3 py-2 tabular-nums">{lead.status_id || '-'}</td>
                                    <td className="px-3 py-2 whitespace-nowrap">{lead.created_at || '-'}</td>
                                    <td className="px-3 py-2">
                                        {lead.field_values.map((value) => `${value.enum_id || '-'}: ${value.value || '-'}`).join('; ')}
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="px-3 py-4 text-slate-500" colSpan={6}>
                                        Нет примеров сделок с выбранным полем.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
