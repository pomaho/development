import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Module = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    is_enabled: boolean;
};

type Props = {
    account: Account;
    modules: Module[];
    can: {
        sync: boolean;
    };
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        current_account: {
            dashboard: string;
            show: string;
            users: string;
            roles: string;
            leads: string;
            pipelines: string;
            pipelines_create: string;
            pipelines_transfer_leads?: string;
            catalogs: string;
            crm_audit: string;
            integrations: string;
            widgets: string;
        };
    };
};

export default function AmoAccountIntegrations({ account, modules, can, links }: Props) {
    const accountLinks = links.current_account;

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: accountLinks.show },
                { label: 'Интеграции' },
            ]}
            links={links}
        >
            <div className="mb-6">
                <h1 className="text-2xl font-semibold">Интеграции: {account.name}</h1>
                <div className="text-theme-sm text-gray-500">{account.base_domain}</div>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                {modules.map((module) => (
                    <section className="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm" key={module.id}>
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="font-semibold">{module.name}</h2>
                                <div className="mt-1 text-theme-sm text-gray-500">{module.description || 'Модуль без описания.'}</div>
                                <div className="mt-3 text-theme-xs text-gray-500">code: {module.code}</div>
                            </div>
                            <span className={module.is_enabled
                                ? 'rounded bg-emerald-50 px-2 py-1 text-xs text-emerald-700'
                                : 'rounded bg-gray-100 px-2 py-1 text-theme-xs text-gray-600'}
                            >
                                {module.is_enabled ? 'enabled' : 'disabled'}
                            </span>
                        </div>

                        {module.code === 'users_audit' ? (
                            <div className="mt-4 flex flex-wrap gap-2 text-sm">
                                <a className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500 hover:border-brand-300" href={accountLinks.users}>Таблица прав</a>
                                <a className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500 hover:border-brand-300" href={accountLinks.roles}>Роли</a>
                            </div>
                        ) : null}

                        {module.code === 'pipelines_builder' ? (
                            <div className="mt-4 flex flex-wrap gap-2 text-sm">
                                <a className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500 hover:border-brand-300" href={accountLinks.pipelines}>Список воронок</a>
                                {can.sync ? <a className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500 hover:border-brand-300" href={accountLinks.pipelines_create}>Создать воронку</a> : null}
                                {can.sync && accountLinks.pipelines_transfer_leads ? <a className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500 hover:border-brand-300" href={accountLinks.pipelines_transfer_leads}>Перенос сделок</a> : null}
                            </div>
                        ) : null}

                        {module.code === 'catalogs_builder' ? (
                            <div className="mt-4 flex flex-wrap gap-2 text-sm">
                                <a className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500 hover:border-brand-300" href={accountLinks.catalogs}>Списки и связанные списки</a>
                            </div>
                        ) : null}

                        {module.code === 'crm_audit' ? (
                            <div className="mt-4 flex flex-wrap gap-2 text-sm">
                                <a className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500 hover:border-brand-300" href={accountLinks.crm_audit}>Открыть аудит</a>
                            </div>
                        ) : null}
                    </section>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
