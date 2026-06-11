import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
import Pagination from '../../Components/Pagination';
import PlainActionForm from '../../Components/PlainActionForm';

type AccountRow = {
    id: number;
    name: string;
    base_domain: string;
    auth_type: string | null;
    is_active: boolean;
    auth_status: string | null;
    last_successful_sync_at: string | null;
    links: {
        show: string;
        edit: string;
        test: string;
        sync: string;
        destroy: string;
    };
    can: {
        sync: boolean;
        update: boolean;
        delete: boolean;
    };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    accounts: {
        data: AccountRow[];
        links: PaginationLink[];
    };
    can: {
        create: boolean;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        install: string;
        export: string;
        api_logs: string;
        logout: string;
        current_account: null;
    };
};

export default function AmoAccountsIndex({ accounts, can, links }: Props) {
    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты' },
            ]}
            links={links}
        >
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-theme-sm font-medium text-brand-600">Client connections</p>
                    <h1 className="mt-1 text-2xl font-semibold text-gray-900">amoCRM аккаунты</h1>
                </div>
                {can.create ? (
                    <div className="flex flex-wrap gap-2">
                        <a className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500" href={links.export}>
                            Экспорт в Excel
                        </a>
                        <a className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" href={links.install} rel="noreferrer" target="_blank">
                            Публичная ссылка установки
                        </a>
                        <a className="inline-flex h-10 items-center rounded-lg border border-brand-200 bg-brand-50 px-4 text-theme-sm font-medium text-brand-700 shadow-theme-xs hover:bg-brand-100" href={links.oauth}>
                            История OAuth
                        </a>
                    </div>
                ) : null}
            </div>

            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-theme-sm">
                        <thead className="bg-gray-50 text-theme-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th className="px-5 py-3">Название</th>
                                <th className="px-5 py-3">Домен</th>
                                <th className="px-5 py-3">Auth</th>
                                <th className="px-5 py-3">Активен</th>
                                <th className="px-5 py-3">Статус</th>
                                <th className="px-5 py-3">Sync</th>
                                <th className="px-5 py-3">Действия</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {accounts.data.length > 0 ? accounts.data.map((account) => (
                                <tr key={account.id}>
                                    <td className="px-5 py-3 font-medium text-gray-900">{account.name}</td>
                                    <td className="px-5 py-3 text-gray-600">{account.base_domain}</td>
                                    <td className="px-5 py-3 text-gray-600">{account.auth_type || '-'}</td>
                                    <td className="px-5 py-3">
                                        <span className={account.is_active
                                            ? 'inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-theme-xs font-medium text-emerald-700'
                                            : 'inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-500'}
                                        >
                                            {account.is_active ? 'да' : 'нет'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-gray-600">{account.auth_status || '-'}</td>
                                    <td className="px-5 py-3 text-gray-600">{account.last_successful_sync_at || '-'}</td>
                                    <td className="flex flex-wrap gap-2 px-5 py-3">
                                        <a className="inline-flex h-9 items-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500" href={account.links.show}>открыть</a>
                                        {account.can.sync ? (
                                            <>
                                                <PlainActionForm action={account.links.test} label="проверить" />
                                                <PlainActionForm action={account.links.sync} label="синхр." />
                                            </>
                                        ) : null}
                                        {account.can.update ? <a className="inline-flex h-9 items-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500" href={account.links.edit}>ред.</a> : null}
                                        {account.can.delete ? <PlainActionForm action={account.links.destroy} danger label="удалить" method="delete" /> : null}
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="px-5 py-6 text-gray-500" colSpan={7}>Подключений пока нет.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="border-t border-gray-100 px-5 pb-5">
                    <Pagination links={accounts.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
