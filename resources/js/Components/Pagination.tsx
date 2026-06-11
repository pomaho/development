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
        <div className="mt-5 flex flex-wrap gap-2 text-theme-sm">
            {links.map((link, index) => link.url ? (
                <a
                    className={link.active
                        ? 'inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-brand-500 px-3 font-medium text-white shadow-theme-xs'
                        : 'inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 font-medium text-gray-700 shadow-theme-xs hover:border-brand-300 hover:text-brand-500'}
                    href={link.url}
                    key={`${link.label}-${index}`}
                >
                    {paginationLabel(link.label)}
                </a>
            ) : (
                <span className="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 px-3 font-medium text-gray-400" key={`${link.label}-${index}`}>
                    {paginationLabel(link.label)}
                </span>
            ))}
        </div>
    );
}
