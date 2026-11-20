import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { BarChart3 } from 'lucide-react';
import { CHART_COLORS } from './StatusBadge';
import EmptyState from './EmptyState';
import { formatNumber } from '@/lib/utils';

/**
 * Horizontal bar list: every bar is direct-labelled with its name and value, so identity never
 * depends on colour. Single-hue (brand blue) unless rows carry their own configured status colour.
 */
export function BarList({ data, max = 8, valueLabel = 'leads', onSelect }) {
    if (!data?.length) return <EmptyState icon={BarChart3} title="No data for this period" />;
    const rows = data.length > max ? [...data.slice(0, max - 1), { name: `Other (${data.length - max + 1})`, total: data.slice(max - 1).reduce((s, r) => s + r.total, 0), other: true }] : data;
    const top = Math.max(...rows.map((r) => r.total), 1);
    const sum = rows.reduce((s, r) => s + r.total, 0);

    return (
        <ul className="space-y-3">
            {rows.map((r) => {
                const pct = sum ? Math.round((r.total / sum) * 100) : 0;
                const clickable = onSelect && r.id && !r.other;
                return (
                    <li key={r.name} title={`${r.name}: ${formatNumber(r.total)} ${valueLabel} (${pct}%)`}>
                        <button type="button" disabled={!clickable} onClick={() => clickable && onSelect(r)} className="group block w-full text-left disabled:cursor-default">
                            <div className="mb-1 flex items-baseline justify-between gap-3 text-sm">
                                <span className={`truncate text-ink ${clickable ? 'group-hover:text-primary-600' : ''}`}>{r.name}</span>
                                <span className="shrink-0 text-ink tabular-nums">
                                    {formatNumber(r.total)} <span className="text-xs text-muted">· {pct}%</span>
                                </span>
                            </div>
                            <div className="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                <div className="h-full rounded-full transition-all" style={{ width: `${Math.max((r.total / top) * 100, r.total ? 2 : 0)}%`, backgroundColor: r.other ? '#9CA3AF' : (CHART_COLORS[r.color] ?? CHART_COLORS.blue) }} />
                            </div>
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}

function TrendTooltip({ active, payload }) {
    if (!active || !payload?.length) return null;
    const p = payload[0].payload;
    return (
        <div className="rounded-lg border border-line bg-white px-3 py-2 text-xs shadow-lg">
            <p className="font-medium text-ink">{p.tooltipLabel ?? p.label}</p>
            <p className="mt-0.5 text-muted">
                <span className="font-semibold text-ink tabular-nums">{formatNumber(p.total)}</span> leads
            </p>
        </div>
    );
}

/** Single-series lead volume over time with a crosshair tooltip. */
export function TrendChart({ data, height = 260 }) {
    if (!data?.length || data.every((d) => !d.total)) return <EmptyState icon={BarChart3} title="No leads in this period" />;
    return (
        <div style={{ height }} role="img" aria-label="Lead volume over time">
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: -16 }}>
                    <defs>
                        <linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="#1695E6" stopOpacity={0.18} />
                            <stop offset="100%" stopColor="#1695E6" stopOpacity={0} />
                        </linearGradient>
                    </defs>
                    <CartesianGrid vertical={false} stroke="#F1F5F9" />
                    <XAxis dataKey="label" tickLine={false} axisLine={false} tick={{ fontSize: 11, fill: '#6B7280' }} minTickGap={24} />
                    <YAxis allowDecimals={false} tickLine={false} axisLine={false} tick={{ fontSize: 11, fill: '#6B7280' }} width={48} />
                    <Tooltip content={<TrendTooltip />} cursor={{ stroke: '#CBD5E1', strokeWidth: 1 }} />
                    <Area type="monotone" dataKey="total" stroke="#1695E6" strokeWidth={2} fill="url(#trendFill)" activeDot={{ r: 4, stroke: "#fff", strokeWidth: 2 }} animationDuration={500} />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    );
}

export function StatCard({ label, value, icon: Icon, hint, href }) {
    const body = (
        <div className="card flex h-full items-start justify-between gap-3 p-5 transition-colors hover:border-gray-300">
            <div className="min-w-0">
                <p className="text-sm text-muted">{label}</p>
                <p className="mt-1.5 text-2xl font-semibold tracking-tight text-ink tabular-nums">{formatNumber(value)}</p>
                {hint && <p className="mt-1 text-xs text-muted">{hint}</p>}
            </div>
            {Icon && (
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                    <Icon className="h-[18px] w-[18px]" />
                </span>
            )}
        </div>
    );
    return href ? (
        <a href={href} className="block">
            {body}
        </a>
    ) : (
        body
    );
}
