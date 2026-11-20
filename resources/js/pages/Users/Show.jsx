import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { KeyRound, Pencil, Power, UsersRound } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import Card, { DetailList } from '@/components/Card';
import StatusBadge, { ActiveBadge } from '@/components/StatusBadge';
import DataTable from '@/components/DataTable';
import EmptyState from '@/components/EmptyState';
import Modal from '@/components/Modal';
import FormInput from '@/components/FormInput';
import { useConfirm } from '@/components/ConfirmationDialog';
import { formatDate, formatDateTime, timeAgo } from '@/lib/utils';

export default function Show({ user, assignedLeads, leadActivity, systemActivity, can }) {
    const confirm = useConfirm();
    const [resetOpen, setResetOpen] = useState(false);
    const pw = useForm({ password: '', password_confirmation: '' });

    const toggle = async () => {
        if (await confirm({ title: `${user.is_active ? 'Deactivate' : 'Activate'} ${user.name}?`, message: user.is_active ? 'They will be signed out and unable to log in. Their leads are preserved.' : 'They will be able to sign in again.', confirmLabel: user.is_active ? 'Deactivate' : 'Activate', danger: user.is_active })) {
            router.patch(`/users/${user.id}/toggle`, {}, { preserveScroll: true });
        }
    };
    const resetPassword = (e) => {
        e.preventDefault();
        pw.put(`/users/${user.id}/password`, { preserveScroll: true, onSuccess: () => (pw.reset(), setResetOpen(false)) });
    };
    const activity = [...leadActivity, ...systemActivity].sort((a, b) => (a.created_at < b.created_at ? 1 : -1)).slice(0, 20);

    return (
        <AppLayout title={user.name} breadcrumbs={[{ label: 'Users', href: '/users' }, { label: user.name }]}>
            <PageHeader
                title={user.name}
                actions={
                    can.update && (
                        <>
                            <Button variant="secondary" icon={KeyRound} onClick={() => setResetOpen(true)}>Reset password</Button>
                            {can.toggle && <Button variant="secondary" icon={Power} onClick={toggle}>{user.is_active ? 'Deactivate' : 'Activate'}</Button>}
                            <Button href={`/users/${user.id}/edit`} icon={Pencil}>Edit user</Button>
                        </>
                    )
                }
            >
                <div className="mt-2 flex items-center gap-2 text-sm text-muted">
                    <StatusBadge color="blue" dot={false}>{user.role_label}</StatusBadge>
                    <ActiveBadge active={user.is_active} />
                    <span>{user.email}</span>
                </div>
            </PageHeader>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="space-y-6 xl:col-span-2">
                    <DataTable
                        rows={assignedLeads.data}
                        meta={assignedLeads}
                        rowHref={(l) => `/leads/${l.id}`}
                        toolbar={<p className="px-1 text-sm font-semibold text-ink">Assigned leads <span className="font-normal text-muted">({assignedLeads.total})</span></p>}
                        empty={<EmptyState icon={UsersRound} title="No leads assigned" />}
                        columns={[
                            { key: 'lead_number', label: 'Lead ID', render: (l) => <Link href={`/leads/${l.id}`} className="font-medium text-primary-600">{l.lead_number}</Link> },
                            { key: 'name', label: 'Name' },
                            { key: 'campaign', label: 'Campaign' },
                            { key: 'lead_date', label: 'Date', render: (l) => formatDate(l.lead_date) },
                            { key: 'lead_status', label: 'Status', render: (l) => <StatusBadge color={l.lead_status?.color}>{l.lead_status?.name}</StatusBadge> },
                            { key: 'payment_status', label: 'Payment', render: (l) => <StatusBadge color={l.payment_status?.color}>{l.payment_status?.name}</StatusBadge> },
                        ]}
                    />
                    <Card title="Recent activity" padded={false}>
                        {activity.length === 0 ? (
                            <EmptyState title="No recorded activity" />
                        ) : (
                            <ul className="divide-y divide-line">
                                {activity.map((a) => (
                                    <li key={a.id} className="px-5 py-3">
                                        <p className="text-sm text-ink">{a.lead_id ? <Link href={`/leads/${a.lead_id}`} className="font-medium text-primary-600">{a.lead_number}: </Link> : null}{a.description}</p>
                                        <p className="mt-0.5 text-xs text-muted" title={formatDateTime(a.created_at)}>{a.module ? `${a.module} · ` : ''}{timeAgo(a.created_at)}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
                <div className="space-y-6">
                    <Card title="Profile">
                        <DetailList items={[
                            { label: 'Mobile', value: user.mobile, full: true },
                            { label: 'Branch', value: user.branch ?? (user.role === 'super_admin' ? 'All branches' : null), full: true },
                            user.role === 'manager' && { label: 'Lead visibility', value: user.lead_visibility === 'assigned' ? 'Only assigned leads' : 'All branch leads in campaigns', full: true },
                            { label: 'Last login', value: user.last_login_at ? formatDateTime(user.last_login_at) : 'Never', full: true },
                            { label: 'Created', value: formatDate(user.created_at), full: true },
                        ]} />
                    </Card>
                    <Card title="Campaign access">
                        {user.role === 'super_admin' ? <p className="text-sm text-muted">All campaigns</p> : user.campaigns.length ? (
                            <div className="flex flex-wrap gap-2">{user.campaigns.map((c) => <StatusBadge key={c.id} color="blue" dot={false}>{c.name}</StatusBadge>)}</div>
                        ) : <p className="text-sm text-muted">No campaigns assigned.</p>}
                    </Card>
                    <Card title="Permissions">
                        {user.role === 'super_admin' ? <p className="text-sm text-muted">Unrestricted access</p> : user.permissions.length ? (
                            <ul className="space-y-1.5 text-sm text-gray-700">{user.permissions.map((p) => <li key={p} className="flex items-center gap-2"><span className="h-1.5 w-1.5 rounded-full bg-green-500" />{p}</li>)}</ul>
                        ) : <p className="text-sm text-muted">View and add notes only.</p>}
                    </Card>
                </div>
            </div>

            <Modal open={resetOpen} onClose={() => setResetOpen(false)} title={`Reset password for ${user.name}`} description="They will be signed out of all sessions." size="sm"
                footer={<><Button variant="secondary" onClick={() => setResetOpen(false)}>Cancel</Button><Button type="submit" form="reset-pw" loading={pw.processing}>Reset password</Button></>}>
                <form id="reset-pw" onSubmit={resetPassword} className="space-y-4">
                    <FormInput label="New password" name="password" type="password" value={pw.data.password} onChange={(e) => pw.setData('password', e.target.value)} error={pw.errors.password} required autoComplete="new-password" />
                    <FormInput label="Confirm password" name="password_confirmation" type="password" value={pw.data.password_confirmation} onChange={(e) => pw.setData('password_confirmation', e.target.value)} required autoComplete="new-password" />
                </form>
            </Modal>
        </AppLayout>
    );
}
