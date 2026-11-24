import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import UserForm from './UserForm';

export default function Create(props) {
    const initialRole = new URLSearchParams(window.location.search).get('role') ?? '';
    const form = useForm({
        name: '', email: '', mobile: '', password: '',
        role: initialRole,
        campaign_ids: [],
        permissions: props.defaultPermissions[initialRole] ?? [],
        lead_visibility: 'campaign',
        is_active: true,
    });
    return (
        <AppLayout title="New user" breadcrumbs={[{ label: 'Users', href: '/users' }, { label: 'New user' }]}>
            <form onSubmit={(e) => (e.preventDefault(), form.post('/users'))} className="mx-auto max-w-4xl">
                <PageHeader title="New user" description="Create a Super Admin, Admin or Manager." />
                <UserForm form={form} {...props} />
                <div className="mt-6 flex justify-end gap-2">
                    <Button variant="secondary" href="/users">Cancel</Button>
                    <Button type="submit" loading={form.processing}>Create user</Button>
                </div>
            </form>
        </AppLayout>
    );
}
