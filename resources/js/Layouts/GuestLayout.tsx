import type { ReactNode } from 'react';

type Props = {
    title: string;
    children: ReactNode;
};

export default function GuestLayout({ title, children }: Props) {
    return (
        <div className="min-h-screen bg-slate-50 px-4 py-12 text-slate-900">
            <div className="mx-auto mb-8 max-w-md text-center">
                <div className="text-lg font-semibold text-slate-950">amo Integrator Hub</div>
                <h1 className="mt-6 text-2xl font-semibold">{title}</h1>
            </div>
            {children}
        </div>
    );
}
