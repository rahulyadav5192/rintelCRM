import { useState } from 'react';
import { Activity, ChevronDown } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import DataTable from '@/components/DataTable';
import SearchInput from '@/components/SearchInput';
import FilterDropdown from '@/components/FilterDropdown';
import StatusBadge from '@/components/StatusBadge';
import EmptyState from '@/components/EmptyState';
import Modal from '@/components/Modal';
import Button from '@/components/Button';
import useFilters from '@/lib/useFilters';
import { formatDateTime } from '@/lib/utils';

const MODULE_COLORS = { leads: 'blue', users: 'violet', branches: 'emerald', campaigns: 'amber', public_forms: 'sky', exports: 'orange', settings: 'gray' };

function Values({ title, values }) {
    if (!values || !Object.keys(values).length) return null;
    return (
        <div>
            <p className="mb-1.5 text-xs font-medium text-muted uppercase">{title}</p>
            <pre className="max-h-64 overflow-auto rounded-lg border border-line bg-subtle p-3 text-xs whitespace-pre-wrap text-gray-700">{JSON.stringify(values, null, 2)}</pre>
        </div>
    );
}

export default function Index({ logs, filters: initial, modules, users }) {
    const { filters, set, reset, loading, activeCount } = useFilters('/activity-logs', initial);
    const [selected, setSelected] = useState(null);

    return (
        <AppLayout title="Activity Logs" breadcrumbs={[{ label: 'Activity Logs' }]}>
            <PageHeader title="Activity logs" description="An audit trail of every important change in the CRM." />
            <DataTable
                loading={loading}
                rows={logs.data}
                meta={logs}
                empty={<EmptyState icon={Activity} title="No activity found" action={activeCount > 0 && <Button variant="secondary" onClick={reset}>Clear filters</Button>} />}
                toolbar={
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5">
                        <SearchInput value={filters.search} onChange={(v) => set('search', v, { debounce: 300 })} placeholder="Search description, action or IP…" className="lg:col-span-1" />
                        <FilterDropdown label="Modules" value={filters.module} onChange={(v) => set('module', v)} options={modules.map((m) => ({ value: m, label: m.replace('_', ' ') }))} />
                        <FilterDropdown label="Users" value={filters.user_id} onChange={(v) => set('user_id', v)} options={users.map((u) => ({ value: u.id, label: u.name }))} />
                        <input type="date" aria-label="From date" value={filters.date_from ?? ''} onChange={(e) => set('date_from', e.target.value)} className="input h-9" />
                        <input type="date" aria-label="To date" value={filters.date_to ?? ''} onChange={(e) => set('date_to', e.target.value)} className="input h-9" />
                    </div>
                }
                columns={[
                    { key: 'created_at', label: 'When', render: (l) => <span className="text-gray-600">{formatDateTime(l.created_at)}</span> },
                    { key: 'user', label: 'User', render: (l) => <span className="font-medium text-ink">{l.user}</span> },
                    { key: 'module', label: 'Module', render: (l) => <StatusBadge color={MODULE_COLORS[l.module] ?? 'gray'} dot={false}>{l.module.replace('_', ' ')}</StatusBadge> },
                    { key: 'action', label: 'Action', render: (l) => <span className="font-mono text-xs text-gray-600">{l.action}</span> },
                    { key: 'description', label: 'Description', className: 'whitespace-normal min-w-[280px]', render: (l) => <span className="text-ink">{l.description}</span> },
                    { key: 'ip_address', label: 'IP', render: (l) => <span className="font-mono text-xs text-muted">{l.ip_address ?? '—'}</span> },
                    {
                        key: 'details', label: <span className="sr-only">Details</span>, className: 'text-right',
                        render: (l) => (l.old_values || l.new_values) && <Button variant="ghost" size="sm" onClick={() => setSelected(l)}>Details <ChevronDown className="h-3.5 w-3.5 -rotate-90" /></Button>,
                    },
                ]}
            />
            <Modal open={!!selected} onClose={() => setSelected(null)} title={selected?.description} description={selected && `${selected.user} · ${formatDateTime(selected.created_at)}`} size="lg">
                {selected && (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <Values title="Previous values" values={selected.old_values} />
                        <Values title="New values" values={selected.new_values} />
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
