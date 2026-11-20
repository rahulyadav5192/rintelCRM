import { Search, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function SearchInput({ value, onChange, placeholder = 'Search…', className }) {
    return (
        <div className={cn('relative', className)}>
            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input type="search" value={value ?? ''} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="input h-9 pr-8 pl-9 [&::-webkit-search-cancel-button]:hidden" />
            {value ? (
                <button type="button" onClick={() => onChange('')} className="absolute top-1/2 right-2 -translate-y-1/2 rounded p-0.5 text-gray-400 hover:text-ink" aria-label="Clear search">
                    <X className="h-3.5 w-3.5" />
                </button>
            ) : null}
        </div>
    );
}
