import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/PageHeader';
import Card, { DetailList } from '@/components/Card';
import Button from '@/components/Button';
import FormInput from '@/components/FormInput';

export default function Edit({ profile }) {
    const details = useForm({ name: profile.name, email: profile.email, mobile: profile.mobile ?? '' });
    const pw = useForm({ current_password: '', password: '', password_confirmation: '' });

    return (
        <AppLayout title="My profile" breadcrumbs={[{ label: 'My profile' }]}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader title="My profile" />
                <Card title="Access" description="Managed by your administrator.">
                    <DetailList items={[
                        { label: 'Role', value: profile.role },
                        { label: 'Campaigns', value: profile.role === 'Super Admin' ? 'All campaigns' : profile.campaigns.join(', ') || '—' },
                    ]} />
                </Card>
                <form onSubmit={(e) => (e.preventDefault(), details.put('/profile', { preserveScroll: true }))}>
                    <Card title="Personal details" actions={<Button type="submit" size="sm" loading={details.processing} disabled={!details.isDirty}>Save</Button>}>
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <FormInput label="Full name" name="name" value={details.data.name} onChange={(e) => details.setData('name', e.target.value)} error={details.errors.name} required />
                            <FormInput label="Email" name="email" type="email" value={details.data.email} onChange={(e) => details.setData('email', e.target.value)} error={details.errors.email} required />
                            <FormInput label="Mobile number" name="mobile" type="tel" value={details.data.mobile} onChange={(e) => details.setData('mobile', e.target.value)} error={details.errors.mobile} />
                        </div>
                    </Card>
                </form>
                <form onSubmit={(e) => (e.preventDefault(), pw.put('/profile/password', { preserveScroll: true, onSuccess: () => pw.reset() }))}>
                    <Card title="Change password" actions={<Button type="submit" size="sm" loading={pw.processing}>Update password</Button>}>
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
                            <FormInput label="Current password" name="current_password" type="password" value={pw.data.current_password} onChange={(e) => pw.setData('current_password', e.target.value)} error={pw.errors.current_password} autoComplete="current-password" required />
                            <FormInput label="New password" name="password" type="password" value={pw.data.password} onChange={(e) => pw.setData('password', e.target.value)} error={pw.errors.password} autoComplete="new-password" required />
                            <FormInput label="Confirm new password" name="password_confirmation" type="password" value={pw.data.password_confirmation} onChange={(e) => pw.setData('password_confirmation', e.target.value)} autoComplete="new-password" required />
                        </div>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
