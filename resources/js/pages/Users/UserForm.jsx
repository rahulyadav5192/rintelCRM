import Card from '@/components/Card';
import FormInput from '@/components/FormInput';
import FormSelect from '@/components/FormSelect';
import Toggle, { Checkbox } from '@/components/Toggle';
import CheckboxList from '@/components/CheckboxList';
import { Lock } from 'lucide-react';

export default function UserForm({ form, roles, branches, permissions, defaultPermissions, branchLocked, isEdit, isSelf }) {
    const { data, setData, errors } = form;
    const scoped = data.role && data.role !== 'super_admin';
    const branch = branches.find((b) => String(b.id) === String(data.branch_id));
    const rolePermissions = permissions.filter((p) => p.roles.includes(data.role));

    const changeRole = (role) => {
        setData((d) => ({ ...d, role, permissions: defaultPermissions[role] ?? [], ...(role === 'super_admin' ? { branch_id: '', campaign_ids: [] } : {}) }));
    };
    const changeBranch = (branchId) => {
        const valid = new Set((branches.find((b) => String(b.id) === String(branchId))?.campaigns ?? []).map((c) => c.id));
        setData((d) => ({ ...d, branch_id: branchId, campaign_ids: d.campaign_ids.filter((id) => valid.has(id)) }));
    };
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

            <Card title="2. Role & branch">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <FormSelect label="Role" name="role" value={data.role} onChange={(e) => changeRole(e.target.value)} options={roles} placeholder={roles.length > 1 ? 'Select role' : undefined} error={errors.role} required disabled={isSelf} hint={isSelf ? 'You cannot change your own role.' : undefined} />
                    {scoped &&
                        (branchLocked ? (
                            <FormInput label="Branch" name="branch_display" value={branch?.name ?? ''} icon={Lock} disabled readOnly />
                        ) : (
                            <FormSelect label="Branch" name="branch_id" value={data.branch_id ?? ''} onChange={(e) => changeBranch(e.target.value)} options={branches.map((b) => ({ value: b.id, label: b.is_active ? b.name : `${b.name} (inactive)` }))} placeholder="Select branch" error={errors.branch_id} required />
                        ))}
                    {data.role === 'manager' && (
                        <FormSelect
                            label="Lead visibility"
                            name="lead_visibility"
                            className="sm:col-span-2"
                            value={data.lead_visibility}
                            onChange={(e) => setData('lead_visibility', e.target.value)}
                            options={[
                                { value: 'campaign', label: 'All branch leads in assigned campaigns' },
                                { value: 'assigned', label: 'Only leads assigned to this manager' },
                            ]}
                            error={errors.lead_visibility}
                        />
                    )}
                </div>
                {data.role === 'super_admin' && <p className="mt-4 rounded-lg bg-subtle p-3 text-sm text-muted">Super Admins have unrestricted access to every branch, campaign and setting.</p>}
            </Card>

            {scoped && (
                <Card title="3. Campaign access" description="The user only sees leads in these campaigns. Campaigns are limited to those assigned to the selected branch.">
                    <CheckboxList
                        required
                        options={(branch?.campaigns ?? []).map((c) => ({ value: c.id, label: c.name, hint: c.is_active ? null : 'Inactive' }))}
                        value={data.campaign_ids}
                        onChange={(v) => setData('campaign_ids', v)}
                        error={errors.campaign_ids}
                        emptyText={data.branch_id ? 'This branch has no campaigns assigned yet.' : 'Select a branch first.'}
                        columns={3}
                    />
                </Card>
            )}

            {scoped && rolePermissions.length > 0 && (
                <Card title="4. Permissions" description="What this user is allowed to do with leads they can access.">
                    {errors.permissions && <p className="mb-3 text-xs text-red-600">{errors.permissions}</p>}
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {rolePermissions.map((p) => (
                            <Checkbox key={p.value} label={p.label} description={p.description} checked={data.permissions.includes(p.value)} onChange={(on) => togglePermission(p.value, on)} />
                        ))}
                    </div>
                </Card>
            )}

            <Card title="Account status">
                <Toggle label="Active" description="Inactive users cannot sign in. Their leads and history are preserved." checked={data.is_active} disabled={isSelf} onChange={(v) => setData('is_active', v)} />
            </Card>
        </div>
    );
}
