import { useMemo, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { ListChecks, Pencil, Plus, Settings2, Star } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import Card from '@/components/Card';
import Modal from '@/components/Modal';
import FormInput from '@/components/FormInput';
import CheckboxList from '@/components/CheckboxList';
import EmptyState from '@/components/EmptyState';
import Toggle from '@/components/Toggle';
import StatusBadge, { DOT_COLORS } from '@/components/StatusBadge';
import { cn, formatNumber } from '@/lib/utils';

function ColorPicker({ colors, value, onChange }) {
    return (
        <div>
            <span className="label">Colour</span>
            <div className="flex flex-wrap gap-1.5">
                {colors.map((c) => (
                    <button key={c} type="button" onClick={() => onChange(c)} title={c} aria-label={c} className={cn('flex h-7 w-7 items-center justify-center rounded-full ring-offset-2', value === c && 'ring-2 ring-primary-500')}>
                        <span className={cn('h-5 w-5 rounded-full', DOT_COLORS[c])} />
                    </button>
                ))}
            </div>
        </div>
    );
}

const campaignOptions = (campaigns) => campaigns.map((c) => ({ value: c.id, label: c.name, hint: c.is_active ? null : 'Inactive' }));

/** Create a status in many campaigns at once — or add an existing one to more campaigns. */
function CreateStatusModal({ open, onClose, type, campaigns, colors, preset }) {
    const form = useForm({ type, name: preset?.name ?? '', color: preset?.color ?? 'blue', is_default: false, campaign_ids: [] });
    const available = preset ? campaigns.filter((c) => !preset.campaigns.some((p) => p.campaign_id === c.id)) : campaigns;

    const submit = (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, type }));
        form.post('/statuses', { preserveScroll: true, onSuccess: () => (form.reset(), onClose()) });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title={preset ? `Add “${preset.name}” to more campaigns` : `New ${type === 'lead' ? 'lead' : 'payment'} status`}
            description="The status is created separately in each selected campaign, at the end of its list. Campaigns that already have a status with this name are skipped."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>Cancel</Button>
                    <Button type="submit" form="create-status" loading={form.processing} disabled={!form.data.name.trim() || !form.data.campaign_ids.length}>
                        {preset ? 'Add to campaigns' : 'Create status'}
                    </Button>
                </>
            }
        >
            <form id="create-status" onSubmit={submit} className="space-y-5">
                {!preset && (
                    <>
                        <FormInput label="Status name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} required autoFocus placeholder={type === 'lead' ? 'e.g. Callback Booked' : 'e.g. Deposit Paid'} />
                        <ColorPicker colors={colors} value={form.data.color} onChange={(c) => form.setData('color', c)} />
                    </>
                )}
                <CheckboxList label="Campaigns" required options={campaignOptions(available)} value={form.data.campaign_ids} onChange={(v) => form.setData('campaign_ids', v)} error={form.errors.campaign_ids} emptyText="Every campaign already has this status." columns={3} />
                <Toggle label="Make this the default status" description="New leads in the selected campaigns will start in this status." checked={form.data.is_default} onChange={(v) => form.setData('is_default', v)} />
            </form>
        </Modal>
    );
}

/** Rename / recolour / (de)activate a status in the campaigns you tick. */
function EditStatusModal({ group, onClose, type, colors }) {
    const allActive = group.campaigns.every((c) => c.is_active);
    const form = useForm({ type, name: group.name, color: group.color, is_active: allActive, status_ids: group.campaigns.map((c) => c.status_id) });

    const submit = (e) => {
        e.preventDefault();
        form.put('/statuses', { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal
            open
            onClose={onClose}
            size="lg"
            title={`Edit “${group.name}”`}
            description="Changes apply to the status in each campaign you select. Existing leads keep their status."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>Cancel</Button>
                    <Button type="submit" form="edit-status" loading={form.processing} disabled={!form.data.status_ids.length}>Save changes</Button>
                </>
            }
        >
            <form id="edit-status" onSubmit={submit} className="space-y-5">
                <FormInput label="Status name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} required />
                <ColorPicker colors={colors} value={form.data.color} onChange={(c) => form.setData('color', c)} />
                <Toggle label="Active" description="Inactive statuses cannot be chosen on lead forms. A campaign's default status always stays active." checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} />
                <CheckboxList
                    label="Apply to campaigns"
                    options={group.campaigns.map((c) => ({ value: c.status_id, label: c.campaign, hint: `${c.leads_count} leads${c.is_default ? ' · default' : ''}${c.is_active ? '' : ' · inactive'}` }))}
                    value={form.data.status_ids}
                    onChange={(v) => form.setData('status_ids', v)}
                    error={form.errors.status_ids}
                    columns={3}
                />
            </form>
        </Modal>
    );
}

export default function Index({ type, groups, campaigns, colors }) {
    const [creating, setCreating] = useState(false);
    const [adding, setAdding] = useState(null);
    const [editing, setEditing] = useState(null);
    const [campaignFilter, setCampaignFilter] = useState('');
    const label = type === 'lead' ? 'Lead statuses' : 'Payment statuses';

    const shown = useMemo(() => (campaignFilter ? groups.filter((g) => g.campaigns.some((c) => String(c.campaign_id) === campaignFilter)) : groups), [groups, campaignFilter]);

    return (
        <AppLayout title="Status Management" breadcrumbs={[{ label: 'Status Management' }]}>
            <PageHeader
                title="Status management"
                description="Create lead and payment statuses once and apply them to as many campaigns as you need. Each campaign keeps its own copy, so you can still fine-tune them per campaign."
                actions={<Button icon={Plus} onClick={() => setCreating(true)}>New {type === 'lead' ? 'lead' : 'payment'} status</Button>}
            />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex gap-1 rounded-lg border border-line bg-white p-1">
                    {[
                        ['lead', 'Lead statuses'],
                        ['payment', 'Payment statuses'],
                    ].map(([value, text]) => (
                        <button key={value} type="button" onClick={() => router.get('/statuses', { type: value }, { preserveScroll: true })} className={cn('rounded-md px-3 py-1.5 text-sm font-medium transition-colors', type === value ? 'bg-primary-500 text-white' : 'text-muted hover:bg-subtle hover:text-ink')}>
                            {text}
                        </button>
                    ))}
                </div>
                <select value={campaignFilter} onChange={(e) => setCampaignFilter(e.target.value)} className="input h-9 sm:w-56" aria-label="Filter by campaign">
                    <option value="">All campaigns</option>
                    {campaigns.map((c) => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                    ))}
                </select>
            </div>

            <Card title={label} description={`${shown.length} status${shown.length === 1 ? '' : 'es'} across ${campaigns.length} campaign${campaigns.length === 1 ? '' : 's'}`} padded={false}>
                {shown.length === 0 ? (
                    <EmptyState icon={ListChecks} title="No statuses yet" action={<Button icon={Plus} onClick={() => setCreating(true)}>New status</Button>} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-line">
                            <thead className="bg-subtle/80">
                                <tr>
                                    <th className="th">Status</th>
                                    <th className="th">Campaigns</th>
                                    <th className="th text-right">Leads</th>
                                    <th className="th"><span className="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {shown.map((g) => {
                                    const missing = campaigns.length - g.campaigns.length;
                                    return (
                                        <tr key={g.key} className="align-top hover:bg-subtle/50">
                                            <td className="td"><StatusBadge color={g.color}>{g.name}</StatusBadge></td>
                                            <td className="td whitespace-normal">
                                                <div className="flex max-w-xl flex-wrap gap-1.5">
                                                    {g.campaigns.map((c) => (
                                                        <Link key={c.status_id} href={`/campaigns/${c.campaign_id}/statuses`} title={`${c.leads_count} leads${c.is_active ? '' : ' · inactive'}`} className={cn('inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs ring-1 ring-inset hover:ring-primary-300', c.is_active ? 'bg-white text-ink ring-line' : 'bg-subtle text-muted line-through ring-line')}>
                                                            {c.is_default && <Star className="h-3 w-3 fill-amber-400 text-amber-400" />}
                                                            {c.campaign}
                                                        </Link>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="td text-right tabular-nums">{formatNumber(g.leads_count)}</td>
                                            <td className="td text-right">
                                                <div className="flex justify-end gap-1">
                                                    {missing > 0 && <Button variant="ghost" size="sm" icon={Plus} onClick={() => setAdding(g)}>Add to {missing} more</Button>}
                                                    <Button variant="ghost" size="sm" icon={Pencil} onClick={() => setEditing(g)}>Edit</Button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
            <p className="mt-3 flex items-center gap-1.5 text-xs text-muted">
                <Settings2 className="h-3.5 w-3.5" /> Click a campaign chip to reorder statuses or set its default. <Star className="ml-1 h-3 w-3 fill-amber-400 text-amber-400" /> marks the default status.
            </p>

            {creating && <CreateStatusModal open onClose={() => setCreating(false)} type={type} campaigns={campaigns} colors={colors} />}
            {adding && <CreateStatusModal key={adding.key} open onClose={() => setAdding(null)} type={type} campaigns={campaigns} colors={colors} preset={adding} />}
            {editing && <EditStatusModal key={editing.key} group={editing} onClose={() => setEditing(null)} type={type} colors={colors} />}
        </AppLayout>
    );
}
