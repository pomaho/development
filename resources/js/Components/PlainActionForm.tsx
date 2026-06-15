import { router } from '@inertiajs/react';

type Props = {
    action: string;
    label: string;
    method?: 'post' | 'delete';
    danger?: boolean;
    buttonClassName?: string;
};

export default function PlainActionForm({ action, label, method = 'post', danger = false, buttonClassName }: Props) {
    const defaultClassName = danger
        ? 'inline-flex h-9 items-center justify-center rounded-lg border border-red-200 bg-white px-3 text-theme-sm font-medium text-red-600 shadow-theme-xs hover:bg-red-50'
        : 'inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500';

    const handleClick = () => {
        if (method === 'delete') {
            router.delete(action);
        } else {
            router.post(action);
        }
    };

    return (
        <button
            className={buttonClassName || defaultClassName}
            type="button"
            onClick={handleClick}
        >
            {label}
        </button>
    );
}
