import { cn } from '@/lib/utils';

export default function Toggle({ checked, onChange, label, description, disabled, name }) {
    return (
        <label className={cn('flex items-start justify-between gap-4', disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer')}>
            <span className="min-w-0">
                <span className="block text-sm font-medium text-ink">{label}</span>
                {description && <span className="mt-0.5 block text-xs text-muted">{description}</span>}
            </span>
            <button
                type="button"
                role="switch"
                name={name}
                aria-checked={!!checked}
                disabled={disabled}
                onClick={() => onChange(!checked)}
                className={cn('relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors', checked ? 'bg-primary-500' : 'bg-gray-200')}
            >
                <span className={cn('inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform', checked ? 'translate-x-4.5' : 'translate-x-0.5')} />
            </button>
        </label>
    );
}

export function Checkbox({ checked, onChange, label, description, disabled }) {
    return (
        <label className={cn('flex items-start gap-3 rounded-lg border p-3 transition-colors', checked ? 'border-primary-300 bg-primary-50/50' : 'border-line hover:bg-subtle', disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer')}>
            <input type="checkbox" className="mt-0.5 h-4 w-4 rounded border-gray-300 accent-primary-500" checked={!!checked} disabled={disabled} onChange={(e) => onChange(e.target.checked)} />
            <span className="min-w-0">
                <span className="block text-sm font-medium text-ink">{label}</span>
                {description && <span className="mt-0.5 block text-xs text-muted">{description}</span>}
            </span>
        </label>
    );
}
