import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Status = {
    id?: number;
    name?: string;
    sort?: number;
    color?: string;
    type?: string;
};

type Props = {
    account: Account;
    pipelineId: number;
    pipeline: {
        id?: number;
        name?: string;
    };
    statuses: Status[];
    error: string | null;
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        submit: string;
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

type PageProps = {
    errors?: Record<string, string>;
};

export default function PipelineClone({ account, pipelineId, pipeline, statuses, error, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const { props } = usePage<PageProps>();
    const nameError = props.errors?.name;
    const pipelineName = pipeline.name || `воронка ${pipelineId}`;

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Воронки', href: links.current_account.pipelines },
                { label: 'Клонировать' },
            ]}
            links={links}
        >
            <div className="mb-6">
                <a className="text-sm text-blue-700 hover:text-blue-900" href={links.current_account.pipelines}>← Все воронки</a>
                <h1 className="mt-2 text-2xl font-semibold">Клонировать воронку</h1>
                <div className="text-sm text-slate-500">{account.name} · {account.base_domain}</div>
            </div>

            {error ? <div className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Не удалось загрузить исходную воронку: {error}</div> : null}

            <form action={links.submit} className="space-y-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" method="post">
                <input name="_token" type="hidden" value={csrf} />

                <div className="grid gap-4 md:grid-cols-2">
                    <div>
                        <div className="text-sm text-slate-500">Исходная воронка</div>
                        <div className="mt-1 text-lg font-semibold">{pipelineName}</div>
                        <div className="mt-1 text-sm text-slate-500">ID {pipeline.id || pipelineId} · этапов: {statuses.length}</div>
                    </div>
                    <label className="block text-sm">
                        <span>Название новой воронки</span>
                        <input className="mt-1 w-full rounded border-slate-300" defaultValue={`Копия: ${pipelineName}`} name="name" required />
                        {nameError ? <div className="mt-1 text-xs text-red-700">{nameError}</div> : null}
                    </label>
                </div>

                <div>
                    <h2 className="mb-3 font-semibold">Этапы, которые будут скопированы</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-slate-500">
                                <tr><th className="py-2">ID</th><th>Название</th><th>Сортировка</th><th>Цвет</th><th>Тип</th></tr>
                            </thead>
                            <tbody>
                                {statuses.length > 0 ? statuses.map((status, index) => (
                                    <tr className="border-t border-slate-100" key={status.id || index}>
                                        <td className="py-2">{status.id || '-'}</td>
                                        <td className="font-medium">{status.name || '-'}</td>
                                        <td>{status.sort || '-'}</td>
                                        <td><span className="inline-block h-5 w-10 rounded border border-slate-200" style={{ background: status.color || '#94a3b8' }} /></td>
                                        <td>{status.type || 'regular'}</td>
                                    </tr>
                                )) : <tr><td className="py-4 text-slate-500" colSpan={5}>Этапы не загружены.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Будет создана новая воронка с теми же обычными этапами, цветами, сортировкой и настройкой неразобранного. Системный этап "Неразобранное" не передается как обычный этап, его создает amoCRM. Главной новая воронка не назначается автоматически.
                </div>

                <div className="flex flex-wrap gap-3">
                    <button className="rounded bg-blue-700 px-4 py-2 text-white hover:bg-blue-800" type="submit">Создать копию в amoCRM</button>
                    <a className="rounded border border-slate-300 px-4 py-2" href={links.current_account.pipelines}>Отмена</a>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
