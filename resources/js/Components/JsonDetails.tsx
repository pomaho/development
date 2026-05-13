type Props = {
    data: unknown;
    label?: string;
};

export default function JsonDetails({ data, label = 'JSON' }: Props) {
    return (
        <details>
            <summary className="cursor-pointer text-blue-700">{label}</summary>
            <pre className="mt-2 max-w-md overflow-auto rounded bg-slate-950 p-3 text-[11px] text-slate-50">
                {JSON.stringify(data, null, 2)}
            </pre>
        </details>
    );
}
