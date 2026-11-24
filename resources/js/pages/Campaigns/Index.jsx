import { Link, router } from '@inertiajs/react';
import { Eye, ListChecks, Megaphone, Pencil, Plus, Power } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import DataTable from '@/components/DataTable';
import SearchInput from '@/components/SearchInput';
import FilterDropdown from '@/components/FilterDropdown';
import StatusBadge, { ActiveBadge } from '@/components/StatusBadge';
import EmptyState from '@/components/EmptyState';
import { useConfirm } from '@/components/ConfirmationDialog';
import useFilters from '@/lib/useFilters';
import { formatNumber } from '@/lib/utils';

export default function Index({ campaigns, filters: initial, canManage }) {
    const { filters, set, reset, loading, activeCount } = useFilters('/campaigns', initial);
    const confirm = useConfirm();

    const toggle = async (c) => {
        if (await confirm({ title: `${c.is_active ? 'Deactivate' : 'Activate'} ${c.name}?`, message: c.is_active ? 'No new leads can be created for this campaign. Existing leads remain accessible.' : 'The campaign will accept new leads again.', confirmLabel: c.is_active ? 'Deactivate' : 'Activate', danger: c.is_active })) {
            router.patch(`/campaigns/${c.id}/toggle`, {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout title="Campaigns" breadcrumbs={[{ label: 'Campaigns' }]}>
            <PageHeader title="Campaigns" description={canManage ? 'Create campaigns, assign admins and managers, and configure statuses.' : 'Campaigns you have access to.'} actions={canManage && <Button href="/campaigns/create" icon={Plus}>New campaign</Button>} />
            <DataTable
                loading={loading}
                rows={campaigns.data}
                meta={campaigns}
                rowHref={(c) => `/campaigns/${c.id}`}
                empty={<EmptyState icon={Megaphone} title={activeCount ? 'No campaigns match' : 'No campaigns yet'} action={activeCount ? <Button variant="secondary" onClick={reset}>Clear filters</Button> : canManage && <Button href="/campaigns/create" icon={Plus}>New campaign</Button>} />}
                toolbar={
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <SearchInput value={filters.search} onChange={(v) => set('search', v, { debounce: 300 })} placeholder="Search campaigns…" className="flex-1" />
                        <FilterDropdown className="sm:w-44" label="Statuses" value={filters.status} onChange={(v) => set('status', v)} options={[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]} />
                    </div>
                }
                columns={[
                    { key: 'name', label: 'Campaign', render: (c) => <div><Link href={`/campaigns/${c.id}`} className="font-medium text-ink hover:text-primary-600">{c.name}</Link><p className="max-w-xs truncate text-xs text-muted">{c.description}</p></div> },
                    { key: 'code', label: 'Code', render: (c) => <span className="font-mono text-xs text-gray-600">{c.code}</span> },
                    ...(canManage ? [{ key: 'admins', label: 'Admins', render: (c) => <span className="block max-w-[220px] truncate text-gray-600" title={c.admins.join(', ')}>{c.admins.join(', ') || '—'}</span> }] : []),
                    { key: 'managers_count', label: 'Managers', render: (c) => formatNumber(c.managers_count) },
                    { key: 'leads_count', label: 'Leads', render: (c) => <Link href={`/leads?campaign_id=${c.id}`} className="text-primary-600 hover:underline">{formatNumber(c.leads_count)}</Link> },
                    { key: 'is_public', label: 'Public form', render: (c) => (c.is_public ? <StatusBadge color="sky">Enabled</StatusBadge> : <span className="text-muted">—</span>) },
                    { key: 'is_active', label: 'Status', render: (c) => <ActiveBadge active={c.is_active} /> },
                    {
                        key: 'actions', label: <span className="sr-only">Actions</span>, className: 'text-right',
                        render: (c) => (
                            <div className="flex justify-end gap-1">
                                <Button variant="ghost" size="icon" href={`/campaigns/${c.id}`} aria-label="View"><Eye className="h-4 w-4" /></Button>
                                {canManage && (
                                    <>
                                        <Button variant="ghost" size="icon" href={`/campaigns/${c.id}/statuses`} aria-label="Statuses" title="Configure statuses"><ListChecks className="h-4 w-4" /></Button>
                                        <Button variant="ghost" size="icon" href={`/campaigns/${c.id}/edit`} aria-label="Edit"><Pencil className="h-4 w-4" /></Button>
                                        <Button variant={c.is_active ? 'danger-ghost' : 'ghost'} size="icon" onClick={() => toggle(c)} aria-label={c.is_active ? 'Deactivate' : 'Activate'}><Power className="h-4 w-4" /></Button>
                                    </>
                                )}
                            </div>
                        ),
                    },
                ]}
            />
        </AppLayout>
    );
}
