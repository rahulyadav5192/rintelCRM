import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import EmptyState from './EmptyState';
import Pagination from './Pagination';

/**
 * columns: [{ key, label, sortable?, render?(row), className?, headerClassName? }]
 * Sorting is delegated to the server through onSort(key).
 */
export default function DataTable({ columns, rows, meta, sort, direction, onSort, rowHref, loading, empty, toolbar, className }) {
    return (
        <div className={cn('card overflow-hidden', className)}>
            {toolbar && <div className="border-b border-line p-3">{toolbar}</div>}
            <div className={cn('relative overflow-x-auto transition-opacity', loading && 'pointer-events-none opacity-60')}>
                <table className="min-w-full divide-y divide-line">
                    <thead className="bg-subtle/80">
                        <tr>
                            {columns.map((col) => (
                                <th key={col.key} scope="col" className={cn('th', col.headerClassName)}>
                                    {col.sortable && onSort ? (
                                        <button type="button" onClick={() => onSort(col.key)} className="group inline-flex items-center gap-1 uppercase hover:text-ink">
                                            {col.label}
                                            {sort === col.key ? (
                                                direction === 'desc' ? <ArrowDown className="h-3.5 w-3.5 text-primary-600" /> : <ArrowUp className="h-3.5 w-3.5 text-primary-600" />
                                            ) : (
                                                <ArrowUpDown className="h-3.5 w-3.5 opacity-0 transition-opacity group-hover:opacity-60" />
                                            )}
                                        </button>
                                    ) : (
                                        col.label
                                    )}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-line bg-white">
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length}>{empty ?? <EmptyState title="No records found" description="Try adjusting your search or filters." />}</td>
                            </tr>
                        ) : (
                            rows.map((row) => (
                                <tr
                                    key={row.id}
                                    onClick={rowHref ? (e) => !e.target.closest('a,button,input,select') && router.visit(rowHref(row)) : undefined}
                                    className={cn('transition-colors', rowHref && 'cursor-pointer hover:bg-subtle/70')}
                                >
                                    {columns.map((col) => (
                                        <td key={col.key} className={cn('td', col.className)}>
                                            {col.render ? col.render(row) : (row[col.key] ?? <span className="text-muted">—</span>)}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
            {meta && <Pagination meta={meta} />}
        </div>
    );
}
