import Card from '@/components/Card';
import FormInput from '@/components/FormInput';
import FormTextarea from '@/components/FormTextarea';
import Toggle from '@/components/Toggle';
import CheckboxList from '@/components/CheckboxList';

export default function CampaignForm({ form, admins, managers }) {
    const { data, setData, errors } = form;
    const userOptions = (list) => list.map((u) => ({ value: u.id, label: u.name, hint: `${u.email}${u.is_active ? '' : ' · inactive'}` }));

    return (
        <div className="space-y-6">
            <Card title="Campaign details">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <FormInput label="Campaign name" name="name" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} required />
                    <FormInput label="Campaign code" name="code" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} error={errors.code} required hint="Short unique code, e.g. IVA or SOLAR" />
                    <FormTextarea label="Description" name="description" className="sm:col-span-2" rows={3} value={data.description ?? ''} onChange={(e) => setData('description', e.target.value)} error={errors.description} hint="Shown to visitors on public lead forms." />
                </div>
            </Card>
            <Card title="Admins" description="Admins who can own leads in this campaign. It also appears on their public lead form when public forms are enabled below.">
                <CheckboxList options={userOptions(admins)} value={data.admin_ids} onChange={(v) => setData('admin_ids', v)} error={errors.admin_ids} emptyText="No admins exist yet." />
            </Card>
            <Card title="Managers" description="Managers who work this campaign's leads and can be chosen as advisors.">
                <CheckboxList options={userOptions(managers)} value={data.manager_ids} onChange={(v) => setData('manager_ids', v)} error={errors.manager_ids} emptyText="No managers exist yet." />
            </Card>
            <Card title="Availability">
                <div className="space-y-5">
                    <Toggle label="Active" description="Inactive campaigns cannot receive new leads. Existing leads remain accessible." checked={data.is_active} onChange={(v) => setData('is_active', v)} />
                    <Toggle label="Available on public lead forms" description="Visitors can choose this campaign on the public form of every admin assigned to it." checked={data.is_public} onChange={(v) => setData('is_public', v)} />
                    <Toggle label="Show statuses on public forms" description="Let visitors pick a lead and payment status. When off, the campaign defaults are used." checked={data.public_show_statuses} disabled={!data.is_public} onChange={(v) => setData('public_show_statuses', v)} />
                </div>
            </Card>
        </div>
    );
}
