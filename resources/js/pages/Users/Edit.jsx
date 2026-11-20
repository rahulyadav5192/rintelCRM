import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import UserForm from './UserForm';

export default function Edit({ user, ...props }) {
    const { auth } = usePage().props;
    const form = useForm({ ...user, mobile: user.mobile ?? '', branch_id: user.branch_id ?? '', password: '', lead_visibility: user.lead_visibility ?? 'campaign' });
    return (
        <AppLayout title={`Edit ${user.name}`} breadcrumbs={[{ label: 'Users', href: '/users' }, { label: user.name, href: `/users/${user.id}` }, { label: 'Edit' }]}>
            <form onSubmit={(e) => (e.preventDefault(), form.put(`/users/${user.id}`))} className="mx-auto max-w-4xl">
                <PageHeader title={`Edit ${user.name}`} />
                <UserForm form={form} {...props} isEdit isSelf={auth.user.id === user.id} />
                <div className="mt-6 flex justify-end gap-2">
                    <Button variant="secondary" href={`/users/${user.id}`}>Cancel</Button>
                    <Button type="submit" loading={form.processing} disabled={!form.isDirty}>Save changes</Button>
                </div>
            </form>
        </AppLayout>
    );
}
