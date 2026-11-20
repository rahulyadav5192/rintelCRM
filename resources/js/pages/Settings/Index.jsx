import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Card from '@/components/Card';
import Button from '@/components/Button';
import FormInput from '@/components/FormInput';
import FormTextarea from '@/components/FormTextarea';

export default function Index({ settings }) {
    const form = useForm(settings);
    const { data, setData, errors } = form;
    return (
        <AppLayout title="Settings" breadcrumbs={[{ label: 'Settings' }]}>
            <form onSubmit={(e) => (e.preventDefault(), form.put('/settings', { preserveScroll: true }))} className="mx-auto max-w-3xl space-y-6">
                <PageHeader title="Settings" description="System-wide configuration." />
                <Card title="Lead IDs" description="Applies to leads created from now on. Existing lead IDs never change.">
                    <FormInput label="Lead ID prefix" name="lead_number_prefix" value={data.lead_number_prefix} onChange={(e) => setData('lead_number_prefix', e.target.value.toUpperCase())} error={errors.lead_number_prefix} required maxLength={6} hint={`Example: ${data.lead_number_prefix || 'RL'}-000123`} className="max-w-xs" />
                </Card>
                <Card title="Public lead form" description="Text shown to visitors on every branch's public form.">
                    <div className="space-y-5">
                        <FormInput label="Heading" name="public_form_heading" value={data.public_form_heading} onChange={(e) => setData('public_form_heading', e.target.value)} error={errors.public_form_heading} required />
                        <FormTextarea label="Introduction" name="public_form_intro" rows={2} value={data.public_form_intro ?? ''} onChange={(e) => setData('public_form_intro', e.target.value)} error={errors.public_form_intro} />
                        <FormTextarea label="Success message" name="public_form_success" rows={2} value={data.public_form_success} onChange={(e) => setData('public_form_success', e.target.value)} error={errors.public_form_success} required />
                    </div>
                </Card>
                <div className="flex justify-end">
                    <Button type="submit" loading={form.processing} disabled={!form.isDirty}>Save settings</Button>
                </div>
            </form>
        </AppLayout>
    );
}
