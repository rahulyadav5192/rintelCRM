import { cn } from '@/lib/utils';

export default function Card({ title, description, actions, className, bodyClassName, children, padded = true }) {
    return (
        <section className={cn('card', className)}>
            {(title || actions) && (
                <header className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                    <div className="min-w-0">
                        {title && <h2 className="text-sm font-semibold text-ink">{title}</h2>}
                        {description && <p className="mt-0.5 text-xs text-muted">{description}</p>}
                    </div>
                    {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
                </header>
            )}
            <div className={cn(padded && 'p-5', bodyClassName)}>{children}</div>
        </section>
    );
}

export function DetailList({ items }) {
    return (
        <dl className="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            {items.filter(Boolean).map(({ label, value, full }) => (
                <div key={label} className={full ? 'sm:col-span-2' : ''}>
                    <dt className="text-xs font-medium text-muted">{label}</dt>
                    <dd className="mt-1 text-sm break-words text-ink">{value === null || value === undefined || value === '' ? '—' : value}</dd>
                </div>
            ))}
        </dl>
    );
}
