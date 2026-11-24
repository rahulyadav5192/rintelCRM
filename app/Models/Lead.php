<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    public const TITLES = ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Prof', 'Mx'];

    /**
     * Only personal/contact fields are mass assignable. Lead number, owner, campaign, assignment,
     * statuses and audit columns are always set explicitly by LeadService after validation.
     */
    protected $fillable = ['lead_date', 'title', 'first_name', 'last_name', 'mobile', 'email', 'house_number', 'post_code', 'follow_up_date', 'notes'];

    protected function casts(): array
    {
        return [
            'lead_date' => 'date:Y-m-d',
            'follow_up_date' => 'date:Y-m-d',
        ];
    }

    /** The Admin who owns this lead (null = Super Admin only). */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id')->withTrashed();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function leadStatus(): BelongsTo
    {
        return $this->belongsTo(CampaignLeadStatus::class, 'lead_status_id');
    }

    public function paymentStatus(): BelongsTo
    {
        return $this->belongsTo(CampaignPaymentStatus::class, 'payment_status_id');
    }

    public function publicForm(): BelongsTo
    {
        return $this->belongsTo(PublicLeadForm::class, 'public_lead_form_id');
    }

    public function leadNotes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest('created_at')->latest('id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->title, $this->first_name, $this->last_name])));
    }

    /**
     * THE central lead visibility rule. Every lead query that serves a user (listing, detail,
     * export, dashboard, reports, notifications) must go through this scope.
     *
     *  - Super Admin: all leads.
     *  - Admin: leads they own (created by them or received through their public form) in
     *    campaigns assigned to them.
     *  - Manager: leads in campaigns assigned to them — all of them ("campaign" visibility)
     *    or only those assigned to them ("assigned").
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $query->whereIn('leads.campaign_id', $user->campaignIds() ?: [0]);

        if ($user->isAdmin()) {
            return $query->where('leads.admin_id', $user->id);
        }

        if ($user->lead_visibility === User::VISIBILITY_ASSIGNED) {
            return $query->where('leads.assigned_to', $user->id);
        }

        return $query;
    }
}
