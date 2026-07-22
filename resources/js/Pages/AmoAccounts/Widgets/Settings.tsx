import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = { id: number; name: string; base_domain: string };
type Widget = { id: number; code: string; name: string };
type Pipeline = { id: number; name: string; is_archive: boolean };
type PipelineStatus = { id: number; name: string; pipeline_id: number };
type LeadField = { id: number; name: string; field_type: string | null };

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
    field_values: Array<{ enum_id: number | null; value: string; count: number; matched_enum: boolean }>;
    sample_leads: Array<{
        id: string; name: string;
        pipeline_id: number | null; status_id: number | null; created_at: string | null;
        field_values: Array<{ enum_id: number | null; value: string }>;
    }>;
};

type RecruiterConfig = {
    pipeline_id?: number | string | null;
    recruiter_field_id?: number | string | null;
    manager_field_id?: number | string | null;
    team_field_id?: number | string | null;
    city_field_id?: number | string | null;
    source_field_id?: number | string | null;
    success_status_id?: number | string | null;
};

type TopupConfig = {
    pipeline_id?: number | string | null;
    manager_field_id?: number | string | null;
    prepayment_field_id?: number | string | null;
    topup_date_field_id?: number | string | null;
};

type ProductGroupConfig = {
    pipeline_id?: number | string | null;
    product_group_field_id?: number | string | null;
};

type Props = {
    account: Account;
    widget: Widget;
    config: RecruiterConfig & TopupConfig & ProductGroupConfig;
    diagnostics: Diagnostics | null;
    pipelines: Pipeline[];
    pipelineStatuses: PipelineStatus[];
    leadFields: LeadField[];
    links: {
        dashboard: string; amo_accounts: string; oauth: string; api_logs: string; logout: string;
        widgets: string; save: string; crm_fields: string;
        current_account: {
            dashboard: string; show: string; users: string; roles: string; leads: string;
            pipelines: string; crm_audit: string; integrations: string; widgets: string;
        };
    };
};

const selectClass = 'mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10';
const labelTextClass = 'text-theme-sm font-medium text-gray-700';
const hintClass = 'mt-1 block text-theme-xs text-gray-500';

function PipelineSelect({ pipelines, value }: { pipelines: Pipeline[]; value: number | string | null | undefined }) {
    return (
        <label className="block">
            <span className={labelTextClass}>Воронка для отчёта</span>
            <select className={selectClass} defaultValue={value || ''} name="pipeline_id">
                <option value="">Все воронки</option>
                {pipelines.map((p) => (
                    <option key={p.id} value={p.id}>
                        {p.name} · ID {p.id}{p.is_archive ? ' · архивная' : ''}
                    </option>
                ))}
            </select>
        </label>
    );
}

function FieldSelect({ label, name, value, leadFields, placeholder, hint }: {
    label: string; name: string; value: number | string | null | undefined;
    leadFields: LeadField[]; placeholder: string; hint?: string;
}) {
    return (
        <label className="block">
            <span className={labelTextClass}>{label}</span>
            <select className={selectClass} defaultValue={value || ''} name={name}>
                <option value="">{placeholder}</option>
                {leadFields.map((f) => (
                    <option key={f.id} value={f.id}>
                        {f.name} · ID {f.id} · {f.field_type || 'без типа'}
                    </option>
                ))}
            </select>
            {hint && <span className={hintClass}>{hint}</span>}
        </label>
    );
}

function RecruiterSettingsForm({ config, pipelines, pipelineStatuses, leadFields }: {
    config: RecruiterConfig;
    pipelines: Pipeline[];
    pipelineStatuses: PipelineStatus[];
    leadFields: LeadField[];
}) {
    return (
        <>
            <PipelineSelect pipelines={pipelines} value={config.pipeline_id} />

            <FieldSelect
                label='Поле сделки с рекрутером'
                name="recruiter_field_id"
                value={config.recruiter_field_id}
                leadFields={leadFields}
                placeholder='Авто: поле "Рекрутер"'
                hint="Если поле не видно, обновите CRM-аудит структуры и проверьте список полей."
            />

            <FieldSelect
                label='Поле сделки с менеджером'
                name="manager_field_id"
                value={config.manager_field_id}
                leadFields={leadFields}
                placeholder='Авто: поле "Менеджер"'
                hint='Колонка "Передано менеджеру" считает сделки, где заполнены и рекрутер, и менеджер.'
            />

            <div className="grid gap-4 md:grid-cols-2">
                <FieldSelect
                    label='Поле сделки с командой'
                    name="team_field_id"
                    value={config.team_field_id}
                    leadFields={leadFields}
                    placeholder='Авто: поле "Команда"'
                />
                <FieldSelect
                    label='Поле сделки с городом'
                    name="city_field_id"
                    value={config.city_field_id}
                    leadFields={leadFields}
                    placeholder='Авто: поле "Город"'
                />
            </div>

            <FieldSelect
                label='Поле сделки с источником'
                name="source_field_id"
                value={config.source_field_id}
                leadFields={leadFields}
                placeholder='Авто: поле “Источник”'
                hint="Значения этого поля будут показаны отдельными колонками в отчёте по командам и городам."
            />

            <label className="block">
                <span className={labelTextClass}>Этап «Встал в график» (успешная реализация)</span>
                <select className={selectClass} defaultValue={config.success_status_id || ''} name="success_status_id">
                    <option value="">Не выбрано (отчёт отключён)</option>
                    {pipelineStatuses.map((s) => (
                        <option key={s.id} value={s.id}>{s.name} · ID {s.id}</option>
                    ))}
                </select>
                <span className={hintClass}>
                    Выберите этап воронки, соответствующий "Встал в график". Отчёт покажет сделки с рекрутером и менеджером, достигшие этого этапа.
                </span>
            </label>
        </>
    );
}

function TopupSettingsForm({ config, pipelines, leadFields }: {
    config: TopupConfig;
    pipelines: Pipeline[];
    leadFields: LeadField[];
}) {
    return (
        <>
            <PipelineSelect pipelines={pipelines} value={config.pipeline_id} />

            <FieldSelect
                label="Поле «Менеджер»"
                name="manager_field_id"
                value={config.manager_field_id}
                leadFields={leadFields}
                placeholder="Выберите поле..."
                hint="По этому полю будет группировка сделок в отчёте."
            />

            <FieldSelect
                label="Поле «Сумма предоплаты»"
                name="prepayment_field_id"
                value={config.prepayment_field_id}
                leadFields={leadFields}
                placeholder="Выберите поле..."
                hint="Числовое поле. Доплата = Бюджет сделки − Сумма предоплаты."
            />

            <FieldSelect
                label="Поле «Месяц предполагаемой доплаты»"
                name="topup_date_field_id"
                value={config.topup_date_field_id}
                leadFields={leadFields}
                placeholder="Выберите поле..."
                hint="По значению этого поля фильтруется период в отчёте."
            />
        </>
    );
}

function ProductGroupSettingsForm({ config, pipelines, leadFields }: {
    config: ProductGroupConfig;
    pipelines: Pipeline[];
    leadFields: LeadField[];
}) {
    return (
        <>
            <PipelineSelect pipelines={pipelines} value={config.pipeline_id} />

            <FieldSelect
                label='Поле «Товарная группа»'
                name="product_group_field_id"
                value={config.product_group_field_id}
                leadFields={leadFields}
                placeholder="Выберите поле..."
                hint="Поле-список (в т.ч. с множественным выбором). По его значениям строится разбивка бюджета активных сделок."
            />
        </>
    );
}

function DiagnosticsSection({ diagnostics }: { diagnostics: Diagnostics }) {
    const metrics = [
        ['Поле найдено', diagnostics.field_found ? 'да' : 'нет'],
        ['ID поля', diagnostics.field_id || '-'],
        ['Тип поля', diagnostics.field_type || '-'],
        ['Значений списка', diagnostics.field_enum_count],
        ['Всего сделок в базе', diagnostics.synced_leads_total],
        ['Сделок в воронке', diagnostics.pipeline_leads_total],
        ['Сделок с полем', diagnostics.leads_with_field],
        ['Сделок распознано', diagnostics.assigned_leads],
        ['Первая сделка', diagnostics.pipeline_first_lead_created_at || '-'],
        ['Последняя сделка', diagnostics.pipeline_last_lead_created_at || '-'],
    ];

    return (
        <section className="mt-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-semibold text-gray-900">Диагностика отчёта по рекрутерам</h2>
                    <p className="mt-1 text-theme-sm text-gray-500">
                        Проверяет локально сохранённые сделки, выбранную воронку и поле "{diagnostics.field_name}".
                    </p>
                </div>
                <div className="rounded bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">Без запросов к amoCRM</div>
            </div>

            <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                {metrics.map(([label, value]) => (
                    <div className="rounded-xl border border-gray-100 bg-gray-50 p-3" key={String(label)}>
                        <div className="text-theme-xs text-gray-500">{label}</div>
                        <div className="mt-1 break-words text-sm font-semibold text-gray-900">{value}</div>
                    </div>
                ))}
            </div>

            <div className="mt-5 overflow-x-auto">
                <h3 className="mb-2 text-sm font-semibold text-gray-800">Найденные значения поля</h3>
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th className="px-3 py-2">Enum ID</th>
                            <th className="px-3 py-2">Значение</th>
                            <th className="px-3 py-2">Сделок</th>
                            <th className="px-3 py-2">Распознано</th>
                        </tr>
                    </thead>
                    <tbody>
                        {diagnostics.field_values.length > 0 ? diagnostics.field_values.map((v) => (
                            <tr className="border-t border-gray-100" key={`${v.enum_id || 'text'}-${v.value}`}>
                                <td className="px-3 py-2 tabular-nums">{v.enum_id || '-'}</td>
                                <td className="px-3 py-2 font-medium text-gray-900">{v.value || '-'}</td>
                                <td className="px-3 py-2 tabular-nums">{v.count}</td>
                                <td className="px-3 py-2">{v.matched_enum ? 'да' : 'нет'}</td>
                            </tr>
                        )) : (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>Значения не найдены.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="mt-5 overflow-x-auto">
                <h3 className="mb-2 text-sm font-semibold text-gray-800">Примеры сделок с этим полем</h3>
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
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
                            <tr className="border-t border-gray-100" key={lead.id}>
                                <td className="px-3 py-2 tabular-nums">{lead.id}</td>
                                <td className="px-3 py-2 font-medium text-gray-900">{lead.name || '-'}</td>
                                <td className="px-3 py-2 tabular-nums">{lead.pipeline_id || '-'}</td>
                                <td className="px-3 py-2 tabular-nums">{lead.status_id || '-'}</td>
                                <td className="px-3 py-2 whitespace-nowrap">{lead.created_at || '-'}</td>
                                <td className="px-3 py-2">{lead.field_values.map((v) => `${v.enum_id || '-'}: ${v.value || '-'}`).join('; ')}</td>
                            </tr>
                        )) : (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>Нет примеров сделок с выбранным полем.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export default function WidgetSettings({ account, widget, config, diagnostics, pipelines, pipelineStatuses, leadFields, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const isTopup = widget.code === 'manager_topup_dashboard';
    const isProductGroup = widget.code === 'product_group_dashboard';

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
                    <h1 className="text-2xl font-semibold">Настройки отчёта: {widget.name}</h1>
                    <div className="text-theme-sm text-gray-500">{account.name} · {account.base_domain}</div>
                </div>
                <a className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300" href={links.widgets}>
                    Назад к блокам
                </a>
            </div>

            <section className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <form action={links.save} className="grid gap-4" method="post">
                    <input name="_token" type="hidden" value={csrf} />

                    {isTopup ? (
                        <TopupSettingsForm config={config} pipelines={pipelines} leadFields={leadFields} />
                    ) : isProductGroup ? (
                        <ProductGroupSettingsForm config={config} pipelines={pipelines} leadFields={leadFields} />
                    ) : (
                        <RecruiterSettingsForm config={config} pipelines={pipelines} pipelineStatuses={pipelineStatuses} leadFields={leadFields} />
                    )}

                    <div className="flex flex-wrap items-center gap-3 pt-1">
                        <button className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" type="submit">
                            Сохранить настройки
                        </button>
                        <a className="text-sm text-brand-600 hover:text-brand-700" href={links.crm_fields}>
                            Открыть список полей сделок и контактов
                        </a>
                    </div>
                </form>
            </section>

            {!isTopup && diagnostics && <DiagnosticsSection diagnostics={diagnostics} />}
        </AuthenticatedLayout>
    );
}
