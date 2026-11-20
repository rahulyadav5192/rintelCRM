import { useState } from 'react';
import { Search } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Field } from './FormInput';

/** Multi-select rendered as a searchable checkbox list. options: [{ value, label, hint?, disabled? }] */
export default function CheckboxList({ label, hint, error, required, options, value = [], onChange, emptyText = 'No options available.', columns = 2, searchable }) {
    const [query, setQuery] = useState('');
    const selected = new Set(value.map(String));
    const shown = query ? options.filter((o) => o.label.toLowerCase().includes(query.toLowerCase())) : options;
    const toggle = (v) => {
        const key = String(v);
        const next = selected.has(key) ? value.filter((x) => String(x) !== key) : [...value, v];
        onChange(next);
    };
    const allSelected = options.length > 0 && options.every((o) => o.disabled || selected.has(String(o.value)));

    return (
        <Field label={label} error={error} hint={hint} required={required}>
            <div className={cn('rounded-lg border', error ? 'border-red-400' : 'border-line')}>
                {(searchable ?? options.length > 8) && (
                    <div className="relative border-b border-line">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Filter…" className="w-full rounded-t-lg py-2 pr-3 pl-9 text-sm focus:outline-none" />
                    </div>
                )}
                {options.length === 0 ? (
                    <p className="px-3 py-4 text-sm text-muted">{emptyText}</p>
                ) : (
                    <>
                        <div className={cn('grid max-h-64 gap-1 overflow-y-auto p-2', columns === 2 ? 'sm:grid-cols-2' : columns === 3 ? 'sm:grid-cols-3' : '')}>
                            {shown.map((o) => (
                                <label key={o.value} className={cn('flex items-start gap-2.5 rounded-md px-2 py-1.5 text-sm', o.disabled ? 'opacity-50' : 'cursor-pointer hover:bg-subtle', selected.has(String(o.value)) && 'bg-primary-50/60')}>
                                    <input type="checkbox" className="mt-0.5 h-4 w-4 rounded border-gray-300 accent-primary-500" checked={selected.has(String(o.value))} disabled={o.disabled} onChange={() => toggle(o.value)} />
                                    <span className="min-w-0">
                                        <span className="block truncate text-ink">{o.label}</span>
                                        {o.hint && <span className="block truncate text-xs text-muted">{o.hint}</span>}
                                    </span>
                                </label>
                            ))}
                        </div>
                        <div className="flex items-center justify-between border-t border-line px-3 py-2 text-xs text-muted">
                            <span>{value.length} selected</span>
                            <button type="button" className="font-medium text-primary-600 hover:text-primary-700" onClick={() => onChange(allSelected ? [] : options.filter((o) => !o.disabled).map((o) => o.value))}>
                                {allSelected ? 'Clear all' : 'Select all'}
                            </button>
                        </div>
                    </>
                )}
            </div>
        </Field>
    );
}
