import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Button from '@/components/Button';
import UserForm from './UserForm';

export default function Create(props) {
    const initialRole = props.roles.length === 1 ? props.roles[0].value : '';
    const form = useForm({
        name: '', email: '', mobile: '', password: '',
        role: initialRole,
        branch_id: props.branchLocked ? props.actorBranchId : '',
        campaign_ids: [],
        permissions: props.defaultPermissions[initialRole] ?? [],
        lead_visibility: 'campaign',
        is_active: true,
    });
    return (
        <AppLayout title="New user" breadcrumbs={[{ label: 'Users', href: '/users' }, { label: 'New user' }]}>
            <form onSubmit={(e) => (e.preventDefault(), form.post('/users'))} className="mx-auto max-w-4xl">
                <PageHeader title="New user" />
                <UserForm form={form} {...props} />
                <div className="mt-6 flex justify-end gap-2">
                    <Button variant="secondary" href="/users">Cancel</Button>
                    <Button type="submit" loading={form.processing}>Create user</Button>
                </div>
            </form>
        </AppLayout>
    );
}
