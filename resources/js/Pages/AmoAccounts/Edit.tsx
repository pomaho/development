import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
    is_active: boolean;
    notes: string | null;
};

type Credential = {
    auth_type: 'long_lived_token' | 'oauth' | string | null;
    masked_access_token: string | null;
    redirect_uri: string | null;
    token_expires_at: string | null;
};

type Props = {
    account: Account;
    credential: Credential;
    links: {
        dashboard: string;
        amo_accounts: string;
        oauth: string;
        api_logs: string;
        logout: string;
        current_account: {
            dashboard: string;
            show: string;
            edit: string;
            update: string;
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

export default function AmoAccountEdit({ account, credential, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: links.current_account.show },
                { label: 'Редактирование' },
            ]}
            links={links}
        >
            <h1 className="mb-6 text-2xl font-semibold">Редактировать подключение</h1>
            <form action={links.current_account.update} className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" method="post">
                <input name="_token" type="hidden" value={csrf} />
                <input name="_method" type="hidden" value="put" />

                <div className="grid gap-4 md:grid-cols-2">
                    <label className="block text-sm">
                        <span>Название</span>
                        <input className="mt-1 w-full rounded border-slate-300" defaultValue={account.name} name="name" required />
                        <FieldError name="name" />
                    </label>

                    <label className="block text-sm">
                        <span>Домен amoCRM</span>
                        <input className="mt-1 w-full rounded border-slate-300" defaultValue={account.base_domain} name="base_domain" placeholder="company.amocrm.ru" required />
                        <FieldError name="base_domain" />
                    </label>

                    <label className="block text-sm">
                        <span>Тип авторизации</span>
                        <select className="mt-1 w-full rounded border-slate-300" defaultValue={credential.auth_type || 'long_lived_token'} name="auth_type" required>
                            <option value="long_lived_token">long_lived_token</option>
                            <option value="oauth">oauth</option>
                        </select>
                        <FieldError name="auth_type" />
                    </label>

                    <label className="flex items-end gap-2 text-sm">
                        <input className="rounded border-slate-300" defaultChecked={account.is_active} name="is_active" type="checkbox" value="1" />
                        <span>Активен</span>
                    </label>

                    <label className="block text-sm md:col-span-2">
                        <span>Access token {credential.masked_access_token ? `(${credential.masked_access_token})` : ''}</span>
                        <input autoComplete="off" className="mt-1 w-full rounded border-slate-300" name="access_token" type="password" />
                        <FieldError name="access_token" />
                    </label>

                    <label className="block text-sm">
                        <span>Client ID</span>
                        <input autoComplete="off" className="mt-1 w-full rounded border-slate-300" name="client_id" type="password" />
                        <FieldError name="client_id" />
                    </label>

                    <label className="block text-sm">
                        <span>Client secret</span>
                        <input autoComplete="off" className="mt-1 w-full rounded border-slate-300" name="client_secret" type="password" />
                        <FieldError name="client_secret" />
                    </label>

                    <label className="block text-sm">
                        <span>Redirect URI</span>
                        <input className="mt-1 w-full rounded border-slate-300" defaultValue={credential.redirect_uri || ''} name="redirect_uri" />
                        <FieldError name="redirect_uri" />
                    </label>

                    <label className="block text-sm">
                        <span>Refresh token</span>
                        <input autoComplete="off" className="mt-1 w-full rounded border-slate-300" name="refresh_token" type="password" />
                        <FieldError name="refresh_token" />
                    </label>

                    <label className="block text-sm">
                        <span>Token expires at</span>
                        <input className="mt-1 w-full rounded border-slate-300" defaultValue={credential.token_expires_at || ''} name="token_expires_at" type="datetime-local" />
                        <FieldError name="token_expires_at" />
                    </label>

                    <label className="block text-sm md:col-span-2">
                        <span>Заметки</span>
                        <textarea className="mt-1 w-full rounded border-slate-300" defaultValue={account.notes || ''} name="notes" rows={4} />
                        <FieldError name="notes" />
                    </label>
                </div>

                <div className="mt-6 flex gap-3">
                    <button className="rounded bg-blue-700 px-4 py-2 text-white hover:bg-blue-800" type="submit">Сохранить</button>
                    <a className="rounded border border-slate-300 px-4 py-2" href={links.current_account.show}>Отмена</a>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
