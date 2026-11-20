import { Link, router, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus, Power, Users as UsersIcon } from 'lucide-react';
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
import { initials, timeAgo } from '@/lib/utils';

const ROLE_COLORS = { super_admin: 'indigo', branch_admin: 'blue', manager: 'gray' };

export default function Index({ users, filters: initial, roles, branches }) {
    const { auth } = usePage().props;
    const { filters, set, reset, loading, activeCount } = useFilters('/users', initial);
    const confirm = useConfirm();
    const isSuper = auth.user.role === 'super_admin';
    const title = isSuper ? 'Users' : 'Managers';

    const toggle = async (u) => {
        if (await confirm({ title: `${u.is_active ? 'Deactivate' : 'Activate'} ${u.name}?`, message: u.is_active ? 'They will be signed out and unable to log in. Their leads and history are preserved.' : 'They will be able to sign in again.', confirmLabel: u.is_active ? 'Deactivate' : 'Activate', danger: u.is_active })) {
            router.patch(`/users/${u.id}/toggle`, {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout title={title} breadcrumbs={[{ label: title }]}>
            <PageHeader title={title} description={isSuper ? 'Manage accounts, roles, campaign access and permissions.' : `Managers in ${auth.user.branch?.name ?? 'your branch'}.`} actions={<Button href="/users/create" icon={Plus}>New {isSuper ? 'user' : 'manager'}</Button>} />
            <DataTable
                loading={loading}
                rows={users.data}
                meta={users}
                rowHref={(u) => `/users/${u.id}`}
                empty={<EmptyState icon={UsersIcon} title={activeCount ? 'No users match' : 'No users yet'} action={activeCount && <Button variant="secondary" onClick={reset}>Clear filters</Button>} />}
                toolbar={
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <SearchInput value={filters.search} onChange={(v) => set('search', v, { debounce: 300 })} placeholder="Search name, email or mobile…" className="flex-1" />
                        {roles.length > 1 && <FilterDropdown className="sm:w-44" label="Roles" value={filters.role} onChange={(v) => set('role', v)} options={roles} />}
                        {isSuper && <FilterDropdown className="sm:w-44" label="Branches" value={filters.branch_id} onChange={(v) => set('branch_id', v)} options={branches.map((b) => ({ value: b.id, label: b.name }))} />}
                        <FilterDropdown className="sm:w-40" label="Statuses" value={filters.status} onChange={(v) => set('status', v)} options={[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]} />
                    </div>
                }
                columns={[
                    {
                        key: 'name', label: 'User',
                        render: (u) => (
                            <div className="flex items-center gap-3">
                                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">{initials(u.name)}</span>
                                <div><Link href={`/users/${u.id}`} className="font-medium text-ink hover:text-primary-600">{u.name}</Link><p className="text-xs text-muted">{u.email}</p></div>
                            </div>
                        ),
                    },
                    { key: 'role', label: 'Role', render: (u) => <StatusBadge color={ROLE_COLORS[u.role]} dot={false}>{u.role_label}</StatusBadge> },
                    { key: 'branch', label: 'Branch', render: (u) => <span className="text-gray-600">{u.branch ?? 'All branches'}</span> },
                    { key: 'campaigns', label: 'Campaigns', render: (u) => <span className="block max-w-[220px] truncate text-gray-600" title={u.campaigns.join(', ')}>{u.role === 'super_admin' ? 'All campaigns' : u.campaigns.join(', ') || '—'}</span> },
                    { key: 'assigned_leads_count', label: 'Assigned leads' },
                    { key: 'last_login_at', label: 'Last login', render: (u) => <span className="text-gray-600">{u.last_login_at ? timeAgo(u.last_login_at) : 'Never'}</span> },
                    { key: 'is_active', label: 'Status', render: (u) => <ActiveBadge active={u.is_active} /> },
                    {
                        key: 'actions', label: <span className="sr-only">Actions</span>, className: 'text-right',
                        render: (u) => (
                            <div className="flex justify-end gap-1">
                                <Button variant="ghost" size="icon" href={`/users/${u.id}`} aria-label="View"><Eye className="h-4 w-4" /></Button>
                                <Button variant="ghost" size="icon" href={`/users/${u.id}/edit`} aria-label="Edit"><Pencil className="h-4 w-4" /></Button>
                                {u.can_toggle && <Button variant={u.is_active ? 'danger-ghost' : 'ghost'} size="icon" onClick={() => toggle(u)} aria-label={u.is_active ? 'Deactivate' : 'Activate'}><Power className="h-4 w-4" /></Button>}
                            </div>
                        ),
                    },
                ]}
            />
        </AppLayout>
    );
}
