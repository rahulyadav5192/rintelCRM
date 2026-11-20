import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn, formatNumber } from '@/lib/utils';

/** Renders a Laravel LengthAwarePaginator (links/from/to/total). */
export default function Pagination({ meta }) {
    if (!meta || !meta.total) return null;
    const links = meta.links ?? [];
    const pages = links.slice(1, -1);

    return (
        <div className="flex flex-col items-center justify-between gap-3 border-t border-line px-4 py-3 sm:flex-row">
            <p className="text-xs text-muted">
                Showing <span className="font-medium text-ink">{formatNumber(meta.from)}</span>–<span className="font-medium text-ink">{formatNumber(meta.to)}</span> of{' '}
                <span className="font-medium text-ink">{formatNumber(meta.total)}</span>
            </p>
            {meta.last_page > 1 && (
                <nav className="flex items-center gap-1">
                    <PageLink href={meta.prev_page_url} label="Previous">
                        <ChevronLeft className="h-4 w-4" />
                    </PageLink>
                    {pages.map((l, i) =>
                        l.url === null ? (
                            <span key={`gap-${i}`} className="px-2 text-xs text-muted">
                                …
                            </span>
                        ) : (
                            <PageLink key={l.label} href={l.url} active={l.active}>
                                {l.label}
                            </PageLink>
                        ),
                    )}
                    <PageLink href={meta.next_page_url} label="Next">
                        <ChevronRight className="h-4 w-4" />
                    </PageLink>
                </nav>
            )}
        </div>
    );
}

function PageLink({ href, active, label, children }) {
    const classes = cn(
        'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-xs font-medium transition-colors',
        active ? 'bg-primary-500 text-white' : 'text-muted hover:bg-subtle hover:text-ink',
        !href && 'pointer-events-none opacity-40',
    );
    if (!href) return <span className={classes} aria-label={label}>{children}</span>;
    return (
        <Link href={href} preserveScroll preserveState className={classes} aria-label={label}>
            {children}
        </Link>
    );
}
