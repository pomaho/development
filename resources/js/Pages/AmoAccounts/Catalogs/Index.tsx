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

type Links = {
    dashboard: string;
    amo_accounts: string;
    oauth: string;
    api_logs: string;
    logout: string;
    store_catalog: string;
    store_elements: string;
    store_chained_list_field: string;
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

export default function CatalogsIndex({ account, catalogs, error, can, links }: Props) {
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
                <div className="text-sm text-slate-500">{account.base_domain}</div>
            </div>

            {error ? <div className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{error}</div> : null}

            <div className="grid gap-4 xl:grid-cols-3">
                <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 className="font-semibold">Создать список</h2>
                    <form action={links.store_catalog} className="mt-4 space-y-3" method="post">
                        <input name="_token" type="hidden" value={csrf} />
                        <label className="block text-sm">
                            <span>Название</span>
                            <input className="mt-1 w-full rounded border-slate-300" name="name" placeholder="Проекты" required />
                        </label>
                        <label className="block text-sm">
                            <span>Сортировка</span>
                            <input className="mt-1 w-full rounded border-slate-300" defaultValue={10} min={1} name="sort" type="number" />
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input className="rounded border-slate-300" defaultChecked name="can_add_elements" type="checkbox" value="1" />
                            Можно добавлять элементы
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input className="rounded border-slate-300" defaultChecked name="can_show_in_cards" type="checkbox" value="1" />
                            Показывать в карточках
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input className="rounded border-slate-300" defaultChecked name="can_link_multiple" type="checkbox" value="1" />
                            Можно связывать несколько элементов
                        </label>
                        <button className="rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800 disabled:opacity-50" disabled={! can.sync} type="submit">Создать список</button>
                    </form>
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 className="font-semibold">Добавить элементы</h2>
                    <form action={links.store_elements} className="mt-4 space-y-3" method="post">
                        <input name="_token" type="hidden" value={csrf} />
                        <label className="block text-sm">
                            <span>Список</span>
                            <select className="mt-1 w-full rounded border-slate-300" name="catalog_id" required>
                                <option value="">Выберите список</option>
                                {catalogs.map((catalog) => catalog.id ? <option key={catalog.id} value={catalog.id}>{catalog.name}</option> : null)}
                            </select>
                        </label>
                        <label className="block text-sm">
                            <span>Элементы, каждый с новой строки</span>
                            <textarea className="mt-1 min-h-36 w-full rounded border-slate-300" name="elements" placeholder={'Проект А\nПроект Б'} required />
                        </label>
                        <button className="rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800 disabled:opacity-50" disabled={! can.sync} type="submit">Добавить элементы</button>
                    </form>
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 className="font-semibold">Связанный список</h2>
                    <form action={links.store_chained_list_field} className="mt-4 space-y-3" method="post">
                        <input name="_token" type="hidden" value={csrf} />
                        <label className="block text-sm">
                            <span>Название поля</span>
                            <input className="mt-1 w-full rounded border-slate-300" name="name" placeholder="Проект / Вакансия / Объект" required />
                        </label>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="block text-sm">
                                <span>Сущность</span>
                                <select className="mt-1 w-full rounded border-slate-300" name="entity_type">
                                    <option value="leads">Сделки</option>
                                    <option value="customers">Покупатели</option>
                                </select>
                            </label>
                            <label className="block text-sm">
                                <span>Сортировка</span>
                                <input className="mt-1 w-full rounded border-slate-300" defaultValue={100} min={1} name="sort" type="number" />
                            </label>
                        </div>
                        <div className="space-y-3">
                            {levels.map((level, index) => (
                                <div className="rounded border border-slate-200 bg-slate-50 p-3" key={index}>
                                    <div className="mb-2 flex items-center justify-between gap-3 text-sm font-medium">
                                        <span>Уровень {index + 1}</span>
                                        <button className="text-red-700 disabled:text-slate-400" disabled={levels.length <= 2} onClick={() => removeLevel(index)} type="button">Удалить</button>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <input name={`levels[${index}][title]`} className="rounded border-slate-300" onChange={(event) => updateLevel(index, 'title', event.target.value)} placeholder="Название уровня" required value={level.title} />
                                        <select name={`levels[${index}][catalog_id]`} className="rounded border-slate-300" onChange={(event) => updateLevel(index, 'catalog_id', event.target.value)} required value={level.catalog_id}>
                                            <option value="">Список</option>
                                            {catalogs.map((catalog) => catalog.id ? <option key={catalog.id} value={catalog.id}>{catalog.name}</option> : null)}
                                        </select>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <button className="rounded border border-slate-300 px-3 py-2 text-sm hover:border-blue-400 disabled:opacity-50" disabled={levels.length >= 5} onClick={addLevel} type="button">Добавить уровень</button>
                        <button className="ml-2 rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800 disabled:opacity-50" disabled={! can.sync} type="submit">Создать поле</button>
                    </form>
                </section>
            </div>

            <section className="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 className="font-semibold">Текущие списки amoCRM</h2>
                <div className="mt-3 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500">
                            <tr><th className="py-2">ID</th><th>Название</th><th>Тип</th><th>Sort</th><th>Элементы</th><th>В карточках</th><th>Множественная связь</th></tr>
                        </thead>
                        <tbody>
                            {catalogs.length > 0 ? catalogs.map((catalog) => (
                                <tr className="border-t border-slate-100" key={catalog.id || catalog.name}>
                                    <td className="py-2">{catalog.id || '-'}</td>
                                    <td className="font-medium">{catalog.name}</td>
                                    <td>{catalog.type}</td>
                                    <td>{catalog.sort || '-'}</td>
                                    <td>{catalog.can_add_elements ? 'да' : 'нет'}</td>
                                    <td>{catalog.can_show_in_cards ? 'да' : 'нет'}</td>
                                    <td>{catalog.can_link_multiple ? 'да' : 'нет'}</td>
                                </tr>
                            )) : <tr><td className="py-4 text-slate-500" colSpan={7}>Списки не загружены.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
