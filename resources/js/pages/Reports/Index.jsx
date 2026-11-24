import { usePage } from '@inertiajs/react';
import { Download, FileSpreadsheet } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import Card from '@/components/Card';
import FilterDropdown from '@/components/FilterDropdown';
import EmptyState from '@/components/EmptyState';
import { BarList } from '@/components/Charts';
import StatusBadge from '@/components/StatusBadge';
import useFilters from '@/lib/useFilters';
import { cn, formatDate, formatNumber, withQuery } from '@/lib/utils';

export default function Index({ type, types, rows, total, filters: initial, canExport, filterOptions }) {
    const { auth } = usePage().props;
    const { filters, set, setMany, reset, loading, activeCount } = useFilters('/reports', { ...initial, type });
    const scopedStatuses = (list) => (filters.campaign_id ? list.filter((s) => String(s.campaign_id) === String(filters.campaign_id)) : list);
    const campaignName = (id) => filterOptions.campaigns.find((c) => c.id === id)?.name;
    const statusLabel = (s) => (filters.campaign_id ? s.name : `${campaignName(s.campaign_id) ?? ''} · ${s.name}`);
    const isDate = type === 'date';
    const exportUrl = (format) => withQuery('/reports', { ...filters, type, export: format });
    const leadsLink = (row) => {
        const key = { admin: 'admin_id', campaign: 'campaign_id', manager: 'assigned_to' }[type];
        if (isDate) return withQuery('/leads', { ...filters, type: undefined, date_from: row.date, date_to: row.date });
        if (!key) return null;
        return withQuery('/leads', { ...filters, type: undefined, [key]: row.id ?? (type === 'manager' ? 'unassigned' : 'none') });
    };

    return (
        <AppLayout title="Reports" breadcrumbs={[{ label: 'Reports' }]}>
            <PageHeader
                title="Reports"
                description={auth.user.role === 'super_admin' ? 'Lead volumes across all admins and campaigns.' : 'Lead volumes for the data you can access.'}
                actions={
                    canExport && (
                        <>
                            <Button variant="secondary" as="a" href={exportUrl('csv')} icon={Download}>CSV</Button>
                            <Button variant="secondary" as="a" href={exportUrl('xlsx')} icon={FileSpreadsheet}>Excel</Button>
                        </>
                    )
                }
            />

            <div className="mb-4 flex gap-1 overflow-x-auto rounded-lg border border-line bg-white p-1">
                {types.map((t) => (
                    <button key={t.value} type="button" onClick={() => set('type', t.value)} className={cn('rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors', type === t.value ? 'bg-primary-500 text-white' : 'text-muted hover:bg-subtle hover:text-ink')}>
                        {t.label}
                    </button>
                ))}
            </div>

            <div className="card mb-6 grid grid-cols-1 gap-2 p-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                <input type="date" aria-label="From date" title="From date" value={filters.date_from ?? ''} onChange={(e) => set('date_from', e.target.value)} className="input h-9" />
                <input type="date" aria-label="To date" title="To date" value={filters.date_to ?? ''} onChange={(e) => set('date_to', e.target.value)} className="input h-9" />
                {filterOptions.admins.length > 0 && <FilterDropdown label="Admins" value={filters.admin_id} onChange={(v) => set('admin_id', v)} options={[{ value: 'none', label: 'No admin' }, ...filterOptions.admins.map((a) => ({ value: a.id, label: a.name }))]} />}
                <FilterDropdown label="Campaigns" value={filters.campaign_id} onChange={(v) => setMany({ campaign_id: v, lead_status_id: '', payment_status_id: '' })} options={filterOptions.campaigns.map((c) => ({ value: c.id, label: c.name }))} />
                <FilterDropdown label="Managers" value={filters.assigned_to} onChange={(v) => set('assigned_to', v)} options={[{ value: 'unassigned', label: 'Unassigned' }, ...filterOptions.managers.map((m) => ({ value: m.id, label: m.name }))]} />
                <FilterDropdown label="Lead statuses" value={filters.lead_status_id} onChange={(v) => set('lead_status_id', v)} options={scopedStatuses(filterOptions.leadStatuses).map((s) => ({ value: s.id, label: statusLabel(s) }))} />
                <FilterDropdown label="Payment statuses" value={filters.payment_status_id} onChange={(v) => set('payment_status_id', v)} options={scopedStatuses(filterOptions.paymentStatuses).map((s) => ({ value: s.id, label: statusLabel(s) }))} />
                {activeCount > 1 && <Button variant="ghost" onClick={() => setMany(Object.fromEntries(Object.keys(filters).filter((k) => k !== 'type').map((k) => [k, ''])))}>Clear filters</Button>}
            </div>

            <div className={cn('grid grid-cols-1 gap-6 transition-opacity xl:grid-cols-5', loading && 'opacity-60')}>
                <Card title={`${types.find((t) => t.value === type)?.label} report`} description={`${formatNumber(total)} leads${isDate && !filters.date_from ? ' · last 30 days' : ''}`} padded={false} className="xl:col-span-3">
                    {rows.length === 0 ? (
                        <EmptyState title="No leads match these filters" action={activeCount > 1 && <Button variant="secondary" onClick={reset}>Reset</Button>} />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-line">
                                <thead className="bg-subtle/80">
                                    <tr><th className="th">{isDate ? 'Date' : 'Name'}</th><th className="th text-right">Leads</th><th className="th text-right">Share</th></tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {rows.map((r) => {
                                        const href = leadsLink(r);
                                        const name = isDate ? formatDate(r.date) : r.color && (type === 'status' || type === 'payment') ? <StatusBadge color={r.color}>{r.name}</StatusBadge> : r.name;
                                        return (
                                            <tr key={r.id ?? r.date ?? r.name} className="hover:bg-subtle/60">
                                                <td className="td">{href ? <a href={href} className="font-medium text-ink hover:text-primary-600">{name}</a> : name}</td>
                                                <td className="td text-right tabular-nums">{formatNumber(r.total)}</td>
                                                <td className="td text-right text-muted tabular-nums">{total ? ((r.total / total) * 100).toFixed(1) : 0}%</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                                <tfoot className="bg-subtle/80">
                                    <tr><td className="td font-semibold">Total</td><td className="td text-right font-semibold tabular-nums">{formatNumber(total)}</td><td className="td text-right text-muted">100%</td></tr>
                                </tfoot>
                            </table>
                        </div>
                    )}
                </Card>
                <Card title="Distribution" className="xl:col-span-2">
                    <BarList data={isDate ? rows.map((r) => ({ name: formatDate(r.date), total: r.total })) : rows} max={10} />
                </Card>
            </div>
        </AppLayout>
    );
}
