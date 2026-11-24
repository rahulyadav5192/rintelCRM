import Card from '@/components/Card';
import FormInput from '@/components/FormInput';
import FormSelect from '@/components/FormSelect';
import Toggle, { Checkbox } from '@/components/Toggle';
import CheckboxList from '@/components/CheckboxList';

const ROLE_HELP = {
    super_admin: 'Unrestricted access to every campaign, lead, user and setting.',
    admin: 'Sees and manages only the leads they own (created by them or received through their public form) in their campaigns. A public lead form URL is created automatically.',
    manager: 'Works leads in their campaigns. Managers are not tied to any admin.',
};

export default function UserForm({ form, roles, campaigns, permissions, defaultPermissions, isEdit, isSelf }) {
    const { data, setData, errors } = form;
    const scoped = data.role && data.role !== 'super_admin';
    const rolePermissions = permissions.filter((p) => p.roles.includes(data.role));

    const changeRole = (role) => setData((d) => ({ ...d, role, permissions: defaultPermissions[role] ?? [], ...(role === 'super_admin' ? { campaign_ids: [] } : {}) }));
    const togglePermission = (value, on) => setData('permissions', on ? [...data.permissions, value] : data.permissions.filter((p) => p !== value));

    return (
        <div className="space-y-6">
            <Card title="1. Basic details">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <FormInput label="Full name" name="name" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} required />
                    <FormInput label="Email" name="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} error={errors.email} required autoComplete="off" />
                    <FormInput label="Mobile number" name="mobile" type="tel" value={data.mobile ?? ''} onChange={(e) => setData('mobile', e.target.value)} error={errors.mobile} />
                    <FormInput label={isEdit ? 'New password' : 'Password'} name="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} error={errors.password} required={!isEdit} autoComplete="new-password" hint={isEdit ? 'Leave blank to keep the current password.' : 'At least 8 characters with letters and numbers.'} />
                </div>
            </Card>

            <Card title="2. Role">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <FormSelect label="Role" name="role" value={data.role} onChange={(e) => changeRole(e.target.value)} options={roles} placeholder="Select role" error={errors.role} required disabled={isSelf} hint={isSelf ? 'You cannot change your own role.' : undefined} />
                    {data.role === 'manager' && (
                        <FormSelect
                            label="Lead visibility"
                            name="lead_visibility"
                            value={data.lead_visibility}
                            onChange={(e) => setData('lead_visibility', e.target.value)}
                            options={[
                                { value: 'campaign', label: 'All leads in assigned campaigns' },
                                { value: 'assigned', label: 'Only leads assigned to this manager' },
                            ]}
                            error={errors.lead_visibility}
                        />
                    )}
                </div>
                {data.role && <p className="mt-4 rounded-lg bg-subtle p-3 text-sm text-muted">{ROLE_HELP[data.role]}</p>}
            </Card>

            {scoped && (
                <Card title="3. Campaign access" description="The user can only work with leads in these campaigns.">
                    <CheckboxList
                        required
                        options={campaigns.map((c) => ({ value: c.id, label: c.name, hint: c.is_active ? null : 'Inactive' }))}
                        value={data.campaign_ids}
                        onChange={(v) => setData('campaign_ids', v)}
                        error={errors.campaign_ids}
                        emptyText="No campaigns exist yet — create one first."
                        columns={3}
                    />
                </Card>
            )}

            {scoped && rolePermissions.length > 0 && (
                <Card title="4. Permissions" description="What this user is allowed to do with the leads they can access.">
                    {errors.permissions && <p className="mb-3 text-xs text-red-600">{errors.permissions}</p>}
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {rolePermissions.map((p) => (
                            <Checkbox key={p.value} label={p.label} description={p.description} checked={data.permissions.includes(p.value)} onChange={(on) => togglePermission(p.value, on)} />
                        ))}
                    </div>
                </Card>
            )}

            <Card title="Account status">
                <Toggle label="Active" description="Inactive users cannot sign in, and an inactive admin's public form stops accepting leads. Their leads and history are preserved." checked={data.is_active} disabled={isSelf} onChange={(v) => setData('is_active', v)} />
            </Card>
        </div>
    );
}
