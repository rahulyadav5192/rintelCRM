import { useForm } from '@inertiajs/react';
import { Info } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import LeadForm from '@/components/LeadForm';

export default function Edit({ lead, campaigns, titles, canChooseOwner, canSetDate, canAssign, currentUserId, abilities, currentOptions }) {
    const form = useForm({
        lead_date: lead.lead_date ?? '',
        title: lead.title ?? '',
        first_name: lead.first_name ?? '',
        last_name: lead.last_name ?? '',
        mobile: lead.mobile ?? '',
        email: lead.email ?? '',
        house_number: lead.house_number ?? '',
        post_code: lead.post_code ?? '',
        admin_id: lead.admin_id ?? '',
        campaign_id: lead.campaign_id,
        assigned_to: lead.assigned_to ?? '',
        lead_status_id: lead.lead_status_id ?? '',
        payment_status_id: lead.payment_status_id ?? '',
        follow_up_date: lead.follow_up_date ?? '',
        notes: lead.notes ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(`/leads/${lead.id}`);
    };

    const limited = !abilities.editDetails;

    return (
        <AppLayout title={`Edit ${lead.lead_number}`} breadcrumbs={[{ label: 'Leads', href: '/leads' }, { label: lead.lead_number, href: `/leads/${lead.id}` }, { label: 'Edit' }]}>
            <form onSubmit={submit} className="mx-auto max-w-4xl">
                <PageHeader title={`Edit lead ${lead.lead_number}`} description={`${lead.first_name} ${lead.last_name ?? ''}`} />
                {limited && (
                    <div className="mb-6 flex items-start gap-2 rounded-lg border border-primary-200 bg-primary-50 p-3 text-sm text-primary-800">
                        <Info className="mt-0.5 h-4 w-4 shrink-0" />
                        You can update only the fields your permissions allow. Other fields are read-only.
                    </div>
                )}
                <LeadForm form={form} campaigns={campaigns} titles={titles} canChooseOwner={canChooseOwner} canSetDate={canSetDate} canAssign={canAssign} currentUserId={currentUserId} leadNumber={lead.lead_number} abilities={abilities} currentOptions={currentOptions} />
                <div className="sticky bottom-0 mt-6 flex justify-end gap-2 border-t border-line bg-subtle/95 py-4 backdrop-blur">
                    <Button variant="secondary" href={`/leads/${lead.id}`}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={form.processing} disabled={!form.isDirty}>
                        Save changes
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
