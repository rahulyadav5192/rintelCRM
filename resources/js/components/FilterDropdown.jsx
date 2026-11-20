import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

/** Compact select used in table toolbars. Highlights itself when a value is applied. */
export default function FilterDropdown({ label, value, onChange, options = [], allLabel, className }) {
    const active = value !== undefined && value !== null && value !== '';
    return (
        <div className={cn('relative', className)}>
            <select
                aria-label={label}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                className={cn(
                    'h-9 w-full appearance-none rounded-lg border bg-white py-0 pr-8 pl-3 text-sm shadow-xs transition focus:border-primary-500 focus:ring-3 focus:ring-primary-500/15 focus:outline-none',
                    active ? 'border-primary-300 bg-primary-50/60 font-medium text-primary-700' : 'border-line text-ink',
                )}
            >
                <option value="">{allLabel ?? `All ${label.toLowerCase()}`}</option>
                {options.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </select>
            <ChevronDown className="pointer-events-none absolute top-1/2 right-2.5 h-4 w-4 -translate-y-1/2 text-gray-400" />
        </div>
    );
}
