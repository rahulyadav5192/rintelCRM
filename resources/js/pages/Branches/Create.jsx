import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import BranchForm from './BranchForm';

export default function Create({ campaigns }) {
    const form = useForm({ name: '', code: '', address: '', contact_person: '', contact_number: '', email: '', is_active: true, campaign_ids: [] });
    return (
        <AppLayout title="New branch" breadcrumbs={[{ label: 'Branches', href: '/branches' }, { label: 'New branch' }]}>
            <form onSubmit={(e) => (e.preventDefault(), form.post('/branches'))} className="mx-auto max-w-4xl">
                <PageHeader title="New branch" />
                <BranchForm form={form} campaigns={campaigns} />
                <div className="mt-6 flex justify-end gap-2">
                    <Button variant="secondary" href="/branches">Cancel</Button>
                    <Button type="submit" loading={form.processing}>Create branch</Button>
                </div>
            </form>
        </AppLayout>
    );
}
