import { Link } from '@inertiajs/react';
import { ListChecks, Pencil, UsersRound } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import Card, { DetailList } from '@/components/Card';
import StatusBadge, { ActiveBadge } from '@/components/StatusBadge';
import EmptyState from '@/components/EmptyState';
import { formatDate, formatNumber } from '@/lib/utils';

export default function Show({ campaign, users, canManage }) {
    return (
        <AppLayout title={campaign.name} breadcrumbs={[{ label: 'Campaigns', href: '/campaigns' }, { label: campaign.name }]}>
            <PageHeader
                title={campaign.name}
                description={campaign.description}
                actions={
                    <>
                        <Button variant="secondary" href={`/leads?campaign_id=${campaign.id}`} icon={UsersRound}>View leads</Button>
                        {canManage && <Button variant="secondary" href={`/campaigns/${campaign.id}/statuses`} icon={ListChecks}>Statuses</Button>}
                        {canManage && <Button href={`/campaigns/${campaign.id}/edit`} icon={Pencil}>Edit campaign</Button>}
                    </>
                }
            >
                <div className="mt-2 flex items-center gap-2">
                    <span className="rounded-md bg-white px-2 py-0.5 font-mono text-xs ring-1 ring-line">{campaign.code}</span>
                    <ActiveBadge active={campaign.is_active} />
                    {campaign.is_public && <StatusBadge color="sky">Public form</StatusBadge>}
                </div>
            </PageHeader>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="space-y-6 xl:col-span-2">
                    <Card title="Lead statuses" description="Accessible leads currently in each status" padded={false}>
                        <ul className="divide-y divide-line">
                            {campaign.lead_statuses.map((s) => (
                                <li key={s.id} className="flex items-center justify-between px-5 py-3">
                                    <div className="flex items-center gap-2">
                                        <StatusBadge color={s.color}>{s.name}</StatusBadge>
                                        {s.is_default && <span className="text-xs text-muted">Default</span>}
                                        {!s.is_active && <span className="text-xs text-muted">· Inactive</span>}
                                    </div>
                                    <Link href={`/leads?campaign_id=${campaign.id}&lead_status_id=${s.id}`} className="text-sm text-primary-600 hover:underline">{formatNumber(s.leads_count)}</Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                    <Card title="Users" description="Admins and managers with access to this campaign" padded={false}>
                        {users.length === 0 ? (
                            <EmptyState title="No users assigned" />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-line">
                                    <thead className="bg-subtle/80"><tr><th className="th">Name</th><th className="th">Role</th><th className="th">Status</th></tr></thead>
                                    <tbody className="divide-y divide-line">
                                        {users.map((u) => (
                                            <tr key={u.id}>
                                                <td className="td"><p className="font-medium text-ink">{u.name}</p><p className="text-xs text-muted">{u.email}</p></td>
                                                <td className="td text-gray-600">{u.role}</td>
                                                <td className="td"><ActiveBadge active={u.is_active} /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                </div>
                <div className="space-y-6">
                    <Card title="Overview">
                        <DetailList items={[
                            { label: 'Accessible leads', value: formatNumber(campaign.leads_count) },
                            { label: 'Created', value: formatDate(campaign.created_at) },
                            { label: 'Public statuses', value: campaign.public_show_statuses ? 'Visible to visitors' : 'Hidden (defaults used)', full: true },
                        ]} />
                    </Card>
                    <Card title="Payment statuses">
                        <div className="flex flex-wrap gap-2">
                            {campaign.payment_statuses.map((s) => (
                                <StatusBadge key={s.id} color={s.is_active ? s.color : 'gray'}>{s.name}{s.is_default ? ' · default' : ''}</StatusBadge>
                            ))}
                        </div>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
