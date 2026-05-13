type Props = {
    action: string;
    label: string;
    method?: 'post' | 'delete';
    danger?: boolean;
};

export default function PlainActionForm({ action, label, method = 'post', danger = false }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

    return (
        <form action={action} method="post">
            <input name="_token" type="hidden" value={csrf} />
            {method !== 'post' ? <input name="_method" type="hidden" value={method} /> : null}
            <button className={danger ? 'text-red-700 hover:text-red-900' : 'text-blue-700 hover:text-blue-900'} type="submit">
                {label}
            </button>
        </form>
    );
}
