import type { ReactNode } from 'react';

type Props = {
    label: string;
    value: ReactNode;
};

export default function DashboardMetric({ label, value }: Props) {
    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm">
            <div className="text-theme-sm font-medium text-gray-500">{label}</div>
            <div className="mt-2 text-2xl font-semibold text-gray-900">{value}</div>
        </div>
    );
}
