type Props = {
    action: string;
    label: string;
    method?: 'post' | 'delete';
    danger?: boolean;
    buttonClassName?: string;
};

export default function PlainActionForm({ action, label, method = 'post', danger = false, buttonClassName }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const defaultClassName = danger
        ? 'inline-flex h-9 items-center justify-center rounded-lg border border-red-200 bg-white px-3 text-theme-sm font-medium text-red-600 shadow-theme-xs hover:bg-red-50'
        : 'inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500';

    return (
        <form action={action} method="post">
            <input name="_token" type="hidden" value={csrf} />
            {method !== 'post' ? <input name="_method" type="hidden" value={method} /> : null}
            <button className={buttonClassName || defaultClassName} type="submit">
                {label}
            </button>
        </form>
    );
}
