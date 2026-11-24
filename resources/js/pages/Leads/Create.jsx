import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import LeadForm from '@/components/LeadForm';

export default function Create({ campaigns, titles, canChooseOwner, canSetDate, canAssign, currentUserId, previewLeadNumber, defaults }) {
    const preselectedCampaign = campaigns.some((c) => c.id === defaults.campaign_id) ? defaults.campaign_id : campaigns.length === 1 ? campaigns[0].id : '';

    const form = useForm({
        lead_date: defaults.lead_date,
        title: '',
        first_name: '',
        last_name: '',
        mobile: '',
        email: '',
        house_number: '',
        post_code: '',
        campaign_id: preselectedCampaign,
        admin_id: '',
        assigned_to: '',
        lead_status_id: '',
        payment_status_id: '',
        follow_up_date: '',
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/leads');
    };

    return (
        <AppLayout title="New lead" breadcrumbs={[{ label: 'Leads', href: '/leads' }, { label: 'New lead' }]}>
            <form onSubmit={submit} className="mx-auto max-w-4xl">
                <PageHeader title="New lead" description="Capture a new enquiry and assign it to an advisor." />
                <LeadForm form={form} campaigns={campaigns} titles={titles} canChooseOwner={canChooseOwner} canSetDate={canSetDate} canAssign={canAssign} currentUserId={currentUserId} leadNumber={previewLeadNumber} isPreview />
                <div className="sticky bottom-0 mt-6 flex justify-end gap-2 border-t border-line bg-subtle/95 py-4 backdrop-blur">
                    <Button variant="secondary" href="/leads">
                        Cancel
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        Create lead
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
