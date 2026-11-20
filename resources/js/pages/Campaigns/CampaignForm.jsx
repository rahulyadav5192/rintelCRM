import { useMemo } from 'react';
import Card from '@/components/Card';
import FormInput from '@/components/FormInput';
import FormTextarea from '@/components/FormTextarea';
import Toggle from '@/components/Toggle';
import CheckboxList from '@/components/CheckboxList';

export default function CampaignForm({ form, branches, managers }) {
    const { data, setData, errors } = form;
    const selectedBranches = new Set(data.branch_ids.map(String));
    // Only managers of selected branches can work this campaign; others are shown disabled.
    const managerOptions = useMemo(
        () => managers.map((m) => ({ value: m.id, label: m.name, hint: `${m.branch ?? 'No branch'}${m.is_active ? '' : ' · inactive'}`, disabled: !selectedBranches.has(String(m.branch_id)) && !data.manager_ids.map(String).includes(String(m.id)) })),
        [managers, data.branch_ids, data.manager_ids], // eslint-disable-line react-hooks/exhaustive-deps
    );

    return (
        <div className="space-y-6">
            <Card title="Campaign details">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <FormInput label="Campaign name" name="name" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} required />
                    <FormInput label="Campaign code" name="code" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} error={errors.code} required hint="Short unique code, e.g. IVA or SOLAR" />
                    <FormTextarea label="Description" name="description" className="sm:col-span-2" rows={3} value={data.description ?? ''} onChange={(e) => setData('description', e.target.value)} error={errors.description} hint="Shown to visitors on public lead forms." />
                </div>
            </Card>
            <Card title="Branches" description="Branches where this campaign runs.">
                <CheckboxList options={branches.map((b) => ({ value: b.id, label: b.name, hint: b.is_active ? null : 'Inactive' }))} value={data.branch_ids} onChange={(v) => setData('branch_ids', v)} error={errors.branch_ids} columns={3} />
            </Card>
            <Card title="Managers" description="Managers who work this campaign. Only managers belonging to a selected branch can be added. Branch Admin access is configured on each user.">
                <CheckboxList options={managerOptions} value={data.manager_ids} onChange={(v) => setData('manager_ids', v)} error={errors.manager_ids} emptyText="No managers exist yet." />
            </Card>
            <Card title="Availability">
                <div className="space-y-5">
                    <Toggle label="Active" description="Inactive campaigns cannot receive new leads. Existing leads remain accessible." checked={data.is_active} onChange={(v) => setData('is_active', v)} />
                    <Toggle label="Available on public lead forms" description="Visitors can choose this campaign on the public form of any assigned branch." checked={data.is_public} onChange={(v) => setData('is_public', v)} />
                    <Toggle label="Show statuses on public forms" description="Let visitors pick a lead and payment status. When off, the campaign defaults are used." checked={data.public_show_statuses} disabled={!data.is_public} onChange={(v) => setData('public_show_statuses', v)} />
                </div>
            </Card>
        </div>
    );
}
