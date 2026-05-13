import type { ReactNode } from 'react';

type Props = {
    label: string;
    value: ReactNode;
};

export default function DashboardMetric({ label, value }: Props) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div className="text-sm text-slate-500">{label}</div>
            <div className="mt-2 text-2xl font-semibold text-slate-950">{value}</div>
        </div>
    );
}
