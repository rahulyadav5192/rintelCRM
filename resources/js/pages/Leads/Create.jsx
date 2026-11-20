import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import LeadForm from '@/components/LeadForm';

export default function Create({ branches, titles, branchLocked, canSetDate, canAssign, currentUserId, previewLeadNumber, defaults }) {
    const firstBranch = defaults.branch_id ?? (branches.length === 1 ? branches[0].id : '');
    const preselectedCampaign = branches.find((b) => b.id === firstBranch)?.campaigns.some((c) => c.id === defaults.campaign_id) ? defaults.campaign_id : '';

    const form = useForm({
        lead_date: defaults.lead_date,
        title: '',
        first_name: '',
        last_name: '',
        mobile: '',
        email: '',
        house_number: '',
        post_code: '',
        branch_id: firstBranch,
        campaign_id: preselectedCampaign,
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
                <LeadForm form={form} branches={branches} titles={titles} branchLocked={branchLocked} canSetDate={canSetDate} canAssign={canAssign} currentUserId={currentUserId} leadNumber={previewLeadNumber} isPreview />
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
