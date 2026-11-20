import { cn } from '@/lib/utils';

// Static class strings so Tailwind can detect them.
const COLORS = {
    gray: 'bg-gray-100 text-gray-700 ring-gray-200',
    blue: 'bg-primary-50 text-primary-700 ring-primary-200',
    sky: 'bg-sky-50 text-sky-700 ring-sky-200',
    indigo: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    violet: 'bg-violet-50 text-violet-700 ring-violet-200',
    green: 'bg-green-50 text-green-700 ring-green-200',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    amber: 'bg-amber-50 text-amber-700 ring-amber-200',
    orange: 'bg-orange-50 text-orange-700 ring-orange-200',
    red: 'bg-red-50 text-red-700 ring-red-200',
    pink: 'bg-pink-50 text-pink-700 ring-pink-200',
};

export const DOT_COLORS = {
    gray: 'bg-gray-400',
    blue: 'bg-primary-500',
    sky: 'bg-sky-500',
    indigo: 'bg-indigo-500',
    violet: 'bg-violet-500',
    green: 'bg-green-500',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    orange: 'bg-orange-500',
    red: 'bg-red-500',
    pink: 'bg-pink-500',
};

export const CHART_COLORS = {
    gray: '#9CA3AF',
    blue: '#1695E6',
    sky: '#0EA5E9',
    indigo: '#6366F1',
    violet: '#8B5CF6',
    green: '#22C55E',
    emerald: '#10B981',
    amber: '#F59E0B',
    orange: '#F97316',
    red: '#EF4444',
    pink: '#EC4899',
};

export default function StatusBadge({ color = 'gray', children, dot = true, className }) {
    if (!children) return <span className="text-sm text-muted">—</span>;
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', COLORS[color] ?? COLORS.gray, className)}>
            {dot && <span className={cn('h-1.5 w-1.5 rounded-full', DOT_COLORS[color] ?? DOT_COLORS.gray)} />}
            {children}
        </span>
    );
}

export function ActiveBadge({ active, activeLabel = 'Active', inactiveLabel = 'Inactive' }) {
    return <StatusBadge color={active ? 'green' : 'gray'}>{active ? activeLabel : inactiveLabel}</StatusBadge>;
}
