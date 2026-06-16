import { router, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, Plus, Trash2, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout';

type Account = {
    id: number;
    name: string;
    base_domain: string;
};

type Webhook = {
    destination: string;
    settings: string[];
    created_at: number | null;
};

type AvailableEvents = Record<string, Record<string, string>>;

type Links = {
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
        catalogs: string;
        crm_audit: string;
        integrations: string;
        widgets: string;
        webhooks: string;
    };
};

type Props = {
    account: Account;
    webhooks: Webhook[];
    incomingUrl: string;
    availableEvents: AvailableEvents;
    fetchError: string | null;
    links: Links;
};

function CopyButton({ value }: { value: string }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        await navigator.clipboard.writeText(value);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <button
            type="button"
            onClick={copy}
            className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 shadow-theme-xs hover:bg-gray-50 shrink-0"
        >
            {copied ? <Check size={13} className="text-green-500" /> : <Copy size={13} />}
            {copied ? 'Скопировано' : 'Копировать'}
        </button>
    );
}

function EventsTag({ events, available }: { events: string[]; available: AvailableEvents }) {
    const allLabels: Record<string, string> = Object.values(available).reduce(
        (acc, group) => ({ ...acc, ...group }),
        {},
    );

    if (events.length === 0) {
        return <span className="text-gray-400 text-xs">Нет событий</span>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {events.map((ev) => (
                <span
                    key={ev}
                    className="inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700"
                >
                    {allLabels[ev] ?? ev}
                </span>
            ))}
        </div>
    );
}

function RegisterForm({
    incomingUrl,
    availableEvents,
    storeUrl,
}: {
    incomingUrl: string;
    availableEvents: AvailableEvents;
    storeUrl: string;
}) {
    const form = useForm({
        destination: incomingUrl,
        events: [] as string[],
    });

    const toggleEvent = (key: string) => {
        form.setData('events', form.data.events.includes(key)
            ? form.data.events.filter((e) => e !== key)
            : [...form.data.events, key],
        );
    };

    const selectGroup = (groupKeys: string[]) => {
        const allSelected = groupKeys.every((k) => form.data.events.includes(k));
        if (allSelected) {
            form.setData('events', form.data.events.filter((e) => !groupKeys.includes(e)));
        } else {
            const merged = [...new Set([...form.data.events, ...groupKeys])];
            form.setData('events', merged);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(storeUrl);
    };

    return (
        <form onSubmit={submit}>
            <div className="mb-5">
                <label className="mb-1.5 block text-theme-sm font-medium text-gray-700">
                    URL назначения
                </label>
                <input
                    type="url"
                    value={form.data.destination}
                    onChange={(e) => form.setData('destination', e.target.value)}
                    className="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-theme-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-brand-500/20"
                    placeholder="https://..."
                    required
                />
                {form.errors.destination && (
                    <p className="mt-1 text-xs text-red-500">{form.errors.destination}</p>
                )}
            </div>

            <div className="mb-5 space-y-4">
                <label className="block text-theme-sm font-medium text-gray-700">
                    Подписаться на события
                </label>
                {form.errors.events && (
                    <p className="text-xs text-red-500">{form.errors.events}</p>
                )}
                {Object.entries(availableEvents).map(([groupName, events]) => {
                    const groupKeys = Object.keys(events);
                    const allSelected = groupKeys.every((k) => form.data.events.includes(k));
                    const someSelected = groupKeys.some((k) => form.data.events.includes(k));

                    return (
                        <div key={groupName} className="rounded-lg border border-gray-200 p-4">
                            <div className="mb-3 flex items-center justify-between">
                                <span className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {groupName}
                                </span>
                                <button
                                    type="button"
                                    onClick={() => selectGroup(groupKeys)}
                                    className="text-xs font-medium text-brand-600 hover:text-brand-700"
                                >
                                    {allSelected ? 'Снять все' : someSelected ? 'Выбрать все' : 'Выбрать все'}
                                </button>
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                {Object.entries(events).map(([key, label]) => (
                                    <label
                                        key={key}
                                        className="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 hover:bg-gray-50"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={form.data.events.includes(key)}
                                            onChange={() => toggleEvent(key)}
                                            className="size-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                        />
                                        <span className="text-theme-sm text-gray-700">{label}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>

            <button
                type="submit"
                disabled={form.processing || form.data.events.length === 0}
                className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-theme-sm font-semibold text-white shadow-theme-xs hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <Plus size={16} />
                Зарегистрировать вебхук
            </button>
        </form>
    );
}

export default function WebhooksIndex({ account, webhooks, incomingUrl, availableEvents, fetchError, links }: Props) {
    const { props } = usePage<{ flash?: { success?: string } }>();
    const flash = props.flash;
    const accountLinks = links.current_account;

    const handleDelete = (destination: string) => {
        if (!confirm(`Удалить вебхук?\n${destination}`)) return;
        router.delete(accountLinks.webhooks, { data: { destination } });
    };

    const formatDate = (ts: number | null) => {
        if (!ts) return '—';
        return new Date(ts * 1000).toLocaleDateString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    };

    return (
        <AuthenticatedLayout
            title="amo Integrator Hub"
            breadcrumbs={[
                { label: 'Dashboard', href: links.dashboard },
                { label: 'Клиенты', href: links.amo_accounts },
                { label: account.name, href: accountLinks.show },
                { label: 'Вебхуки' },
            ]}
            links={links}
        >
            {flash?.success && (
                <div className="mb-6 flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-theme-sm text-green-700">
                    <Check size={16} className="shrink-0" />
                    {flash.success}
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_360px]">
                <div className="space-y-6">
                    {/* Входящий URL */}
                    <div className="rounded-xl border border-gray-200 bg-white p-6">
                        <h2 className="mb-1 text-theme-xl font-semibold text-gray-900">Входящий URL</h2>
                        <p className="mb-4 text-theme-sm text-gray-500">
                            Этот URL нужно указать в amoCRM при создании вебхука. Система будет получать события по нему.
                        </p>
                        <div className="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <code className="min-w-0 flex-1 truncate text-xs text-gray-700">{incomingUrl}</code>
                            <CopyButton value={incomingUrl} />
                        </div>
                    </div>

                    {/* Зарегистрированные вебхуки */}
                    <div className="rounded-xl border border-gray-200 bg-white p-6">
                        <h2 className="mb-1 text-theme-xl font-semibold text-gray-900">Зарегистрированные вебхуки</h2>
                        <p className="mb-5 text-theme-sm text-gray-500">
                            Вебхуки, которые сейчас подключены к аккаунту {account.base_domain}.
                        </p>

                        {fetchError && (
                            <div className="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-theme-sm text-red-700">
                                <TriangleAlert size={16} className="mt-0.5 shrink-0" />
                                <span>Не удалось загрузить вебхуки из amoCRM: {fetchError}</span>
                            </div>
                        )}

                        {!fetchError && webhooks.length === 0 && (
                            <div className="rounded-lg border border-dashed border-gray-200 px-6 py-10 text-center">
                                <p className="text-theme-sm text-gray-400">Вебхуки ещё не зарегистрированы</p>
                            </div>
                        )}

                        {webhooks.length > 0 && (
                            <div className="divide-y divide-gray-100">
                                {webhooks.map((wh) => (
                                    <div key={wh.destination} className="py-4 first:pt-0 last:pb-0">
                                        <div className="mb-2 flex items-start justify-between gap-4">
                                            <code className="min-w-0 flex-1 break-all text-xs text-gray-700">
                                                {wh.destination}
                                            </code>
                                            <button
                                                type="button"
                                                onClick={() => handleDelete(wh.destination)}
                                                className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                            >
                                                <Trash2 size={13} />
                                                Удалить
                                            </button>
                                        </div>
                                        <div className="mb-1.5">
                                            <EventsTag events={wh.settings} available={availableEvents} />
                                        </div>
                                        {wh.created_at && (
                                            <p className="text-xs text-gray-400">
                                                Зарегистрирован {formatDate(wh.created_at)}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Форма регистрации */}
                <div className="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 className="mb-1 text-theme-xl font-semibold text-gray-900">Зарегистрировать вебхук</h2>
                    <p className="mb-5 text-theme-sm text-gray-500">
                        Выберите события, на которые amoCRM будет отправлять уведомления.
                    </p>
                    <RegisterForm
                        incomingUrl={incomingUrl}
                        availableEvents={availableEvents}
                        storeUrl={accountLinks.webhooks}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
