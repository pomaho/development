type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    links: PaginationLink[];
};

const paginationLabel = (label: string) => label
    .replace('&laquo; Previous', 'Назад')
    .replace('Next &raquo;', 'Вперед');

export default function Pagination({ links }: Props) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="mt-4 flex flex-wrap gap-2 text-sm">
            {links.map((link, index) => link.url ? (
                <a
                    className={link.active
                        ? 'rounded bg-blue-700 px-3 py-1 text-white'
                        : 'rounded border border-slate-300 px-3 py-1 text-slate-700 hover:border-blue-400'}
                    href={link.url}
                    key={`${link.label}-${index}`}
                >
                    {paginationLabel(link.label)}
                </a>
            ) : (
                <span className="rounded border border-slate-200 px-3 py-1 text-slate-400" key={`${link.label}-${index}`}>
                    {paginationLabel(link.label)}
                </span>
            ))}
        </div>
    );
}
