<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// Role, status and permissions are deliberately NOT mass assignable; they are set explicitly
// by UserController after authorization checks.
#[Fillable(['name', 'email', 'mobile', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const VISIBILITY_CAMPAIGN = 'campaign';

    public const VISIBILITY_ASSIGNED = 'assigned';

    /** @var array<int>|null */
    private ?array $campaignIdCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'notifications_read_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    // ---- Relationships -------------------------------------------------

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class)->withTimestamps();
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function createdLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'created_by');
    }

    /** Leads owned by this Admin. */
    public function ownedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'admin_id');
    }

    public function publicForm(): HasOne
    {
        return $this->hasOne(PublicLeadForm::class);
    }

    // ---- Role helpers ----------------------------------------------------

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isManager(): bool
    {
        return $this->role === Role::Manager;
    }

    public function hasPermission(Permission $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        if (! in_array($permission, Permission::forRole($this->role), true)) {
            return false;
        }

        return $this->permissions->contains('permission', $permission->value);
    }

    /** @return array<string> */
    public function permissionValues(): array
    {
        if ($this->isSuperAdmin()) {
            return array_map(fn ($p) => $p->value, Permission::cases());
        }

        return array_values(array_filter(
            array_map(fn ($p) => $p->value, Permission::forRole($this->role)),
            fn ($v) => $this->permissions->contains('permission', $v),
        ));
    }

    /** @param array<string> $values */
    public function syncPermissions(array $values): void
    {
        $allowed = array_map(fn ($p) => $p->value, Permission::forRole($this->role));
        $values = array_values(array_intersect(array_unique($values), $allowed));

        $this->permissions()->whereNotIn('permission', $values)->delete();
        foreach ($values as $value) {
            $this->permissions()->firstOrCreate(['permission' => $value]);
        }
        $this->unsetRelation('permissions');
    }

    /**
     * IDs of campaigns this user is assigned to. Super Admins are unrestricted and should
     * never call this for filtering — use the scopes below, which handle that case.
     *
     * @return array<int>
     */
    public function campaignIds(): array
    {
        return $this->campaignIdCache ??= $this->campaigns()->pluck('campaigns.id')->map(fn ($id) => (int) $id)->all();
    }

    public function flushAccessCache(): void
    {
        $this->campaignIdCache = null;
    }

    public function canAccessCampaign(int $campaignId): bool
    {
        return $this->isSuperAdmin() || in_array($campaignId, $this->campaignIds(), true);
    }

    // ---- Scopes ----------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Active managers that can be assigned leads in a campaign. Managers are not tied to any admin. */
    public function scopeEligibleManagers(Builder $query, int $campaignId): Builder
    {
        return $query->where('role', Role::Manager->value)
            ->where('is_active', true)
            ->whereHas('campaigns', fn (Builder $q) => $q->where('campaigns.id', $campaignId));
    }

    /** Active Admins that can own leads in a campaign (the campaign must be assigned to them). */
    public function scopeEligibleAdmins(Builder $query, int $campaignId): Builder
    {
        return $query->where('role', Role::Admin->value)
            ->where('is_active', true)
            ->whereHas('campaigns', fn (Builder $q) => $q->where('campaigns.id', $campaignId));
    }
}
