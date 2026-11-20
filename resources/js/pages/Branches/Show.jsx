import { Link, router } from '@inertiajs/react';
import { Globe, Pencil, Power, UsersRound } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import Card, { DetailList } from '@/components/Card';
import StatusBadge, { ActiveBadge } from '@/components/StatusBadge';
import EmptyState from '@/components/EmptyState';
import { useConfirm } from '@/components/ConfirmationDialog';
import { formatDate, formatNumber } from '@/lib/utils';

export default function Show({ branch, users, recentLeads }) {
    const confirm = useConfirm();
    const toggle = async () => {
        if (await confirm({ title: `${branch.is_active ? 'Deactivate' : 'Activate'} ${branch.name}?`, message: branch.is_active ? 'New leads and public submissions will be blocked. Existing leads remain accessible.' : 'The branch will accept new leads again.', confirmLabel: branch.is_active ? 'Deactivate' : 'Activate', danger: branch.is_active })) {
            router.patch(`/branches/${branch.id}/toggle`, {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout title={branch.name} breadcrumbs={[{ label: 'Branches', href: '/branches' }, { label: branch.name }]}>
            <PageHeader
                title={branch.name}
                actions={
                    <>
                        <Button variant="secondary" icon={Power} onClick={toggle}>{branch.is_active ? 'Deactivate' : 'Activate'}</Button>
                        <Button href={`/branches/${branch.id}/edit`} icon={Pencil}>Edit branch</Button>
                    </>
                }
            >
                <div className="mt-2 flex items-center gap-2">
                    <span className="rounded-md bg-white px-2 py-0.5 font-mono text-xs ring-1 ring-line">{branch.code}</span>
                    <ActiveBadge active={branch.is_active} />
                </div>
            </PageHeader>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="space-y-6 xl:col-span-2">
                    <Card title="Campaigns" description="Lead counts per campaign in this branch" padded={false}>
                        {branch.campaigns.length === 0 ? (
                            <EmptyState title="No campaigns assigned" action={<Button variant="secondary" href={`/branches/${branch.id}/edit`}>Assign campaigns</Button>} />
                        ) : (
                            <ul className="divide-y divide-line">
                                {branch.campaigns.map((c) => (
                                    <li key={c.id} className="flex items-center justify-between px-5 py-3">
                                        <div className="flex items-center gap-3">
                                            <Link href={`/campaigns/${c.id}`} className="text-sm font-medium text-ink hover:text-primary-600">{c.name}</Link>
                                            {!c.is_active && <StatusBadge color="gray">Inactive</StatusBadge>}
                                        </div>
                                        <Link href={`/leads?branch_id=${branch.id}&campaign_id=${c.id}`} className="text-sm text-primary-600 hover:underline">{formatNumber(c.leads_count)} leads</Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card title="Users" description={`${users.length} user${users.length === 1 ? '' : 's'} in this branch`} padded={false}>
                        {users.length === 0 ? (
                            <EmptyState title="No users in this branch" action={<Button variant="secondary" href="/users/create">Add user</Button>} />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-line">
                                    <thead className="bg-subtle/80"><tr><th className="th">Name</th><th className="th">Role</th><th className="th">Campaigns</th><th className="th">Status</th></tr></thead>
                                    <tbody className="divide-y divide-line">
                                        {users.map((u) => (
                                            <tr key={u.id} className="hover:bg-subtle/60">
                                                <td className="td"><Link href={`/users/${u.id}`} className="font-medium text-ink hover:text-primary-600">{u.name}</Link><p className="text-xs text-muted">{u.email}</p></td>
                                                <td className="td text-gray-600">{u.role}</td>
                                                <td className="td text-gray-600">{u.campaigns.join(', ') || '—'}</td>
                                                <td className="td"><ActiveBadge active={u.is_active} /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>

                    <Card title="Recent leads" padded={false} actions={<Button variant="ghost" size="sm" href={`/leads?branch_id=${branch.id}`}>View all</Button>}>
                        {recentLeads.length === 0 ? (
                            <EmptyState icon={UsersRound} title="No leads yet" />
                        ) : (
                            <ul className="divide-y divide-line">
                                {recentLeads.map((l) => (
                                    <li key={l.id}>
                                        <Link href={`/leads/${l.id}`} className="flex items-center justify-between gap-4 px-5 py-3 hover:bg-subtle">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-ink">{l.name}</p>
                                                <p className="text-xs text-muted">{l.lead_number} · {l.campaign} · {l.assignee ?? 'Unassigned'}</p>
                                            </div>
                                            <StatusBadge color={l.lead_status?.color}>{l.lead_status?.name}</StatusBadge>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
                <div className="space-y-6">
                    <Card title="Details">
                        <DetailList items={[
                            { label: 'Address', value: branch.address, full: true },
                            { label: 'Contact person', value: branch.contact_person, full: true },
                            { label: 'Contact number', value: branch.contact_number, full: true },
                            { label: 'Email', value: branch.email, full: true },
                            { label: 'Total leads', value: formatNumber(branch.leads_count) },
                            { label: 'Users', value: formatNumber(branch.users_count) },
                            { label: 'Created', value: formatDate(branch.created_at), full: true },
                        ]} />
                    </Card>
                    <Card title="Public lead form">
                        {branch.public_form ? (
                            <div className="space-y-3 text-sm">
                                <div className="flex items-center justify-between"><span className="text-muted">Status</span><ActiveBadge active={branch.public_form.is_active} /></div>
                                <div className="flex items-center justify-between"><span className="text-muted">Submissions</span><span className="font-medium">{formatNumber(branch.public_form.submissions_count)}</span></div>
                                <Button variant="secondary" size="sm" href="/public-forms" icon={Globe} className="w-full">Manage public form</Button>
                            </div>
                        ) : (
                            <div className="text-sm text-muted">
                                No public URL yet.
                                <Button variant="secondary" size="sm" href="/public-forms" icon={Globe} className="mt-3 w-full">Generate public URL</Button>
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
