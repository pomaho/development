import { Fragment } from 'react';

// Project → city → vacancy drill-down; K names the counters every level carries.
type LocationBreakdownVacancy<K extends string> = { name: string } & { [P in K]: number };

type LocationBreakdownCity<K extends string> = LocationBreakdownVacancy<K> & {
    vacancies: LocationBreakdownVacancy<K>[];
};

export type LocationBreakdownProject<K extends string> = LocationBreakdownVacancy<K> & {
    cities: LocationBreakdownCity<K>[];
};

export type LocationFilter = { project: string; city?: string; vacancy?: string };

export type LocationFieldNames = { project_field_name: string; city_field_name: string; vacancy_field_name: string };

function BreakdownCount({ value, onClick }: { value: number; onClick: () => void }) {
    if (value === 0) {
        return <span className="font-mono tabular-nums text-slate-300">0</span>;
    }
    return (
        <button
            type="button"
            onClick={onClick}
            className="font-mono font-semibold tabular-nums text-indigo-700 underline-offset-2 hover:underline"
        >
            {value}
        </button>
    );
}

export function LocationBreakdownTable<K extends string>({ projects, fieldNames, columns, rate, onOpen }: {
    projects: LocationBreakdownProject<K>[];
    fieldNames: LocationFieldNames;
    columns: { key: K; label: string }[];
    // Optional trailing percentage column, numerator / denominator of two counters.
    rate?: { label: string; numerator: K; denominator: K };
    onOpen: (key: K, location: LocationFilter, path: string) => void;
}) {
    if (projects.length === 0) {
        return <p className="py-2 text-xs text-slate-400">Нет данных для детализации.</p>;
    }

    const rateCell = (node: LocationBreakdownVacancy<K>) => {
        if (!rate) return null;
        const denominator = node[rate.denominator];
        const percent = denominator > 0 ? Math.round((node[rate.numerator] / denominator) * 1000) / 10 : 0;
        return <td className="px-4 py-2 text-right text-xs font-semibold tabular-nums text-slate-600">{percent}%</td>;
    };

    const open = (key: K, location: LocationFilter) => {
        const path = [
            location.project,
            location.city === '—' ? 'Без города' : location.city,
            location.vacancy !== '—' ? location.vacancy : undefined,
        ].filter(Boolean).join(' / ');
        onOpen(key, location, path);
    };

    return (
        <div className="overflow-hidden rounded-xl ring-1 ring-slate-200">
            <table className="w-full bg-white text-left text-sm">
                <thead className="bg-gradient-to-r from-slate-50 to-slate-100/50">
                    <tr>
                        <th className="px-5 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{fieldNames.project_field_name}</th>
                        <th className="px-5 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{fieldNames.city_field_name}</th>
                        <th className="px-5 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{fieldNames.vacancy_field_name}</th>
                        {columns.map((column) => (
                            <th key={column.key} className="w-32 px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{column.label}</th>
                        ))}
                        {rate && <th className="w-28 px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{rate.label}</th>}
                    </tr>
                </thead>
                <tbody>
                    {projects.map((project) => (
                        <Fragment key={project.name}>
                            <tr className="bg-slate-100">
                                <td className="border-l-4 border-slate-300 px-5 py-2 font-semibold text-slate-700" colSpan={3}>
                                    {project.name}
                                </td>
                                {columns.map((column) => (
                                    <td key={column.key} className="px-4 py-2 text-right">
                                        <BreakdownCount value={project[column.key]} onClick={() => open(column.key, { project: project.name })} />
                                    </td>
                                ))}
                                {rateCell(project)}
                            </tr>
                            {project.cities.map((city) => city.vacancies.map((vacancy, vi) => {
                                const location = { project: project.name, city: city.name, vacancy: vacancy.name };
                                return (
                                    <tr key={`${city.name}-${vacancy.name}`} className="border-t border-slate-100 transition-colors hover:bg-violet-50/40">
                                        <td className="py-2 pl-9 pr-3" />
                                        <td className="px-5 py-2 text-xs text-slate-600">
                                            {vi === 0 ? (city.name === '—' ? <span className="italic text-slate-400">Без города</span> : city.name) : ''}
                                        </td>
                                        <td className="px-5 py-2 text-xs text-gray-500">
                                            {vacancy.name !== '—' ? vacancy.name : ''}
                                        </td>
                                        {columns.map((column) => (
                                            <td key={column.key} className="px-4 py-2 text-right">
                                                <BreakdownCount value={vacancy[column.key]} onClick={() => open(column.key, location)} />
                                            </td>
                                        ))}
                                        {rateCell(vacancy)}
                                    </tr>
                                );
                            }))}
                        </Fragment>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
