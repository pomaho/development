import { usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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
    hint?: string;
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
    const [rows, setRows] = useState<Status[]>([...defaultStatuses, ...extraRows]);

    const updateRow = (index: number, field: keyof Status, value: string) => {
        setRows((currentRows) => currentRows.map((row, rowIndex) => {
            if (rowIndex !== index) {
                return row;
            }

            return {
                ...row,
                [field]: field === 'sort' ? Number(value) : value,
            };
        }));
    };

    const addRowAfter = (index: number) => {
        const nextSort = rows
            .slice(0, index + 1)
            .filter((row) => row.id === undefined)
            .length * 10 + 10;

        setRows((currentRows) => [
            ...currentRows.slice(0, index + 1),
            { name: '', sort: nextSort, color: '#98cbff', hint: '' },
            ...currentRows.slice(index + 1),
        ]);
    };

    const addRowToEnd = () => {
        const lastRegularSort = Math.max(
            0,
            ...rows
                .filter((row) => row.id === undefined)
                .map((row) => Number(row.sort) || 0),
        );

        setRows((currentRows) => [
            ...currentRows,
            { name: '', sort: lastRegularSort + 10, color: '#98cbff', hint: '' },
        ]);
    };

    const removeRow = (index: number) => {
        if (rows[index]?.id !== undefined) {
            return;
        }

        setRows((currentRows) => currentRows.filter((_, rowIndex) => rowIndex !== index));
    };

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
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="font-semibold">Этапы</h2>
                            <div className="text-sm text-slate-500">Обычные этапы можно добавлять после любой строки, удалять и снабжать подсказками для менеджеров.</div>
                        </div>
                        <button className="inline-flex items-center gap-2 rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:border-blue-400 hover:text-blue-700" onClick={addRowToEnd} type="button">
                            <Plus size={16} />
                            Добавить этап
                        </button>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-slate-500">
                                <tr>
                                    <th className="py-2">Тип</th>
                                    <th>Название</th>
                                    <th>Подсказка</th>
                                    <th>Сортировка</th>
                                    <th>Цвет</th>
                                    <th className="text-right">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((status, index) => {
                                    const isSystem = status.id !== undefined;

                                    return (
                                        <tr className="align-top border-t border-slate-100" key={`${status.id || 'custom'}-${index}`}>
                                            <td className="py-3 pr-3">
                                                {isSystem ? (
                                                    <>
                                                        <input name={`statuses[${index}][id]`} type="hidden" value={status.id} />
                                                        <span className="rounded bg-slate-100 px-2 py-1 text-xs">{status.id}</span>
                                                    </>
                                                ) : <span className="rounded bg-blue-50 px-2 py-1 text-xs text-blue-700">обычный</span>}
                                            </td>
                                            <td className="py-3 pr-3">
                                                <input className="w-full rounded border-slate-300" name={`statuses[${index}][name]`} onChange={(event) => updateRow(index, 'name', event.target.value)} placeholder={status.name ? undefined : 'Дополнительный этап'} value={status.name} />
                                                <FieldError name={`statuses.${index}.name`} />
                                            </td>
                                            <td className="py-3 pr-3">
                                                {isSystem ? <span className="inline-block rounded bg-slate-50 px-2 py-1 text-xs text-slate-400">системная</span> : (
                                                    <>
                                                        <textarea className="min-w-72 rounded border-slate-300" name={`statuses[${index}][hint]`} onChange={(event) => updateRow(index, 'hint', event.target.value)} placeholder="Подсказка для менеджера" rows={2} value={status.hint || ''} />
                                                        <FieldError name={`statuses.${index}.hint`} />
                                                    </>
                                                )}
                                            </td>
                                            <td className="py-3 pr-3">
                                                {isSystem ? <span className="inline-block rounded bg-slate-50 px-2 py-1 text-xs text-slate-400">системная</span> : (
                                                    <input className="w-28 rounded border-slate-300" max={9999} min={1} name={`statuses[${index}][sort]`} onChange={(event) => updateRow(index, 'sort', event.target.value)} type="number" value={status.sort || ''} />
                                                )}
                                            </td>
                                            <td className="py-3 pr-3">
                                                {isSystem ? <span className="inline-block rounded bg-slate-50 px-2 py-1 text-xs text-slate-400">amoCRM</span> : (
                                                    <input className="h-9 w-16 rounded border-slate-300" name={`statuses[${index}][color]`} onChange={(event) => updateRow(index, 'color', event.target.value)} type="color" value={status.color || '#98cbff'} />
                                                )}
                                            </td>
                                            <td className="py-3 text-right">
                                                <div className="inline-flex items-start gap-2">
                                                    <button aria-label="Добавить этап после этой строки" className="inline-flex h-9 w-9 items-center justify-center rounded border border-slate-300 text-slate-600 hover:border-blue-400 hover:text-blue-700" onClick={() => addRowAfter(index)} title="Добавить этап после этой строки" type="button">
                                                        <Plus size={16} />
                                                    </button>
                                                    <button aria-label="Удалить этап" className="inline-flex h-9 w-9 items-center justify-center rounded border border-slate-300 text-slate-500 hover:border-red-300 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-40" disabled={isSystem} onClick={() => removeRow(index)} title={isSystem ? 'Системный этап нельзя удалить' : 'Удалить этап'} type="button">
                                                        <Trash2 size={16} />
                                                    </button>
                                                </div>
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
