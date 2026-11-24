<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'code', 'description', 'is_public', 'public_show_statuses'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'public_show_statuses' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function managers(): BelongsToMany
    {
        return $this->users()->where('role', Role::Manager->value);
    }

    public function admins(): BelongsToMany
    {
        return $this->users()->where('role', Role::Admin->value);
    }

    public function leadStatuses(): HasMany
    {
        return $this->hasMany(CampaignLeadStatus::class)->orderBy('sort_order')->orderBy('id');
    }

    public function paymentStatuses(): HasMany
    {
        return $this->hasMany(CampaignPaymentStatus::class)->orderBy('sort_order')->orderBy('id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Campaigns the user can see data for (assigned campaigns for scoped roles). */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereIn('campaigns.id', $user->campaignIds());
    }

    public function defaultLeadStatus(): ?CampaignLeadStatus
    {
        return $this->leadStatuses()->where('is_active', true)->reorder()->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id')->first();
    }

    public function defaultPaymentStatus(): ?CampaignPaymentStatus
    {
        return $this->paymentStatuses()->where('is_active', true)->reorder()->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id')->first();
    }
}
