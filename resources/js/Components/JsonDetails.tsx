type Props = {
    data: unknown;
    label?: string;
};

export default function JsonDetails({ data, label = 'JSON' }: Props) {
    return (
        <details>
            <summary className="cursor-pointer text-theme-sm font-medium text-brand-600 hover:text-brand-700">{label}</summary>
            <pre className="mt-2 max-w-md overflow-auto rounded-xl border border-gray-800 bg-gray-950 p-3 text-[11px] leading-5 text-gray-50 shadow-theme-sm">
                {JSON.stringify(data, null, 2)}
            </pre>
        </details>
    );
}
