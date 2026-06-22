import { usePage } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

const TIMEZONES = [
    { value: 'UTC', label: 'UTC (±00:00)' },
    { value: 'Europe/Kaliningrad', label: 'Europe/Kaliningrad (UTC+02:00) — Калининград' },
    { value: 'Europe/Moscow', label: 'Europe/Moscow (UTC+03:00) — Москва, Санкт-Петербург' },
    { value: 'Europe/Samara', label: 'Europe/Samara (UTC+04:00) — Самара, Ижевск' },
    { value: 'Asia/Yekaterinburg', label: 'Asia/Yekaterinburg (UTC+05:00) — Екатеринбург' },
    { value: 'Asia/Omsk', label: 'Asia/Omsk (UTC+06:00) — Омск' },
    { value: 'Asia/Krasnoyarsk', label: 'Asia/Krasnoyarsk (UTC+07:00) — Красноярск, Барнаул' },
    { value: 'Asia/Irkutsk', label: 'Asia/Irkutsk (UTC+08:00) — Иркутск' },
    { value: 'Asia/Yakutsk', label: 'Asia/Yakutsk (UTC+09:00) — Якутск' },
    { value: 'Asia/Vladivostok', label: 'Asia/Vladivostok (UTC+10:00) — Владивосток' },
    { value: 'Asia/Magadan', label: 'Asia/Magadan (UTC+11:00) — Магадан' },
    { value: 'Asia/Kamchatka', label: 'Asia/Kamchatka (UTC+12:00) — Камчатка' },
    { value: 'Europe/Minsk', label: 'Europe/Minsk (UTC+03:00) — Минск' },
    { value: 'Asia/Almaty', label: 'Asia/Almaty (UTC+05:00) — Алматы' },
    { value: 'Asia/Tashkent', label: 'Asia/Tashkent (UTC+05:00) — Ташкент' },
    { value: 'Asia/Baku', label: 'Asia/Baku (UTC+04:00) — Баку' },
    { value: 'Asia/Tbilisi', label: 'Asia/Tbilisi (UTC+04:00) — Тбилиси' },
    { value: 'Asia/Yerevan', label: 'Asia/Yerevan (UTC+04:00) — Ереван' },
];

type Account = {
    id: number;
    name: string;
    base_domain: string;
    is_active: boolean;
    notes: string | null;
    timezone: string;
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

    return message ? <div className="mt-1.5 text-theme-xs font-medium text-red-600">{message}</div> : null;
}

export default function AmoAccountEdit({ account, credential, links }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const labelClass = 'block text-theme-sm font-medium text-gray-700';
    const inputClass = 'mt-1.5 h-11 w-full rounded-lg border-gray-200 bg-white px-3 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10';
    const secretInputClass = `${inputClass} font-mono`;

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
            <div className="mb-6">
                <p className="text-theme-sm font-medium text-brand-600">Connection settings</p>
                <h1 className="mt-1 text-2xl font-semibold text-gray-900">Редактировать подключение</h1>
            </div>

            <form action={links.current_account.update} className="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm" method="post">
                <input name="_token" type="hidden" value={csrf} />
                <input name="_method" type="hidden" value="put" />

                <div className="grid gap-4 md:grid-cols-2">
                    <label className={labelClass}>
                        <span>Название</span>
                        <input className={inputClass} defaultValue={account.name} name="name" required />
                        <FieldError name="name" />
                    </label>

                    <label className={labelClass}>
                        <span>Домен amoCRM</span>
                        <input className={inputClass} defaultValue={account.base_domain} name="base_domain" placeholder="company.amocrm.ru" required />
                        <FieldError name="base_domain" />
                    </label>

                    <label className={labelClass}>
                        <span>Тип авторизации</span>
                        <select className={inputClass} defaultValue={credential.auth_type || 'long_lived_token'} name="auth_type" required>
                            <option value="long_lived_token">long_lived_token</option>
                            <option value="oauth">oauth</option>
                        </select>
                        <FieldError name="auth_type" />
                    </label>

                    <label className="flex items-end gap-2 text-theme-sm font-medium text-gray-700">
                        <input className="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20" defaultChecked={account.is_active} name="is_active" type="checkbox" value="1" />
                        <span>Активен</span>
                    </label>

                    <label className={labelClass}>
                        <span>Часовой пояс аккаунта</span>
                        <select className={inputClass} defaultValue={account.timezone} name="timezone">
                            {TIMEZONES.map((tz) => (
                                <option key={tz.value} value={tz.value}>{tz.label}</option>
                            ))}
                        </select>
                        <FieldError name="timezone" />
                        <span className="mt-1 block text-xs text-gray-400">amoCRM API не отдаёт timezone — задайте вручную для корректного отображения дат</span>
                    </label>

                    <label className={`${labelClass} md:col-span-2`}>
                        <span>Access token {credential.masked_access_token ? `(${credential.masked_access_token})` : ''}</span>
                        <input autoComplete="off" className={secretInputClass} name="access_token" type="password" />
                        <FieldError name="access_token" />
                    </label>

                    <label className={labelClass}>
                        <span>Client ID</span>
                        <input autoComplete="off" className={secretInputClass} name="client_id" type="password" />
                        <FieldError name="client_id" />
                    </label>

                    <label className={labelClass}>
                        <span>Client secret</span>
                        <input autoComplete="off" className={secretInputClass} name="client_secret" type="password" />
                        <FieldError name="client_secret" />
                    </label>

                    <label className={labelClass}>
                        <span>Redirect URI</span>
                        <input className={inputClass} defaultValue={credential.redirect_uri || ''} name="redirect_uri" />
                        <FieldError name="redirect_uri" />
                    </label>

                    <label className={labelClass}>
                        <span>Refresh token</span>
                        <input autoComplete="off" className={secretInputClass} name="refresh_token" type="password" />
                        <FieldError name="refresh_token" />
                    </label>

                    <label className={labelClass}>
                        <span>Token expires at</span>
                        <input className={inputClass} defaultValue={credential.token_expires_at || ''} name="token_expires_at" type="datetime-local" />
                        <FieldError name="token_expires_at" />
                    </label>

                    <label className={`${labelClass} md:col-span-2`}>
                        <span>Заметки</span>
                        <textarea className="mt-1.5 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-theme-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10" defaultValue={account.notes || ''} name="notes" rows={4} />
                        <FieldError name="notes" />
                    </label>
                </div>

                <div className="mt-6 flex gap-3">
                    <button className="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600" type="submit">Сохранить</button>
                    <a className="inline-flex h-10 items-center rounded-lg border border-gray-200 bg-white px-4 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500" href={links.current_account.show}>Отмена</a>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
