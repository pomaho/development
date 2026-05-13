import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';
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
    const paginationLabel = (label: string) => label
        .replace('&laquo; Previous', 'Назад')
        .replace('Next &raquo;', 'Вперед');

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
                <h1 className="text-2xl font-semibold">amoCRM аккаунты</h1>
                {can.create ? (
                    <div className="flex flex-wrap gap-2">
                        <a className="rounded border border-slate-300 px-4 py-2 text-sm hover:border-blue-400" href={links.export}>
                            Экспорт в Excel
                        </a>
                        <a className="rounded bg-blue-700 px-4 py-2 text-sm text-white hover:bg-blue-800" href={links.install} rel="noreferrer" target="_blank">
                            Публичная ссылка установки
                        </a>
                        <a className="rounded border border-blue-700 px-4 py-2 text-sm text-blue-700 hover:bg-blue-50" href={links.oauth}>
                            История OAuth
                        </a>
                    </div>
                ) : null}
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-2">Название</th>
                                <th>Домен</th>
                                <th>Auth</th>
                                <th>Активен</th>
                                <th>Статус</th>
                                <th>Sync</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            {accounts.data.length > 0 ? accounts.data.map((account) => (
                                <tr className="border-t border-slate-100" key={account.id}>
                                    <td className="py-2 font-medium">{account.name}</td>
                                    <td>{account.base_domain}</td>
                                    <td>{account.auth_type || '-'}</td>
                                    <td>{account.is_active ? 'да' : 'нет'}</td>
                                    <td>{account.auth_status || '-'}</td>
                                    <td>{account.last_successful_sync_at || '-'}</td>
                                    <td className="flex flex-wrap gap-2 py-2">
                                        <a className="text-blue-700 hover:text-blue-900" href={account.links.show}>открыть</a>
                                        {account.can.sync ? (
                                            <>
                                                <PlainActionForm action={account.links.test} label="проверить" />
                                                <PlainActionForm action={account.links.sync} label="синхр." />
                                            </>
                                        ) : null}
                                        {account.can.update ? <a className="text-blue-700 hover:text-blue-900" href={account.links.edit}>ред.</a> : null}
                                        {account.can.delete ? <PlainActionForm action={account.links.destroy} danger label="удалить" method="delete" /> : null}
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td className="py-4 text-slate-500" colSpan={7}>Подключений пока нет.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {accounts.links.length > 3 ? (
                    <div className="mt-4 flex flex-wrap gap-2 text-sm">
                        {accounts.links.map((link, index) => link.url ? (
                            <a
                                className={link.active
                                    ? 'rounded bg-blue-700 px-3 py-1 text-white'
                                    : 'rounded border border-slate-300 px-3 py-1 text-slate-700 hover:border-blue-400'}
                                href={link.url}
                                key={`${link.label}-${index}`}
                            >
                                {paginationLabel(link.label)}
                            </a>
                        ) : (
                            <span
                                className="rounded border border-slate-200 px-3 py-1 text-slate-400"
                                key={`${link.label}-${index}`}
                            >
                                {paginationLabel(link.label)}
                            </span>
                        ))}
                    </div>
                ) : null}
            </div>
        </AuthenticatedLayout>
    );
}
