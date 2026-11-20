import { useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Download, Eye, FileSpreadsheet, Pencil, Plus, SlidersHorizontal, Trash2, UsersRound, X } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import DataTable from '@/components/DataTable';
import SearchInput from '@/components/SearchInput';
import FilterDropdown from '@/components/FilterDropdown';
import StatusBadge from '@/components/StatusBadge';
import EmptyState from '@/components/EmptyState';
import Dropdown from '@/components/Dropdown';
import { useConfirm } from '@/components/ConfirmationDialog';
import useFilters from '@/lib/useFilters';
import { cn, formatDate, withQuery } from '@/lib/utils';

export default function Index({ leads, filters: initialFilters, filterOptions }) {
    const { auth } = usePage().props;
    const { filters, set, setMany, sort, reset, loading, activeCount } = useFilters('/leads', initialFilters);
    const [showFilters, setShowFilters] = useState(activeCount > 0 && !(activeCount === 1 && filters.search));
    const confirm = useConfirm();

    const campaignName = filterOptions.campaigns.find((c) => String(c.id) === String(filters.campaign_id))?.name;
    const statusOptions = useMemo(() => {
        const scoped = (list) => (filters.campaign_id ? list.filter((s) => String(s.campaign_id) === String(filters.campaign_id)) : list);
        const label = (s) => (filters.campaign_id ? s.name : `${filterOptions.campaigns.find((c) => c.id === s.campaign_id)?.name ?? ''} · ${s.name}`);
        return {
            lead: scoped(filterOptions.leadStatuses).map((s) => ({ value: s.id, label: label(s) })),
            payment: scoped(filterOptions.paymentStatuses).map((s) => ({ value: s.id, label: label(s) })),
        };
    }, [filters.campaign_id, filterOptions]);

    const destroy = async (lead) => {
        if (await confirm({ title: `Delete lead ${lead.lead_number}?`, message: 'The lead will be removed from all listings. This action is logged.', confirmLabel: 'Delete lead', danger: true })) {
            router.delete(`/leads/${lead.id}`, { preserveScroll: true });
        }
    };

    const exportUrl = (format) => withQuery('/leads/export', { ...filters, format });

    const columns = [
        { key: 'lead_number', label: 'Lead ID', sortable: true, render: (l) => <Link href={`/leads/${l.id}`} className="font-medium text-primary-600 hover:text-primary-700">{l.lead_number}</Link> },
        { key: 'lead_date', label: 'Date', sortable: true, render: (l) => <span className="text-gray-600">{formatDate(l.lead_date)}</span> },
        { key: 'name', label: 'Name', sortable: true, render: (l) => <span className="font-medium text-ink">{l.name}</span> },
        { key: 'mobile', label: 'Mobile', sortable: true, render: (l) => <span className="text-gray-600 tabular-nums">{l.mobile}</span> },
        { key: 'email', label: 'Email', render: (l) => <span className="text-gray-600">{l.email ?? '—'}</span> },
        ...(filterOptions.showBranchFilter ? [{ key: 'branch', label: 'Branch' }] : []),
        { key: 'campaign', label: 'Campaign' },
        { key: 'assignee', label: 'Advisor', render: (l) => l.assignee ?? <span className="text-muted italic">Unassigned</span> },
        { key: 'lead_status', label: 'Lead status', render: (l) => <StatusBadge color={l.lead_status?.color}>{l.lead_status?.name}</StatusBadge> },
        { key: 'payment_status', label: 'Payment', render: (l) => <StatusBadge color={l.payment_status?.color}>{l.payment_status?.name}</StatusBadge> },
        { key: 'creator', label: 'Created by', render: (l) => <span className="text-gray-600">{l.creator ?? '—'}</span> },
        {
            key: 'actions',
            label: <span className="sr-only">Actions</span>,
            headerClassName: 'text-right',
            className: 'text-right',
            render: (l) => (
                <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" href={`/leads/${l.id}`} title="View" aria-label="View lead">
                        <Eye className="h-4 w-4" />
                    </Button>
                    {l.can.update && (
                        <Button variant="ghost" size="icon" href={`/leads/${l.id}/edit`} title="Edit" aria-label="Edit lead">
                            <Pencil className="h-4 w-4" />
                        </Button>
                    )}
                    {l.can.delete && (
                        <Button variant="danger-ghost" size="icon" onClick={() => destroy(l)} title="Delete" aria-label="Delete lead">
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title={campaignName ? `${campaignName} leads` : 'Leads'} breadcrumbs={[{ label: 'Leads', href: '/leads' }, ...(campaignName ? [{ label: campaignName }] : [])]}>
            <PageHeader
                title={campaignName ? `${campaignName} leads` : 'Leads'}
                description={`${leads.total} lead${leads.total === 1 ? '' : 's'}${activeCount ? ' matching your filters' : ' you have access to'}.`}
                actions={
                    <>
                        {auth.can.exportLeads && (
                            <Dropdown
                                width="w-48"
                                trigger={() => (
                                    <Button variant="secondary" icon={Download}>
                                        Export
                                    </Button>
                                )}
                            >
                                <div className="p-1.5">
                                    <a href={exportUrl('csv')} className="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-gray-700 hover:bg-subtle">
                                        <Download className="h-4 w-4 text-muted" /> Export as CSV
                                    </a>
                                    <a href={exportUrl('xlsx')} className="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-gray-700 hover:bg-subtle">
                                        <FileSpreadsheet className="h-4 w-4 text-muted" /> Export as Excel
                                    </a>
                                </div>
                            </Dropdown>
                        )}
                        {auth.can.createLeads && (
                            <Button href={withQuery('/leads/create', { campaign_id: filters.campaign_id })} icon={Plus}>
                                New lead
                            </Button>
                        )}
                    </>
                }
            />

            <DataTable
                loading={loading}
                columns={columns}
                rows={leads.data}
                meta={leads}
                sort={filters.sort}
                direction={filters.direction}
                onSort={sort}
                rowHref={(l) => `/leads/${l.id}`}
                empty={
                    <EmptyState
                        icon={UsersRound}
                        title={activeCount ? 'No leads match your filters' : 'No leads yet'}
                        description={activeCount ? 'Try removing a filter or searching for something else.' : 'Leads you create or receive from public forms will appear here.'}
                        action={activeCount ? <Button variant="secondary" onClick={reset}>Clear filters</Button> : auth.can.createLeads ? <Button href="/leads/create" icon={Plus}>New lead</Button> : null}
                    />
                }
                toolbar={
                    <div className="space-y-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <SearchInput value={filters.search} onChange={(v) => set('search', v, { debounce: 350 })} placeholder="Search by lead ID, name, mobile, email or post code…" className="flex-1" />
                            <div className="flex gap-2">
                                <Button variant={showFilters ? 'primary' : 'secondary'} icon={SlidersHorizontal} onClick={() => setShowFilters(!showFilters)} className={cn(!showFilters && activeCount > 0 && 'border-primary-300 text-primary-700')}>
                                    Filters{activeCount > 0 ? ` (${activeCount})` : ''}
                                </Button>
                                {activeCount > 0 && (
                                    <Button variant="ghost" icon={X} onClick={reset}>
                                        Clear
                                    </Button>
                                )}
                            </div>
                        </div>
                        {showFilters && (
                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                                {filterOptions.showBranchFilter && <FilterDropdown label="Branches" value={filters.branch_id} onChange={(v) => set('branch_id', v)} options={filterOptions.branches.map((b) => ({ value: b.id, label: b.name }))} />}
                                <FilterDropdown label="Campaigns" value={filters.campaign_id} onChange={(v) => setMany({ campaign_id: v, lead_status_id: '', payment_status_id: '' })} options={filterOptions.campaigns.map((c) => ({ value: c.id, label: c.name }))} />
                                <FilterDropdown label="Advisors" value={filters.assigned_to} onChange={(v) => set('assigned_to', v)} options={[{ value: 'unassigned', label: 'Unassigned' }, ...filterOptions.managers.map((m) => ({ value: m.id, label: m.name }))]} />
                                <FilterDropdown label="Lead statuses" value={filters.lead_status_id} onChange={(v) => set('lead_status_id', v)} options={statusOptions.lead} />
                                <FilterDropdown label="Payment statuses" value={filters.payment_status_id} onChange={(v) => set('payment_status_id', v)} options={statusOptions.payment} />
                                <FilterDropdown label="Sources" value={filters.source} onChange={(v) => set('source', v)} options={[{ value: 'manual', label: 'Created in CRM' }, { value: 'public', label: 'Public form' }]} />
                                <input type="date" aria-label="From date" value={filters.date_from ?? ''} max={filters.date_to || undefined} onChange={(e) => set('date_from', e.target.value)} className="input h-9" title="From date" />
                                <input type="date" aria-label="To date" value={filters.date_to ?? ''} min={filters.date_from || undefined} onChange={(e) => set('date_to', e.target.value)} className="input h-9" title="To date" />
                            </div>
                        )}
                    </div>
                }
            />
        </AppLayout>
    );
}
