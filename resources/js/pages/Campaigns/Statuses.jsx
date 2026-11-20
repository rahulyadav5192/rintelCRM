import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Check, Pencil, Plus, Star, X } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import Card from '@/components/Card';
import StatusBadge, { DOT_COLORS } from '@/components/StatusBadge';
import { useConfirm } from '@/components/ConfirmationDialog';
import { cn } from '@/lib/utils';

function ColorPicker({ colors, value, onChange }) {
    return (
        <div className="flex flex-wrap gap-1.5">
            {colors.map((c) => (
                <button key={c} type="button" onClick={() => onChange(c)} title={c} aria-label={c} className={cn('flex h-6 w-6 items-center justify-center rounded-full ring-offset-2', value === c && 'ring-2 ring-primary-500')}>
                    <span className={cn('h-4 w-4 rounded-full', DOT_COLORS[c])} />
                </button>
            ))}
        </div>
    );
}

function StatusRow({ status, index, count, type, campaignId, colors, onMove }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ name: status.name, color: status.color, is_default: status.is_default, is_active: status.is_active });
    const confirm = useConfirm();
    const url = `/campaigns/${campaignId}/statuses/${type}/${status.id}`;

    const save = (overrides = {}) => {
        form.transform((d) => ({ ...d, ...overrides }));
        form.put(url, { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    const toggleActive = async () => {
        if (status.is_active && !(await confirm({ title: `Deactivate "${status.name}"?`, message: `It will no longer be selectable on lead forms. ${status.leads_count} existing lead(s) keep this status.`, confirmLabel: 'Deactivate', danger: true }))) return;
        save({ is_active: !status.is_active, is_default: status.is_active ? false : status.is_default });
    };

    return (
        <li className={cn('flex flex-col gap-3 px-5 py-3 sm:flex-row sm:items-center', !status.is_active && 'bg-subtle/60')}>
            <div className="flex shrink-0 flex-row gap-0.5 sm:flex-col">
                <button type="button" disabled={index === 0} onClick={() => onMove(index, -1)} className="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-ink disabled:opacity-30" aria-label="Move up"><ArrowUp className="h-3.5 w-3.5" /></button>
                <button type="button" disabled={index === count - 1} onClick={() => onMove(index, 1)} className="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-ink disabled:opacity-30" aria-label="Move down"><ArrowDown className="h-3.5 w-3.5" /></button>
            </div>
            {editing ? (
                <form className="flex flex-1 flex-col gap-3 lg:flex-row lg:items-center" onSubmit={(e) => (e.preventDefault(), save())}>
                    <div className="lg:w-56">
                        <input className={cn('input h-9', form.errors.name && 'input-error')} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
                        {form.errors.name && <p className="mt-1 text-xs text-red-600">{form.errors.name}</p>}
                    </div>
                    <ColorPicker colors={colors} value={form.data.color} onChange={(c) => form.setData('color', c)} />
                    <div className="flex gap-1 lg:ml-auto">
                        <Button type="submit" size="sm" icon={Check} loading={form.processing}>Save</Button>
                        <Button variant="ghost" size="sm" icon={X} onClick={() => (form.reset(), setEditing(false))}>Cancel</Button>
                    </div>
                </form>
            ) : (
                <>
                    <div className="flex flex-1 items-center gap-3">
                        <StatusBadge color={status.is_active ? status.color : 'gray'}>{status.name}</StatusBadge>
                        {status.is_default && <span className="inline-flex items-center gap-1 text-xs font-medium text-amber-600"><Star className="h-3 w-3 fill-current" /> Default</span>}
                        {!status.is_active && <span className="text-xs text-muted">Inactive</span>}
                        <span className="text-xs text-muted">{status.leads_count} lead{status.leads_count === 1 ? '' : 's'}</span>
                    </div>
                    <div className="flex gap-1">
                        {!status.is_default && status.is_active && <Button variant="ghost" size="sm" onClick={() => save({ is_default: true })}>Make default</Button>}
                        <Button variant="ghost" size="sm" icon={Pencil} onClick={() => setEditing(true)}>Edit</Button>
                        {!status.is_default && <Button variant={status.is_active ? 'danger-ghost' : 'ghost'} size="sm" onClick={toggleActive}>{status.is_active ? 'Deactivate' : 'Activate'}</Button>}
                    </div>
                </>
            )}
        </li>
    );
}

function StatusSection({ title, description, type, statuses, campaignId, colors }) {
    const [items, setItems] = useState(statuses);
    useEffect(() => setItems(statuses), [statuses]);
    const form = useForm({ name: '', color: 'blue', is_default: false });

    const move = (index, dir) => {
        const next = [...items];
        [next[index], next[index + dir]] = [next[index + dir], next[index]];
        setItems(next);
        router.post(`/campaigns/${campaignId}/statuses/${type}/reorder`, { ids: next.map((s) => s.id) }, { preserveScroll: true, preserveState: true });
    };

    const add = (e) => {
        e.preventDefault();
        form.post(`/campaigns/${campaignId}/statuses/${type}`, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <Card title={title} description={description} padded={false}>
            <ul className="divide-y divide-line">
                {items.map((s, i) => (
                    <StatusRow key={s.id} status={s} index={i} count={items.length} type={type} campaignId={campaignId} colors={colors} onMove={move} />
                ))}
            </ul>
            <form onSubmit={add} className="flex flex-col gap-3 border-t border-line bg-subtle/60 px-5 py-4 lg:flex-row lg:items-start">
                <div className="lg:w-56">
                    <input className={cn('input h-9', form.errors.name && 'input-error')} placeholder="New status name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    {form.errors.name && <p className="mt-1 text-xs text-red-600">{form.errors.name}</p>}
                </div>
                <div className="pt-1.5"><ColorPicker colors={colors} value={form.data.color} onChange={(c) => form.setData('color', c)} /></div>
                <Button type="submit" size="sm" icon={Plus} loading={form.processing} disabled={!form.data.name.trim()} className="lg:ml-auto">Add status</Button>
            </form>
        </Card>
    );
}

export default function Statuses({ campaign, leadStatuses, paymentStatuses, colors }) {
    return (
        <AppLayout title={`${campaign.name} statuses`} breadcrumbs={[{ label: 'Campaigns', href: '/campaigns' }, { label: campaign.name, href: `/campaigns/${campaign.id}` }, { label: 'Statuses' }]}>
            <div className="mx-auto max-w-4xl">
                <PageHeader title={`${campaign.name} statuses`} description="Statuses are specific to this campaign. Deactivate statuses instead of deleting them so historical leads keep their values." />
                <div className="space-y-6">
                    <StatusSection title="Lead statuses" description="The pipeline stages a lead moves through. New leads start in the default status." type="lead" statuses={leadStatuses} campaignId={campaign.id} colors={colors} />
                    <StatusSection title="Payment statuses" description="Payment progress for leads in this campaign." type="payment" statuses={paymentStatuses} campaignId={campaign.id} colors={colors} />
                </div>
            </div>
        </AppLayout>
    );
}
