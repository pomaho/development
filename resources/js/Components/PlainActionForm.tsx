type Props = {
    action: string;
    label: string;
    method?: 'post' | 'delete';
    danger?: boolean;
    buttonClassName?: string;
};

export default function PlainActionForm({ action, label, method = 'post', danger = false, buttonClassName }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const defaultClassName = danger ? 'text-red-700 hover:text-red-900' : 'text-blue-700 hover:text-blue-900';

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
