import { CalendarDays, Inbox } from 'lucide-react';
import type { ReactNode } from 'react';

// ─── Types ────────────────────────────────────────────────────────────────────

export type LoadState<T> =
    | { status: 'loading' }
    | { status: 'error'; message: string }
    | { status: 'loaded'; data: T };

// ─── Formatting helpers ───────────────────────────────────────────────────────

export function rub(value: number): string {
    if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1).replace('.0', '')} млн ₽`;
    if (value >= 1_000) return `${(value / 1_000).toFixed(0)} тыс ₽`;
    return `${value.toLocaleString('ru-RU')} ₽`;
}

export function rubFull(value: number): string {
    return value.toLocaleString('ru-RU') + ' ₽';
}

export function buildUrl(base: string, params: Record<string, string | number | undefined>): string {
    const url = new URL(base, window.location.origin);
    for (const [k, v] of Object.entries(params)) {
        if (v !== undefined && v !== '') url.searchParams.set(k, String(v));
    }
    return url.toString();
}

// ─── Primitive UI components ──────────────────────────────────────────────────

export function ReportSection({ eyebrow, title, description, aside, children }: {
    eyebrow: string; title: string; description?: string; aside?: ReactNode; children: ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-2xl bg-white shadow-md ring-1 ring-slate-200/60">
            <div className="grid gap-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-5 py-5 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <div className="bg-gradient-to-r from-violet-600 to-indigo-500 bg-clip-text text-xs font-bold uppercase tracking-wider text-transparent">
                        {eyebrow}
                    </div>
                    <h2 className="mt-1.5 text-lg font-bold text-gray-900">{title}</h2>
                    {description ? <p className="mt-1 text-sm text-slate-500">{description}</p> : null}
                </div>
                {aside}
            </div>
            {children}
        </section>
    );
}

export function AccentSummary({ label, value, note, tone }: {
    label: string; value: ReactNode; note?: string; tone: 'brand' | 'warning' | 'success';
}) {
    const cls = tone === 'warning'
        ? 'bg-gradient-to-br from-amber-400 to-orange-500 shadow-amber-200/60'
        : tone === 'success'
        ? 'bg-gradient-to-br from-emerald-400 to-green-600 shadow-emerald-200/60'
        : 'bg-gradient-to-br from-violet-500 to-indigo-600 shadow-violet-200/60';
    return (
        <div className={`rounded-2xl px-5 py-4 text-right text-white shadow-lg ${cls}`}>
            <div className="text-xs font-semibold uppercase tracking-wider text-white/70">{label}</div>
            <div className="mt-1 text-4xl font-extrabold tabular-nums">{value}</div>
            {note ? <div className="mt-1 text-xs text-white/70">{note}</div> : null}
        </div>
    );
}

export function SectionSkeleton({ rows }: { rows: number }) {
    return (
        <section className="overflow-hidden rounded-2xl bg-white shadow-md ring-1 ring-slate-200/60">
            <div className="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-5 py-5">
                <div className="h-3 w-24 animate-pulse rounded-full bg-slate-200" />
                <div className="mt-3 h-5 w-64 animate-pulse rounded-full bg-slate-200" />
                <div className="mt-2 h-3 w-96 animate-pulse rounded-full bg-slate-100" />
            </div>
            <div className="divide-y divide-slate-100">
                {Array.from({ length: rows }).map((_, i) => (
                    <div className="flex items-center gap-4 px-5 py-4" key={i}>
                        <div className="h-4 flex-1 animate-pulse rounded-full bg-slate-100" style={{ animationDelay: `${i * 60}ms` }} />
                        <div className="h-4 w-16 animate-pulse rounded-full bg-slate-100" style={{ animationDelay: `${i * 60 + 30}ms` }} />
                        <div className="h-4 w-24 animate-pulse rounded-full bg-slate-100" style={{ animationDelay: `${i * 60 + 60}ms` }} />
                    </div>
                ))}
            </div>
        </section>
    );
}

export function SectionError({ message }: { message: string }) {
    return (
        <section className="overflow-hidden rounded-2xl bg-white shadow-md ring-1 ring-red-200/60">
            <div className="flex flex-col items-center gap-3 px-5 py-10 text-center text-sm text-slate-400">
                <Inbox className="size-10 text-red-200" />
                Ошибка загрузки данных: {message}
            </div>
        </section>
    );
}

export function EmptyState({ children }: { children: ReactNode }) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-2xl bg-slate-50 p-10 text-center text-sm text-slate-400 ring-1 ring-slate-200">
            <Inbox className="size-10 text-slate-300" />
            {children}
        </div>
    );
}

// ─── Shared page header (dark hero + period picker) ───────────────────────────

export function WidgetHeader({ title, account, period, from, to, onFromChange, onToChange, onSubmit }: {
    title: string;
    account: { name: string; base_domain: string };
    period: { label: string; from: string; to: string };
    from: string;
    to: string;
    onFromChange: (value: string) => void;
    onToChange: (value: string) => void;
    onSubmit: (e: React.FormEvent) => void;
}) {
    const periodLabel = `${period.label}: ${period.from} — ${period.to}`;

    return (
        <header className="overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 shadow-2xl ring-1 ring-white/10">
            <div className="grid gap-5 px-6 py-6 lg:grid-cols-[1fr_auto] lg:items-center">
                <div className="flex min-w-0 items-center gap-5">
                    <div className="flex size-14 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20">
                        <img className="size-9 object-contain" src="/assets/anyservice-logo.png" alt="AnyService" />
                    </div>
                    <div className="min-w-0">
                        <div className="inline-flex items-center rounded-full bg-violet-500/20 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-violet-300 ring-1 ring-violet-400/30">
                            BI аналитика CRM
                        </div>
                        <h1 className="mt-2 text-2xl font-bold text-white">{title}</h1>
                        <div className="mt-1.5 flex flex-wrap items-center gap-2 text-sm text-slate-400">
                            <span>{account.name}</span>
                            <span className="size-1 rounded-full bg-slate-600" />
                            <span>{account.base_domain}</span>
                            <span className="size-1 rounded-full bg-slate-600" />
                            <span>{periodLabel}</span>
                        </div>
                    </div>
                </div>

                <form className="rounded-xl bg-white/5 p-4 ring-1 ring-white/10" onSubmit={onSubmit}>
                    <div className="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                        <CalendarDays className="size-3.5 text-violet-400" />
                        Период
                    </div>
                    <div className="grid grid-cols-[1fr_1fr_auto] gap-2">
                        <input
                            className="h-10 rounded-lg border-white/10 bg-white/10 px-3 text-sm text-white focus:border-violet-400 focus:ring-violet-400/20"
                            type="date"
                            value={from}
                            onChange={(e) => onFromChange(e.target.value)}
                        />
                        <input
                            className="h-10 rounded-lg border-white/10 bg-white/10 px-3 text-sm text-white focus:border-violet-400 focus:ring-violet-400/20"
                            type="date"
                            value={to}
                            onChange={(e) => onToChange(e.target.value)}
                        />
                        <button
                            className="h-10 rounded-lg bg-gradient-to-r from-violet-600 to-indigo-600 px-4 text-sm font-semibold text-white shadow-lg shadow-violet-500/30 hover:from-violet-500 hover:to-indigo-500"
                            type="submit"
                        >
                            Показать
                        </button>
                    </div>
                </form>
            </div>
        </header>
    );
}
