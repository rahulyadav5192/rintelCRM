import Card from '@/components/Card';
import FormInput from '@/components/FormInput';
import FormTextarea from '@/components/FormTextarea';
import Toggle from '@/components/Toggle';
import CheckboxList from '@/components/CheckboxList';

export default function BranchForm({ form, campaigns }) {
    const { data, setData, errors } = form;
    return (
        <div className="space-y-6">
            <Card title="Branch details">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <FormInput label="Branch name" name="name" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} required />
                    <FormInput label="Branch code" name="code" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} error={errors.code} required hint="Unique short code, e.g. MAIN or LDN-01" />
                    <FormTextarea label="Address" name="address" className="sm:col-span-2" rows={2} value={data.address ?? ''} onChange={(e) => setData('address', e.target.value)} error={errors.address} />
                </div>
            </Card>
            <Card title="Contact">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <FormInput label="Contact person" name="contact_person" value={data.contact_person ?? ''} onChange={(e) => setData('contact_person', e.target.value)} error={errors.contact_person} />
                    <FormInput label="Contact number" name="contact_number" type="tel" value={data.contact_number ?? ''} onChange={(e) => setData('contact_number', e.target.value)} error={errors.contact_number} />
                    <FormInput label="Email" name="email" type="email" value={data.email ?? ''} onChange={(e) => setData('email', e.target.value)} error={errors.email} />
                </div>
            </Card>
            <Card title="Campaigns" description="Campaigns that operate in this branch. Leads in this branch can only use these campaigns.">
                <CheckboxList options={campaigns.map((c) => ({ value: c.id, label: c.name, hint: c.is_active ? null : 'Inactive' }))} value={data.campaign_ids} onChange={(v) => setData('campaign_ids', v)} error={errors.campaign_ids} columns={3} />
            </Card>
            <Card title="Status">
                <Toggle label="Active" description="Inactive branches cannot receive new leads (including public submissions). Existing leads stay accessible." checked={data.is_active} onChange={(v) => setData('is_active', v)} />
            </Card>
        </div>
    );
}
