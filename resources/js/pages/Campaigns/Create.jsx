import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import CampaignForm from './CampaignForm';

export default function Create({ admins, managers }) {
    const form = useForm({ name: '', code: '', description: '', is_active: true, is_public: false, public_show_statuses: false, admin_ids: [], manager_ids: [] });
    return (
        <AppLayout title="New campaign" breadcrumbs={[{ label: 'Campaigns', href: '/campaigns' }, { label: 'New campaign' }]}>
            <form onSubmit={(e) => (e.preventDefault(), form.post('/campaigns'))} className="mx-auto max-w-4xl">
                <PageHeader title="New campaign" description="Default lead and payment statuses are created automatically — you can customise them next." />
                <CampaignForm form={form} admins={admins} managers={managers} />
                <div className="mt-6 flex justify-end gap-2">
                    <Button variant="secondary" href="/campaigns">Cancel</Button>
                    <Button type="submit" loading={form.processing}>Create campaign</Button>
                </div>
            </form>
        </AppLayout>
    );
}
