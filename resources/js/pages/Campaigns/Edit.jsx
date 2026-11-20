import { useForm } from '@inertiajs/react';
import { ListChecks } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import CampaignForm from './CampaignForm';

export default function Edit({ campaign, branches, managers }) {
    const form = useForm({ ...campaign });
    return (
        <AppLayout title={`Edit ${campaign.name}`} breadcrumbs={[{ label: 'Campaigns', href: '/campaigns' }, { label: campaign.name, href: `/campaigns/${campaign.id}` }, { label: 'Edit' }]}>
            <form onSubmit={(e) => (e.preventDefault(), form.put(`/campaigns/${campaign.id}`))} className="mx-auto max-w-4xl">
                <PageHeader title={`Edit ${campaign.name}`} actions={<Button variant="secondary" href={`/campaigns/${campaign.id}/statuses`} icon={ListChecks}>Configure statuses</Button>} />
                <CampaignForm form={form} branches={branches} managers={managers} />
                <div className="mt-6 flex justify-end gap-2">
                    <Button variant="secondary" href={`/campaigns/${campaign.id}`}>Cancel</Button>
                    <Button type="submit" loading={form.processing} disabled={!form.isDirty}>Save changes</Button>
                </div>
            </form>
        </AppLayout>
    );
}
