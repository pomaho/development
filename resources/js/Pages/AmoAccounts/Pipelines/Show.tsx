import DashboardMetric from '../../../Components/DashboardMetric';
import JsonDetails from '../../../Components/JsonDetails';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type AnyRecord = Record<string, any>;

type Props = {
    account: Account;
    pipelineId: number;
    details: {
        pipeline?: AnyRecord;
        statuses?: AnyRecord[];
        stage_rows?: AnyRecord[];
        sources?: AnyRecord[];
        all_sources?: AnyRecord[];
        widgets?: AnyRecord[];
        website_buttons?: AnyRecord[];
        loss_reasons?: AnyRecord[];
        errors?: Record<string, string>;
        limitations?: string[];
    };
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
        clone: string;
        create: string;
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

function EmptyRow({ colSpan, children }: { colSpan: number; children: string }) {
    return (
        <tr>
            <td className="px-5 py-6 text-gray-500" colSpan={colSpan}>{children}</td>
        </tr>
    );
}

export default function PipelineShow({ account, pipelineId, details, error, can, links }: Props) {
    const pipeline = details.pipeline || {};
    const statuses = details.statuses || [];
    const stageRows = details.stage_rows || [];
    const sources = details.sources || [];
    const allSources = details.all_sources || [];
    const widgets = details.widgets || [];
    const websiteButtons = details.website_buttons || [];
    const lossReasons = details.loss_reasons || [];
    const detailErrors = details.errors || {};
    const limitations = details.limitations || [];
    const actionLinkClass = 'inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500';

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Воронки', href: links.current_account.pipelines },
                { label: pipeline.name || `Воронка ${pipelineId}` },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <a className="text-theme-sm font-medium text-brand-600 hover:text-brand-700" href={links.current_account.pipelines}>← Все воронки</a>
                    <h1 className="mt-2 text-2xl font-semibold text-gray-900">{pipeline.name || `Воронка ${pipelineId}`}</h1>
                    <div className="mt-1 text-theme-sm text-gray-500">{account.name} · {account.base_domain}</div>
                </div>
                {can.sync ? (
                    <div className="flex flex-wrap gap-2">
                        <a className={actionLinkClass} href={links.clone}>Клонировать</a>
                        <a className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" href={links.create}>Создать воронку</a>
                    </div>
                ) : null}
            </div>

            {error ? <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-theme-sm text-amber-800">Не удалось загрузить настройки воронки: {error}</div> : null}

            {Object.keys(detailErrors).length > 0 ? (
                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-theme-sm text-amber-800">
                    <div className="font-medium">Часть разделов amoCRM не отдала данные.</div>
                    <div className="mt-1 text-theme-xs text-amber-700">Страница продолжает показывать все, что удалось получить.</div>
                    <ul className="mt-2 list-disc space-y-1 pl-5">
                        {Object.entries(detailErrors).map(([section, message]) => (
                            <li key={section}><span className="font-medium">{section}:</span> {message}</li>
                        ))}
                    </ul>
                </div>
            ) : null}

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <DashboardMetric label="ID воронки" value={pipeline.id || pipelineId} />
                <DashboardMetric label="Этапов" value={statuses.length} />
                <DashboardMetric label="Источников" value={sources.length} />
                <DashboardMetric label="Виджетов" value={widgets.length} />
                <DashboardMetric label="Причин отказа" value={lossReasons.length} />
            </div>

            <section className="mb-6 overflow-x-auto rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm">
                <div className="mb-3 flex items-center justify-between gap-3">
                    <h2 className="text-lg font-semibold text-gray-900">Схема этапов</h2>
                    <div className="text-theme-sm text-gray-500">
                        Главная: {pipeline.is_main ? 'да' : 'нет'} · Неразобранное: {pipeline.is_unsorted_on ? 'включено' : 'выключено'} · Архив: {pipeline.is_archive ? 'да' : 'нет'}
                    </div>
                </div>
                <div className="flex min-w-max gap-3">
                    {statuses.length > 0 ? statuses.map((status, index) => (
                        <div className="w-56 rounded-xl border border-gray-200 bg-gray-50" key={status.id || `${status.name}-${index}`} style={{ borderTop: `5px solid ${status.color || '#94a3b8'}` }}>
                            <div className="p-3">
                                <div className="font-medium text-gray-900">{status.name || '-'}</div>
                                <div className="mt-2 text-theme-xs text-gray-500">ID {status.id || '-'} · sort {status.sort || '-'}</div>
                                <div className="mt-1 text-theme-xs text-gray-500">type {status.type || 'regular'}</div>
                            </div>
                        </div>
                    )) : <div className="text-theme-sm text-gray-500">Этапы не загружены.</div>}
                </div>
            </section>

            <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 className="px-5 pt-5 text-lg font-semibold text-gray-900">Настройки этапов</h2>
                    <div className="px-5 pt-5 text-theme-sm text-gray-500">Таблица собрана по данным pipeline statuses, descriptions, sources и required_statuses полей сделок.</div>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-theme-sm">
                        <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                            <tr>{['Порядок', 'Цвет', 'Этап', 'ID', 'Тип', 'Описание', 'Обязательные поля', 'Источники', 'JSON'].map((heading) => <th className="px-5 py-3" key={heading}>{heading}</th>)}</tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {stageRows.length > 0 ? stageRows.map((row, index) => {
                                const status = row.status || {};
                                const requiredFields = row.required_fields || [];
                                const rowSources = row.sources || [];

                                return (
                                    <tr className="align-top" key={status.id || index}>
                                        <td className="px-5 py-3 text-gray-700">{status.sort || '-'}</td>
                                        <td className="px-5 py-3"><span className="inline-block h-5 w-10 rounded border border-gray-200" style={{ background: status.color || '#94a3b8' }} /></td>
                                        <td className="px-5 py-3 font-medium text-gray-900">{status.name || '-'}</td>
                                        <td className="px-5 py-3 text-gray-600">{status.id || '-'}</td>
                                        <td className="px-5 py-3 text-gray-600">{status.type || 'regular'}</td>
                                        <td className="max-w-xs px-5 py-3 text-gray-600">{row.description || '-'}</td>
                                        <td className="max-w-sm px-5 py-3">
                                            {requiredFields.length > 0 ? requiredFields.map((field: AnyRecord, fieldIndex: number) => (
                                                <div className="mb-1 rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs text-gray-700" key={field.id || fieldIndex}>
                                                    {field.name || `Поле ${field.id}`} <span className="text-gray-500">({field.type || '-'})</span>
                                                </div>
                                            )) : <span className="text-gray-400">-</span>}
                                        </td>
                                        <td className="max-w-sm px-5 py-3">
                                            {rowSources.length > 0 ? rowSources.map((source: AnyRecord, sourceIndex: number) => (
                                                <div className="mb-1 rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs text-gray-700" key={source.id || sourceIndex}>
                                                    {source.name || source.type || `Источник ${source.id}`}
                                                </div>
                                            )) : <span className="text-gray-400">-</span>}
                                        </td>
                                        <td className="px-5 py-3"><JsonDetails data={status} /></td>
                                    </tr>
                                );
                            }) : <EmptyRow colSpan={9}>Настройки этапов не загружены.</EmptyRow>}
                        </tbody>
                    </table>
                </div>
            </section>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <DataTable title="Источники воронки" empty={`Привязанные к воронке источники не найдены. Всего источников в аккаунте: ${allSources.length}.`} rows={sources} columns={['ID', 'Название', 'Тип', 'Этап']} pick={(source) => [source.id || '-', source.name || '-', source.type || '-', source.status_id || source.default_status_id || '-']} />
                <DataTable title="Причины отказа" empty="Причины отказа не загружены." rows={lossReasons} columns={['ID', 'Название', 'Порядок']} pick={(reason) => [reason.id || '-', reason.name || '-', reason.sort || '-']} />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <DataTable title="Виджеты аккаунта" empty="Виджеты не загружены или endpoint недоступен." rows={widgets} columns={['Код', 'Название', 'Активен']} pick={(widget) => [widget.code || widget.id || '-', widget.name || '-', (widget.is_active || widget.is_enabled) ? 'да' : 'нет']} />
                <DataTable title="Кнопки и CRM Plugin" empty="Привязанные к воронке кнопки не найдены." rows={websiteButtons} columns={['ID', 'Название', 'Этап']} pick={(button) => [button.id || '-', button.name || button.button_text || '-', button.status_id || button.default_status_id || '-']} />
            </div>

            {limitations.length > 0 ? (
                <div className="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm">
                    <h2 className="text-lg font-semibold text-gray-900">Что amoCRM не отдает напрямую</h2>
                    <ul className="mt-3 list-disc space-y-2 pl-5 text-theme-sm text-gray-600">
                        {limitations.map((limitation) => <li key={limitation}>{limitation}</li>)}
                    </ul>
                </div>
            ) : null}

            <div className="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm">
                <h2 className="mb-3 text-lg font-semibold text-gray-900">Raw JSON воронки</h2>
                <JsonDetails data={pipeline} label="Показать JSON" />
            </div>
        </AuthenticatedLayout>
    );
}

function DataTable({ title, rows, columns, empty, pick }: { title: string; rows: AnyRecord[]; columns: string[]; empty: string; pick: (row: AnyRecord) => Array<string | number> }) {
    return (
        <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
            <h2 className="border-b border-gray-200 px-5 py-4 text-lg font-semibold text-gray-900">{title}</h2>
            <div className="overflow-x-auto">
                <table className="w-full text-left text-theme-sm">
                    <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                        <tr>{columns.map((column) => <th className="px-5 py-3" key={column}>{column}</th>)}<th className="px-5 py-3">JSON</th></tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {rows.length > 0 ? rows.map((row, index) => (
                            <tr className="align-top" key={row.id || index}>
                                {pick(row).map((value, valueIndex) => <td className="px-5 py-3 text-gray-600" key={valueIndex}>{value}</td>)}
                                <td className="px-5 py-3"><JsonDetails data={row} /></td>
                            </tr>
                        )) : <EmptyRow colSpan={columns.length + 1}>{empty}</EmptyRow>}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
