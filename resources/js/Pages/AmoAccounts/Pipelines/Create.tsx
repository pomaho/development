import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Status = {
    id?: number;
    name: string;
    sort?: number;
    color?: string;
};

type Props = {
    account: Account;
    defaultStatuses: Status[];
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
            crm_audit: string;
            integrations: string;
            widgets: string;
        };
    };
};

type PageProps = {
    errors?: Record<string, string>;
};

function FieldError({ name }: { name: string }) {
    const { props } = usePage<PageProps>();
    const message = props.errors?.[name];

    return message ? <div className="mt-1 text-xs text-red-700">{message}</div> : null;
}

export default function PipelineCreate({ account, defaultStatuses, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const extraRows: Status[] = Array.from({ length: 4 }, (_, offset) => ({
        name: '',
        sort: (defaultStatuses.length + offset + 1) * 10,
        color: '#98cbff',
    }));
    const rows = [...defaultStatuses, ...extraRows];

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Воронки', href: links.current_account.pipelines },
                { label: 'Создать воронку' },
            ]}
            links={links}
        >
            <div className="mb-6">
                <h1 className="text-2xl font-semibold">Создать воронку: {account.name}</h1>
                <div className="text-sm text-slate-500">{account.base_domain}</div>
            </div>

            <form action={links.store} className="space-y-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" method="post">
                <input name="_token" type="hidden" value={csrf} />

                <div className="grid gap-4 md:grid-cols-2">
                    <label className="block text-sm">
                        <span>Название воронки</span>
                        <input className="mt-1 w-full rounded border-slate-300" defaultValue="Продажи B2B" name="name" required />
                        <FieldError name="name" />
                    </label>
                    <label className="block text-sm">
                        <span>Сортировка</span>
                        <input className="mt-1 w-full rounded border-slate-300" defaultValue={20} max={10000} min={1} name="sort" required type="number" />
                        <FieldError name="sort" />
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input className="rounded border-slate-300" name="is_main" type="checkbox" value="1" />
                        <span>Сделать главной</span>
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input className="rounded border-slate-300" defaultChecked name="is_unsorted_on" type="checkbox" value="1" />
                        <span>Включить неразобранное</span>
                    </label>
                </div>

                <div>
                    <h2 className="mb-3 font-semibold">Этапы</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-slate-500">
                                <tr><th className="py-2">Системный ID</th><th>Название</th><th>Сортировка</th><th>Цвет</th></tr>
                            </thead>
                            <tbody>
                                {rows.map((status, index) => {
                                    const isSystem = status.id !== undefined;

                                    return (
                                        <tr className="border-t border-slate-100" key={`${status.id || 'custom'}-${index}`}>
                                            <td className="py-2">
                                                {isSystem ? (
                                                    <>
                                                        <input name={`statuses[${index}][id]`} type="hidden" value={status.id} />
                                                        <span className="rounded bg-slate-100 px-2 py-1 text-xs">{status.id}</span>
                                                    </>
                                                ) : <span className="text-slate-400">обычный</span>}
                                            </td>
                                            <td>
                                                <input className="w-full rounded border-slate-300" defaultValue={status.name} name={`statuses[${index}][name]`} placeholder={status.name ? undefined : 'Дополнительный этап'} />
                                                <FieldError name={`statuses.${index}.name`} />
                                            </td>
                                            <td>
                                                {isSystem ? <span className="text-slate-400">системная</span> : (
                                                    <input className="w-28 rounded border-slate-300" defaultValue={status.sort} max={9999} min={1} name={`statuses[${index}][sort]`} type="number" />
                                                )}
                                            </td>
                                            <td>
                                                {isSystem ? <span className="text-slate-400">amoCRM</span> : (
                                                    <input className="h-9 w-16 rounded border-slate-300" defaultValue={status.color || '#98cbff'} name={`statuses[${index}][color]`} type="color" />
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="flex flex-wrap gap-3">
                    <button className="rounded bg-blue-700 px-4 py-2 text-white hover:bg-blue-800" type="submit">Создать в amoCRM</button>
                    <a className="rounded border border-slate-300 px-4 py-2" href={links.current_account.pipelines}>Отмена</a>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
