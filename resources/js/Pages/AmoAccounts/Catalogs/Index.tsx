import { useState } from 'react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Catalog = {
    id: number | null;
    name: string;
    type: string;
    sort: number | null;
    can_add_elements: boolean;
    can_show_in_cards: boolean;
    can_link_multiple: boolean;
};

type EnumField = {
    id: number | null;
    name: string;
    type: string | null;
    entity_type: string | null;
    sort: number | null;
    enums: Array<{
        id: number | null;
        value: string;
        sort: number | null;
    }>;
};

type Links = {
    dashboard: string;
    amo_accounts: string;
    oauth: string;
    api_logs: string;
    logout: string;
    store_catalog: string;
    store_elements: string;
    compose_elements_preview: string;
    compose_elements_apply: string;
    store_chained_list_field: string;
    update_enum_field: string;
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

type Props = {
    account: Account;
    catalogs: Catalog[];
    enumFields: EnumField[];
    composePreview: {
        rows: Array<{
            child_id: number;
            old_name: string;
            parent_id: number | null;
            parent_name: string | null;
            new_name: string | null;
            status: string;
            message: string;
        }>;
        total: number;
        ready: number;
        unchanged: number;
        skipped: number;
        updated?: number;
    } | null;
    composeForm: {
        parent_catalog_id: string;
        child_catalog_id: string;
        template: string;
        mappings: string;
    };
    error: string | null;
    can: {
        sync: boolean;
    };
    links: Links;
};

type ChainLevel = {
    title: string;
    catalog_id: string;
};

const entityLabel = (entityType: string | null) => {
    if (entityType === 'leads') {
        return 'Сделки';
    }

    if (entityType === 'contacts') {
        return 'Контакты';
    }

    if (entityType === 'companies') {
        return 'Компании';
    }

    return entityType || '-';
};

const composeStatusClass = (status: string) => {
    if (status === 'ready') {
        return 'bg-success-50 text-success-700';
    }

    if (status === 'unchanged') {
        return 'bg-gray-100 text-gray-600';
    }

    return 'bg-warning-50 text-warning-700';
};

export default function CatalogsIndex({ account, catalogs, enumFields, composePreview, composeForm, error, can, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const accountLinks = links.current_account;
    const firstCatalog = catalogs.find((catalog) => catalog.id);
    const [levels, setLevels] = useState<ChainLevel[]>([
        { title: 'Проект', catalog_id: firstCatalog?.id ? String(firstCatalog.id) : '' },
        { title: 'Вакансия', catalog_id: '' },
    ]);

    const updateLevel = (index: number, field: keyof ChainLevel, value: string) => {
        setLevels((current) => current.map((level, levelIndex) => levelIndex === index ? { ...level, [field]: value } : level));
    };

    const addLevel = () => {
        if (levels.length < 5) {
            setLevels((current) => [...current, { title: '', catalog_id: '' }]);
        }
    };

    const removeLevel = (index: number) => {
        if (levels.length > 2) {
            setLevels((current) => current.filter((_, levelIndex) => levelIndex !== index));
        }
    };

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: accountLinks.show },
                { label: 'Списки' },
            ]}
            links={links}
        >
            <div className="mb-6">
                <h1 className="text-2xl font-semibold">Списки и связанные списки: {account.name}</h1>
                <div className="text-theme-sm text-gray-500">{account.base_domain}</div>
            </div>

            {error ? <div className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{error}</div> : null}

            <div className="grid gap-4 xl:grid-cols-3">
                <section className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                    <h2 className="font-semibold">Создать список</h2>
                    <form action={links.store_catalog} className="mt-4 space-y-3" method="post">
                        <input name="_token" type="hidden" value={csrf} />
                        <label className="block text-sm">
                            <span>Название</span>
                            <input className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" name="name" placeholder="Проекты" required />
                        </label>
                        <label className="block text-sm">
                            <span>Сортировка</span>
                            <input className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" defaultValue={10} min={1} name="sort" type="number" />
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input className="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20" defaultChecked name="can_add_elements" type="checkbox" value="1" />
                            Можно добавлять элементы
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input className="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20" defaultChecked name="can_show_in_cards" type="checkbox" value="1" />
                            Показывать в карточках
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input className="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20" defaultChecked name="can_link_multiple" type="checkbox" value="1" />
                            Можно связывать несколько элементов
                        </label>
                        <button className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-50" disabled={! can.sync} type="submit">Создать список</button>
                    </form>
                </section>

                <section className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                    <h2 className="font-semibold">Добавить элементы</h2>
                    <form action={links.store_elements} className="mt-4 space-y-3" method="post">
                        <input name="_token" type="hidden" value={csrf} />
                        <label className="block text-sm">
                            <span>Список</span>
                            <select className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" name="catalog_id" required>
                                <option value="">Выберите список</option>
                                {catalogs.map((catalog) => catalog.id ? <option key={catalog.id} value={catalog.id}>{catalog.name}</option> : null)}
                            </select>
                        </label>
                        <label className="block text-sm">
                            <span>Элементы, каждый с новой строки</span>
                            <textarea className="mt-1.5 min-h-36 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" name="elements" placeholder={'Проект А\nПроект Б'} required />
                        </label>
                        <button className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-50" disabled={! can.sync} type="submit">Добавить элементы</button>
                    </form>
                </section>

                <section className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                    <h2 className="font-semibold">Связанный список</h2>
                    <form action={links.store_chained_list_field} className="mt-4 space-y-3" method="post">
                        <input name="_token" type="hidden" value={csrf} />
                        <label className="block text-sm">
                            <span>Название поля</span>
                            <input className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" name="name" placeholder="Проект / Вакансия / Объект" required />
                        </label>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="block text-sm">
                                <span>Сущность</span>
                                <select className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" name="entity_type">
                                    <option value="leads">Сделки</option>
                                    <option value="customers">Покупатели</option>
                                </select>
                            </label>
                            <label className="block text-sm">
                                <span>Сортировка</span>
                                <input className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" defaultValue={100} min={1} name="sort" type="number" />
                            </label>
                        </div>
                        <div className="space-y-3">
                            {levels.map((level, index) => (
                                <div className="rounded-xl border border-gray-200 bg-gray-50 p-3" key={index}>
                                    <div className="mb-2 flex items-center justify-between gap-3 text-sm font-medium">
                                        <span>Уровень {index + 1}</span>
                                        <button className="text-red-700 disabled:text-gray-400" disabled={levels.length <= 2} onClick={() => removeLevel(index)} type="button">Удалить</button>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <input name={`levels[${index}][title]`} className="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20" onChange={(event) => updateLevel(index, 'title', event.target.value)} placeholder="Название уровня" required value={level.title} />
                                        <select name={`levels[${index}][catalog_id]`} className="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20" onChange={(event) => updateLevel(index, 'catalog_id', event.target.value)} required value={level.catalog_id}>
                                            <option value="">Список</option>
                                            {catalogs.map((catalog) => catalog.id ? <option key={catalog.id} value={catalog.id}>{catalog.name}</option> : null)}
                                        </select>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <button className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500 text-sm hover:border-brand-300 disabled:opacity-50" disabled={levels.length >= 5} onClick={addLevel} type="button">Добавить уровень</button>
                        <button className="ml-2 inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-50" disabled={! can.sync} type="submit">Создать поле</button>
                    </form>
                </section>
            </div>

            <section className="mt-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="font-semibold">Скомпоновать названия связанного списка</h2>
                        <div className="mt-1 text-theme-sm text-gray-500">
                            Например: проект <span className="font-medium text-gray-700">Командор</span> + подгруппа <span className="font-medium text-gray-700">Железногорск</span> = <span className="font-medium text-gray-700">Командор Железногорск</span>.
                        </div>
                    </div>
                    {composePreview ? (
                        <span className="rounded bg-brand-50 px-2 py-1 text-theme-xs text-brand-700">
                            {composePreview.ready} к изменению
                        </span>
                    ) : null}
                </div>

                <form action={links.compose_elements_preview} className="mt-4 grid gap-4 xl:grid-cols-[1fr_1fr_1.2fr]" method="post">
                    <input name="_token" type="hidden" value={csrf} />
                    <label className="block text-sm">
                        <span>Родительский список</span>
                        <select className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" defaultValue={composeForm.parent_catalog_id} name="parent_catalog_id" required>
                            <option value="">Выберите список проекта</option>
                            {catalogs.map((catalog) => catalog.id ? <option key={catalog.id} value={catalog.id}>{catalog.name}</option> : null)}
                        </select>
                    </label>
                    <label className="block text-sm">
                        <span>Дочерний список</span>
                        <select className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" defaultValue={composeForm.child_catalog_id} name="child_catalog_id" required>
                            <option value="">Выберите список подгруппы</option>
                            {catalogs.map((catalog) => catalog.id ? <option key={catalog.id} value={catalog.id}>{catalog.name}</option> : null)}
                        </select>
                    </label>
                    <label className="block text-sm">
                        <span>Шаблон нового названия</span>
                        <input className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 font-mono" defaultValue={composeForm.template || '{parent} {child}'} name="template" required />
                    </label>
                    <label className="block text-sm xl:col-span-3">
                        <span>Ручные соответствия, если связь не определяется автоматически</span>
                        <textarea
                            className="mt-1.5 min-h-24 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 font-mono text-xs"
                            name="mappings"
                            defaultValue={composeForm.mappings}
                            placeholder={'Железногорск|Командор\nОмск|Бетта'}
                        />
                    </label>
                    <div className="flex flex-wrap gap-2 xl:col-span-3">
                        <button className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500 disabled:opacity-50" disabled={! can.sync} type="submit">
                            Предпросмотр
                        </button>
                        <button className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-50" disabled={! can.sync} formAction={links.compose_elements_apply} type="submit">
                            Применить переименование
                        </button>
                    </div>
                </form>

                {composePreview ? (
                    <div className="mt-5 overflow-x-auto rounded-xl border border-gray-200">
                        <table className="w-full min-w-[900px] text-left text-theme-sm">
                            <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                                <tr>
                                    <th className="px-4 py-3">ID</th>
                                    <th className="px-4 py-3">Старое название</th>
                                    <th className="px-4 py-3">Проект</th>
                                    <th className="px-4 py-3">Новое название</th>
                                    <th className="px-4 py-3">Статус</th>
                                    <th className="px-4 py-3">Комментарий</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {composePreview.rows.map((row) => (
                                    <tr className="align-top" key={row.child_id}>
                                        <td className="px-4 py-3 text-gray-600">{row.child_id}</td>
                                        <td className="px-4 py-3 font-medium text-gray-900">{row.old_name}</td>
                                        <td className="px-4 py-3 text-gray-700">{row.parent_name || '-'}</td>
                                        <td className="px-4 py-3 text-gray-900">{row.new_name || '-'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium ${composeStatusClass(row.status)}`}>
                                                {row.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-gray-600">{row.message}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : null}
            </section>

            <section className="mt-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="font-semibold">Списки в полях сделок, контактов и компаний</h2>
                        <div className="mt-1 text-theme-sm text-gray-500">
                            Редактируются значения enum-полей. Формат строки: <span className="font-mono">id|значение</span>. Для нового значения укажите только текст.
                        </div>
                    </div>
                    <span className="rounded bg-gray-100 px-2 py-1 text-theme-xs text-gray-600">{enumFields.length} полей</span>
                </div>

                <div className="mt-4 grid gap-4 lg:grid-cols-2">
                    {enumFields.length > 0 ? enumFields.map((field) => (
                        <form action={links.update_enum_field} className="rounded-xl border border-gray-200 bg-gray-50 p-4" key={`${field.entity_type}-${field.id}`} method="post">
                            <input name="_token" type="hidden" value={csrf} />
                            <input name="entity_type" type="hidden" value={field.entity_type || ''} />
                            <input name="field_id" type="hidden" value={field.id || ''} />
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <div className="text-theme-sm text-gray-500">{entityLabel(field.entity_type)} · {field.type || '-'}</div>
                                    <input className="mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 font-medium" name="name" required defaultValue={field.name} />
                                </div>
                                <span className="rounded bg-white px-2 py-1 text-theme-xs text-gray-500">ID {field.id}</span>
                            </div>
                            <label className="mt-3 block text-sm">
                                <span>Значения</span>
                                <textarea
                                    className="mt-1.5 min-h-40 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 font-mono text-xs"
                                    name="values"
                                    defaultValue={field.enums.map((item) => item.id ? `${item.id}|${item.value}` : item.value).join('\n')}
                                    required
                                />
                            </label>
                            <button className="mt-3 inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-50" disabled={! can.sync} type="submit">Сохранить значения</button>
                        </form>
                    )) : (
                        <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 text-theme-sm text-gray-500">
                            Enum-поля не загружены. В amoCRM это обычно поля типа список, мультисписок или переключатель.
                        </div>
                    )}
                </div>
            </section>

            <section className="mt-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <h2 className="font-semibold">Текущие списки amoCRM</h2>
                <div className="mt-3 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-gray-500">
                            <tr><th className="py-2">ID</th><th>Название</th><th>Тип</th><th>Sort</th><th>Элементы</th><th>В карточках</th><th>Множественная связь</th></tr>
                        </thead>
                        <tbody>
                            {catalogs.length > 0 ? catalogs.map((catalog) => (
                                <tr className="border-t border-gray-100" key={catalog.id || catalog.name}>
                                    <td className="py-2">{catalog.id || '-'}</td>
                                    <td className="font-medium">{catalog.name}</td>
                                    <td>{catalog.type}</td>
                                    <td>{catalog.sort || '-'}</td>
                                    <td>{catalog.can_add_elements ? 'да' : 'нет'}</td>
                                    <td>{catalog.can_show_in_cards ? 'да' : 'нет'}</td>
                                    <td>{catalog.can_link_multiple ? 'да' : 'нет'}</td>
                                </tr>
                            )) : <tr><td className="py-4 text-gray-500" colSpan={7}>Списки не загружены.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
