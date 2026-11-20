import { router, useForm } from '@inertiajs/react';
import { ArrowRightLeft, CircleDollarSign, FilePen, Mail, MessageSquare, Pencil, Phone, Plus, Trash2, UserPlus, Activity as ActivityIcon, Tag } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import Card, { DetailList } from '@/components/Card';
import StatusBadge from '@/components/StatusBadge';
import FormTextarea from '@/components/FormTextarea';
import EmptyState from '@/components/EmptyState';
import { useConfirm } from '@/components/ConfirmationDialog';
import { cn, formatDate, formatDateTime, timeAgo } from '@/lib/utils';

const ACTIVITY_ICONS = {
    created: [Plus, 'bg-primary-50 text-primary-600'],
    assigned: [UserPlus, 'bg-violet-50 text-violet-600'],
    reassigned: [ArrowRightLeft, 'bg-violet-50 text-violet-600'],
    status_changed: [Tag, 'bg-amber-50 text-amber-600'],
    payment_status_changed: [CircleDollarSign, 'bg-green-50 text-green-600'],
    updated: [FilePen, 'bg-gray-100 text-gray-600'],
    note_added: [MessageSquare, 'bg-sky-50 text-sky-600'],
};

export default function Show({ lead, can }) {
    const confirm = useConfirm();
    const note = useForm({ body: '' });

    const addNote = (e) => {
        e.preventDefault();
        note.post(`/leads/${lead.id}/notes`, { preserveScroll: true, onSuccess: () => note.reset() });
    };

    const destroy = async () => {
        if (await confirm({ title: `Delete lead ${lead.lead_number}?`, message: 'The lead will be removed from all listings. This action is logged.', confirmLabel: 'Delete lead', danger: true })) {
            router.delete(`/leads/${lead.id}`);
        }
    };

    return (
        <AppLayout title={lead.lead_number} breadcrumbs={[{ label: 'Leads', href: '/leads' }, { label: lead.lead_number }]}>
            <PageHeader
                title={lead.full_name}
                actions={
                    <>
                        {can.delete && (
                            <Button variant="secondary" icon={Trash2} onClick={destroy} className="text-red-600">
                                Delete
                            </Button>
                        )}
                        {can.update && (
                            <Button href={`/leads/${lead.id}/edit`} icon={Pencil}>
                                Edit lead
                            </Button>
                        )}
                    </>
                }
            >
                <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted">
                    <span className="rounded-md bg-white px-2 py-0.5 font-mono text-xs font-medium text-ink ring-1 ring-line">{lead.lead_number}</span>
                    <StatusBadge color={lead.lead_status?.color}>{lead.lead_status?.name}</StatusBadge>
                    {lead.payment_status && <StatusBadge color={lead.payment_status.color}>{lead.payment_status.name}</StatusBadge>}
                    {lead.source === 'public' && <StatusBadge color="sky">Public form</StatusBadge>}
                </div>
            </PageHeader>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="space-y-6 xl:col-span-2">
                    <Card title="Personal information">
                        <DetailList
                            items={[
                                { label: 'Full name', value: lead.full_name },
                                {
                                    label: 'Mobile',
                                    value: (
                                        <a href={`tel:${lead.mobile}`} className="inline-flex items-center gap-1.5 text-primary-600 hover:underline">
                                            <Phone className="h-3.5 w-3.5" />
                                            {lead.mobile}
                                        </a>
                                    ),
                                },
                                {
                                    label: 'Email',
                                    value: lead.email && (
                                        <a href={`mailto:${lead.email}`} className="inline-flex items-center gap-1.5 text-primary-600 hover:underline">
                                            <Mail className="h-3.5 w-3.5" />
                                            {lead.email}
                                        </a>
                                    ),
                                },
                                { label: 'House number', value: lead.house_number },
                                { label: 'Post code', value: lead.post_code },
                                { label: 'Lead date', value: formatDate(lead.lead_date) },
                            ]}
                        />
                    </Card>

                    <Card title="Notes" description="Initial notes captured with the lead">
                        {lead.notes ? <p className="text-sm whitespace-pre-line text-gray-700">{lead.notes}</p> : <p className="text-sm text-muted">No notes were captured.</p>}
                    </Card>

                    <Card title="Activity" description="Full history of changes to this lead" padded={false}>
                        {can.addNote && (
                            <form onSubmit={addNote} className="border-b border-line p-5">
                                <FormTextarea name="body" rows={3} placeholder="Add a note — e.g. call outcome or next steps…" value={note.data.body} onChange={(e) => note.setData('body', e.target.value)} error={note.errors.body} />
                                <div className="mt-2 flex justify-end">
                                    <Button type="submit" size="sm" loading={note.processing} disabled={!note.data.body.trim()} icon={MessageSquare}>
                                        Add note
                                    </Button>
                                </div>
                            </form>
                        )}
                        <Timeline lead={lead} />
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card title="Assignment">
                        <DetailList
                            items={[
                                { label: 'Branch', value: lead.branch, full: true },
                                { label: 'Campaign', value: lead.campaign?.name, full: true },
                                { label: 'Advisor', value: lead.assignee ? lead.assignee.name : <span className="text-muted italic">Unassigned</span>, full: true },
                                { label: 'Follow-up date', value: lead.follow_up_date ? formatDate(lead.follow_up_date) : null, full: true },
                            ]}
                        />
                    </Card>
                    <Card title="Record">
                        <DetailList
                            items={[
                                { label: 'Created by', value: lead.creator, full: true },
                                { label: 'Created', value: formatDateTime(lead.created_at), full: true },
                                { label: 'Last updated', value: `${formatDateTime(lead.updated_at)}${lead.updated_by_name ? ` by ${lead.updated_by_name}` : ''}`, full: true },
                            ]}
                        />
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function Timeline({ lead }) {
    if (!lead.activities.length) return <EmptyState icon={ActivityIcon} title="No activity yet" />;

    return (
        <ol className="p-5">
            {lead.activities.map((a, i) => {
                const [Icon, tone] = ACTIVITY_ICONS[a.type] ?? [ActivityIcon, 'bg-gray-100 text-gray-600'];
                const noteBody = a.type === 'note_added' ? a.new_values?.note : null;
                return (
                    <li key={a.id} className="relative flex gap-3 pb-6 last:pb-0">
                        {i < lead.activities.length - 1 && <span className="absolute top-8 bottom-0 left-4 w-px bg-line" />}
                        <span className={cn('relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white', tone)}>
                            <Icon className="h-4 w-4" />
                        </span>
                        <div className="min-w-0 flex-1 pt-1">
                            <p className="text-sm text-ink">
                                {a.description} <span className="text-muted">by {a.user}</span>
                            </p>
                            {noteBody && <p className="mt-2 rounded-lg border border-line bg-subtle px-3 py-2 text-sm whitespace-pre-line text-gray-700">{noteBody}</p>}
                            {a.type === 'updated' && a.new_values && (
                                <div className="mt-2 space-y-1 rounded-lg border border-line bg-subtle px-3 py-2 text-xs">
                                    {Object.keys(a.new_values).map((k) => (
                                        <p key={k} className="text-gray-600">
                                            <span className="font-medium text-ink capitalize">{k.replace(/_/g, ' ')}:</span> <span className="line-through opacity-60">{String(a.old_values?.[k] ?? '—')}</span> → {String(a.new_values[k] ?? '—')}
                                        </p>
                                    ))}
                                </div>
                            )}
                            <p className="mt-1 text-xs text-muted" title={formatDateTime(a.created_at)}>
                                {formatDateTime(a.created_at)} · {timeAgo(a.created_at)}
                            </p>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
