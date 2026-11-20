import { Link, router } from '@inertiajs/react';
import { Building2, Eye, Pencil, Plus, Power } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import DataTable from '@/components/DataTable';
import SearchInput from '@/components/SearchInput';
import FilterDropdown from '@/components/FilterDropdown';
import { ActiveBadge } from '@/components/StatusBadge';
import EmptyState from '@/components/EmptyState';
import { useConfirm } from '@/components/ConfirmationDialog';
import useFilters from '@/lib/useFilters';
import { formatDate, formatNumber } from '@/lib/utils';

export default function Index({ branches, filters: initial }) {
    const { filters, set, sort, reset, loading, activeCount } = useFilters('/branches', initial);
    const confirm = useConfirm();

    const toggle = async (b) => {
        const ok = await confirm({
            title: `${b.is_active ? 'Deactivate' : 'Activate'} ${b.name}?`,
            message: b.is_active ? 'The branch will stop accepting new leads, including public form submissions. Existing leads remain accessible.' : 'The branch will be able to receive new leads again.',
            confirmLabel: b.is_active ? 'Deactivate' : 'Activate',
            danger: b.is_active,
        });
        if (ok) router.patch(`/branches/${b.id}/toggle`, {}, { preserveScroll: true });
    };

    return (
        <AppLayout title="Branches" breadcrumbs={[{ label: 'Branches' }]}>
            <PageHeader title="Branches" description="Manage branches, their campaigns and contact details." actions={<Button href="/branches/create" icon={Plus}>New branch</Button>} />
            <DataTable
                loading={loading}
                rows={branches.data}
                meta={branches}
                sort={filters.sort}
                direction={filters.direction}
                onSort={sort}
                rowHref={(b) => `/branches/${b.id}`}
                empty={<EmptyState icon={Building2} title={activeCount ? 'No branches match' : 'No branches yet'} action={activeCount ? <Button variant="secondary" onClick={reset}>Clear filters</Button> : <Button href="/branches/create" icon={Plus}>New branch</Button>} />}
                toolbar={
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <SearchInput value={filters.search} onChange={(v) => set('search', v, { debounce: 300 })} placeholder="Search name, code, contact or email…" className="flex-1" />
                        <FilterDropdown className="sm:w-44" label="Statuses" value={filters.status} onChange={(v) => set('status', v)} options={[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]} />
                    </div>
                }
                columns={[
                    { key: 'name', label: 'Branch', sortable: true, render: (b) => <Link href={`/branches/${b.id}`} className="font-medium text-ink hover:text-primary-600">{b.name}</Link> },
                    { key: 'code', label: 'Code', sortable: true, render: (b) => <span className="font-mono text-xs text-gray-600">{b.code}</span> },
                    { key: 'contact_person', label: 'Contact', render: (b) => <div><p className="text-ink">{b.contact_person ?? '—'}</p><p className="text-xs text-muted">{b.contact_number}</p></div> },
                    { key: 'campaigns_count', label: 'Campaigns', render: (b) => formatNumber(b.campaigns_count) },
                    { key: 'users_count', label: 'Users', render: (b) => formatNumber(b.users_count) },
                    { key: 'leads_count', label: 'Leads', render: (b) => formatNumber(b.leads_count) },
                    { key: 'is_active', label: 'Status', render: (b) => <ActiveBadge active={b.is_active} /> },
                    { key: 'created_at', label: 'Created', sortable: true, render: (b) => <span className="text-gray-600">{formatDate(b.created_at)}</span> },
                    {
                        key: 'actions', label: <span className="sr-only">Actions</span>, className: 'text-right',
                        render: (b) => (
                            <div className="flex justify-end gap-1">
                                <Button variant="ghost" size="icon" href={`/branches/${b.id}`} aria-label="View"><Eye className="h-4 w-4" /></Button>
                                <Button variant="ghost" size="icon" href={`/branches/${b.id}/edit`} aria-label="Edit"><Pencil className="h-4 w-4" /></Button>
                                <Button variant={b.is_active ? 'danger-ghost' : 'ghost'} size="icon" onClick={() => toggle(b)} aria-label={b.is_active ? 'Deactivate' : 'Activate'} title={b.is_active ? 'Deactivate' : 'Activate'}><Power className="h-4 w-4" /></Button>
                            </div>
                        ),
                    },
                ]}
            />
        </AppLayout>
    );
}
