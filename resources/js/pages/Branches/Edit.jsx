import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import BranchForm from './BranchForm';

export default function Edit({ branch, campaigns }) {
    const form = useForm({ ...branch, campaign_ids: branch.campaign_ids ?? [] });
    return (
        <AppLayout title={`Edit ${branch.name}`} breadcrumbs={[{ label: 'Branches', href: '/branches' }, { label: branch.name, href: `/branches/${branch.id}` }, { label: 'Edit' }]}>
            <form onSubmit={(e) => (e.preventDefault(), form.put(`/branches/${branch.id}`))} className="mx-auto max-w-4xl">
                <PageHeader title={`Edit ${branch.name}`} />
                <BranchForm form={form} campaigns={campaigns} />
                <div className="mt-6 flex justify-end gap-2">
                    <Button variant="secondary" href={`/branches/${branch.id}`}>Cancel</Button>
                    <Button type="submit" loading={form.processing} disabled={!form.isDirty}>Save changes</Button>
                </div>
            </form>
        </AppLayout>
    );
}
