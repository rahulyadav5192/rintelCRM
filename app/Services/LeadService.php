<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\Campaign;
use App\Models\CampaignLeadStatus;
use App\Models\CampaignPaymentStatus;
use App\Models\Lead;
use App\Models\PublicLeadForm;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lead business rules shared by the authenticated CRM and the public lead form:
 * which owner/campaign/manager/status combinations are valid, lead ID generation,
 * permission-aware updates and the lead activity timeline.
 */
class LeadService
{
    public const DETAIL_FIELDS = ['lead_date', 'title', 'first_name', 'last_name', 'mobile', 'email', 'house_number', 'post_code', 'notes'];

    // ---- Option sources (also used by the lookup endpoints) -------------------------------

    /** Active campaigns the user may create leads in. */
    public function creatableCampaigns(User $user): Collection
    {
        return Campaign::query()->active()->accessibleBy($user)->orderBy('name')->get(['campaigns.id', 'campaigns.name', 'campaigns.code']);
    }

    public function eligibleManagers(int $campaignId): Collection
    {
        return User::query()->eligibleManagers($campaignId)->orderBy('name')->get(['id', 'name']);
    }

    public function eligibleAdmins(int $campaignId): Collection
    {
        return User::query()->eligibleAdmins($campaignId)->orderBy('name')->get(['id', 'name']);
    }

    /** Admins choose nobody: their leads are always their own. Super Admins and Managers pick the owner. */
    public function canChooseOwner(User $user): bool
    {
        return ! $user->isAdmin();
    }

    public function statusOptions(Campaign $campaign): array
    {
        return [
            'lead_statuses' => $campaign->leadStatuses()->where('is_active', true)->get(['id', 'name', 'color', 'is_default']),
            'payment_statuses' => $campaign->paymentStatuses()->where('is_active', true)->get(['id', 'name', 'color', 'is_default']),
        ];
    }

    public function previewLeadNumber(): string
    {
        return $this->formatNumber(((int) Lead::withTrashed()->max('id')) + 1);
    }

    // ---- Create -------------------------------------------------------------------------

    /**
     * @param  array  $data  Field-validated input (see LeadRequest / PublicLeadRequest).
     * @param  User|null  $actor  null for public submissions.
     */
    public function create(array $data, ?User $actor, ?PublicLeadForm $form = null): Lead
    {
        $campaign = $this->resolveCampaignForCreate($data, $actor, $form);
        $adminId = $this->resolveOwnerForCreate($data, $actor, $form, $campaign);

        $assignedTo = $data['assigned_to'] ?? null;
        if ($actor?->isManager() && ! $assignedTo && ! $actor->hasPermission(Permission::AssignLeads)) {
            // A manager creating a lead owns it by default when eligible.
            $assignedTo = User::query()->eligibleManagers($campaign->id)->whereKey($actor->id)->exists() ? $actor->id : null;
        }
        if ($assignedTo && $actor?->isManager() && ! $actor->hasPermission(Permission::AssignLeads) && (int) $assignedTo !== $actor->id) {
            throw ValidationException::withMessages(['assigned_to' => 'You can only assign leads to yourself.']);
        }
        $this->assertEligibleManager($assignedTo, $campaign->id);

        $canPickStatus = $form ? $campaign->public_show_statuses : true;
        $leadStatus = $this->resolveStatus(CampaignLeadStatus::class, $canPickStatus ? ($data['lead_status_id'] ?? null) : null, $campaign, 'lead_status_id');
        $paymentStatus = $this->resolveStatus(CampaignPaymentStatus::class, $canPickStatus ? ($data['payment_status_id'] ?? null) : null, $campaign, 'payment_status_id');

        if (! $leadStatus) {
            throw ValidationException::withMessages(['campaign_id' => 'This campaign has no active lead statuses configured.']);
        }

        return DB::transaction(function () use ($data, $actor, $form, $adminId, $campaign, $assignedTo, $leadStatus, $paymentStatus) {
            $lead = new Lead;
            $lead->fill(collect($data)->only([...self::DETAIL_FIELDS, 'follow_up_date'])->all());
            $canSetDate = $actor && ($actor->isSuperAdmin() || ($actor->isAdmin() && $actor->hasPermission(Permission::EditLeads)));
            if (! $canSetDate || empty($data['lead_date'])) {
                $lead->lead_date = now()->toDateString();
            }
            $lead->admin_id = $adminId;
            $lead->campaign_id = $campaign->id;
            $lead->assigned_to = $assignedTo;
            $lead->lead_status_id = $leadStatus->id;
            $lead->payment_status_id = $paymentStatus?->id;
            $lead->source = $form ? 'public' : 'manual';
            $lead->public_lead_form_id = $form?->id;
            $lead->created_by = $actor?->id;
            $lead->updated_by = $actor?->id;
            $lead->save();

            // The ID is derived from the auto-increment primary key inside the same transaction,
            // so it is unique under concurrency and never taken from user input.
            $lead->lead_number = $this->formatNumber($lead->id);
            $lead->saveQuietly();

            $this->activity($lead, $actor, 'created', $form ? 'Lead submitted via public form' : 'Lead created');
            if ($assignedTo) {
                $name = User::withTrashed()->find($assignedTo)?->name;
                $this->activity($lead, $actor, 'assigned', "Lead assigned to {$name}", null, ['assigned_to' => $name]);
            }
            if ($form) {
                $form->increment('submissions_count');
            }

            ActivityLogger::log('leads', 'lead.created', "Lead {$lead->lead_number} created".($form ? ' via public form' : ''), $lead->id, null, [
                'admin' => $adminId ? User::withTrashed()->find($adminId)?->name : null, 'campaign' => $campaign->name,
            ], $actor?->id);

            return $lead;
        });
    }

    // ---- Update -------------------------------------------------------------------------

    public function update(Lead $lead, array $data, User $actor): Lead
    {
        $lead->loadMissing(['campaign', 'admin', 'leadStatus', 'paymentStatus', 'assignee']);

        $changed = fn (string $key) => array_key_exists($key, $data) && (string) ($data[$key] ?? '') !== (string) ($this->currentValue($lead, $key) ?? '');

        $detailChanges = array_values(array_filter([...self::DETAIL_FIELDS, 'campaign_id'], $changed));
        if ($detailChanges && ! $actor->hasPermission(Permission::EditLeads)) {
            throw new AuthorizationException('You do not have permission to edit lead details.');
        }
        if ($actor->isAdmin() && $changed('campaign_id')) {
            throw new AuthorizationException('Admins can view but not change a lead\'s campaign, assignment or status.');
        }
        if ($changed('admin_id') && ! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Only Super Admins can change the admin who owns a lead.');
        }
        if ($changed('assigned_to') && ! $actor->hasPermission(Permission::AssignLeads)) {
            throw new AuthorizationException('You do not have permission to assign leads.');
        }
        if (($changed('lead_status_id') || $changed('follow_up_date')) && ! $actor->hasPermission(Permission::UpdateLeadStatus)) {
            throw new AuthorizationException('You do not have permission to change the lead status.');
        }
        if ($changed('payment_status_id') && ! $actor->hasPermission(Permission::UpdatePaymentStatus)) {
            throw new AuthorizationException('You do not have permission to change the payment status.');
        }

        // Resolve target campaign / owner.
        $campaign = $lead->campaign;
        $campaignChanged = $changed('campaign_id');
        if ($campaignChanged) {
            $campaign = Campaign::query()->active()->accessibleBy($actor)->find((int) $data['campaign_id'])
                ?? throw ValidationException::withMessages(['campaign_id' => 'Select an active campaign that you have access to.']);
        }
        $adminId = $changed('admin_id') ? ((int) $data['admin_id'] ?: null) : $lead->admin_id;
        $ownerChanged = (int) $adminId !== (int) $lead->admin_id;
        if ($adminId && ($ownerChanged || $campaignChanged)) {
            $this->assertEligibleAdmin($adminId, $campaign->id);
        }

        // Assignment.
        $assignedTo = array_key_exists('assigned_to', $data) && $actor->hasPermission(Permission::AssignLeads)
            ? ($data['assigned_to'] ?: null)
            : $lead->assigned_to;
        $assignmentChanged = (int) $assignedTo !== (int) $lead->assigned_to;
        if ($assignmentChanged || ($campaignChanged && $assignedTo)) {
            if ($campaignChanged && ! $assignmentChanged && $assignedTo
                && ! User::query()->eligibleManagers($campaign->id)->whereKey($assignedTo)->exists()) {
                throw ValidationException::withMessages(['assigned_to' => 'The current advisor is not assigned to the new campaign. Choose another advisor.']);
            }
            $this->assertEligibleManager($assignedTo, $campaign->id);
        }

        // Statuses (must belong to the — possibly new — campaign).
        $leadStatusInput = $actor->hasPermission(Permission::UpdateLeadStatus) ? ($data['lead_status_id'] ?? $lead->lead_status_id) : $lead->lead_status_id;
        $paymentStatusInput = $actor->hasPermission(Permission::UpdatePaymentStatus) ? ($data['payment_status_id'] ?? $lead->payment_status_id) : $lead->payment_status_id;
        if ($campaignChanged) {
            // Status IDs of the old campaign can never be valid for the new one; fall back to defaults.
            $leadStatusInput = $this->belongsToCampaign(CampaignLeadStatus::class, $leadStatusInput, $campaign) ? $leadStatusInput : null;
            $paymentStatusInput = $this->belongsToCampaign(CampaignPaymentStatus::class, $paymentStatusInput, $campaign) ? $paymentStatusInput : null;
        }
        $leadStatus = (int) $leadStatusInput === (int) $lead->lead_status_id && ! $campaignChanged
            ? $lead->leadStatus
            : $this->resolveStatus(CampaignLeadStatus::class, $leadStatusInput, $campaign, 'lead_status_id');
        $paymentStatus = (int) $paymentStatusInput === (int) $lead->payment_status_id && ! $campaignChanged
            ? $lead->paymentStatus
            : $this->resolveStatus(CampaignPaymentStatus::class, $paymentStatusInput, $campaign, 'payment_status_id');
        if (! $leadStatus) {
            throw ValidationException::withMessages(['lead_status_id' => 'This campaign has no active lead statuses configured.']);
        }

        return DB::transaction(function () use ($lead, $data, $actor, $adminId, $campaign, $assignedTo, $leadStatus, $paymentStatus, $assignmentChanged) {
            $before = $this->snapshot($lead);
            $oldAssignee = $lead->assignee?->name;
            $oldStatus = $lead->leadStatus?->name;
            $oldPayment = $lead->paymentStatus?->name;

            if ($actor->hasPermission(Permission::EditLeads)) {
                $lead->fill(collect($data)->only(self::DETAIL_FIELDS)->all());
            }
            if ($actor->hasPermission(Permission::UpdateLeadStatus) && array_key_exists('follow_up_date', $data)) {
                $lead->follow_up_date = $data['follow_up_date'] ?: null;
            }
            $lead->admin_id = $adminId;
            $lead->campaign_id = $campaign->id;
            $lead->assigned_to = $assignedTo;
            $lead->lead_status_id = $leadStatus->id;
            $lead->payment_status_id = $paymentStatus?->id;
            $lead->updated_by = $actor->id;
            $lead->save();
            $lead->load(['admin', 'campaign', 'assignee', 'leadStatus', 'paymentStatus']);

            $after = $this->snapshot($lead);
            $detailKeys = [...self::DETAIL_FIELDS, 'follow_up_date', 'admin', 'campaign'];
            [$old, $new] = ActivityLogger::diff(array_intersect_key($before, array_flip($detailKeys)), array_intersect_key($after, array_flip($detailKeys)));
            if ($new) {
                $this->activity($lead, $actor, 'updated', 'Lead information edited ('.implode(', ', array_map(fn ($k) => str_replace('_', ' ', $k), array_keys($new))).')', $old, $new);
                ActivityLogger::log('leads', 'lead.updated', "Lead {$lead->lead_number} edited", $lead->id, $old, $new);
            }
            if ($assignmentChanged) {
                $type = $oldAssignee ? 'reassigned' : 'assigned';
                $newName = $lead->assignee?->name;
                $desc = $newName
                    ? ($oldAssignee ? "Lead reassigned from {$oldAssignee} to {$newName}" : "Lead assigned to {$newName}")
                    : "Lead unassigned from {$oldAssignee}";
                $this->activity($lead, $actor, $type, $desc, ['assigned_to' => $oldAssignee], ['assigned_to' => $newName]);
                ActivityLogger::log('leads', "lead.{$type}", "{$lead->lead_number}: {$desc}", $lead->id, ['assigned_to' => $oldAssignee], ['assigned_to' => $newName]);
            }
            if ($oldStatus !== $lead->leadStatus?->name) {
                $desc = "Status changed from {$oldStatus} to {$lead->leadStatus?->name}";
                $this->activity($lead, $actor, 'status_changed', $desc, ['status' => $oldStatus], ['status' => $lead->leadStatus?->name]);
                ActivityLogger::log('leads', 'lead.status_changed', "{$lead->lead_number}: {$desc}", $lead->id, ['status' => $oldStatus], ['status' => $lead->leadStatus?->name]);
            }
            if ($oldPayment !== $lead->paymentStatus?->name) {
                $desc = 'Payment status changed from '.($oldPayment ?? 'none').' to '.($lead->paymentStatus?->name ?? 'none');
                $this->activity($lead, $actor, 'payment_status_changed', $desc, ['payment_status' => $oldPayment], ['payment_status' => $lead->paymentStatus?->name]);
                ActivityLogger::log('leads', 'lead.payment_status_changed', "{$lead->lead_number}: {$desc}", $lead->id, ['payment_status' => $oldPayment], ['payment_status' => $lead->paymentStatus?->name]);
            }

            return $lead;
        });
    }

    public function addNote(Lead $lead, User $actor, string $body): void
    {
        DB::transaction(function () use ($lead, $actor, $body) {
            $note = $lead->leadNotes()->make(['body' => $body]);
            $note->user_id = $actor->id;
            $note->save();
            $this->activity($lead, $actor, 'note_added', 'Note added', null, ['note' => $body]);
        });
    }

    public function activity(Lead $lead, ?User $actor, string $type, string $description, ?array $old = null, ?array $new = null): void
    {
        $lead->activities()->create([
            'user_id' => $actor?->id,
            'type' => $type,
            'description' => mb_substr($description, 0, 255),
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }

    // ---- Internals ----------------------------------------------------------------------

    private function resolveCampaignForCreate(array $data, ?User $actor, ?PublicLeadForm $form): Campaign
    {
        if ($form) {
            // Public forms offer the owning admin's active, public campaigns only.
            $query = $form->admin->campaigns()->active()->where('is_public', true);
        } else {
            $query = Campaign::query()->active()->accessibleBy($actor);
        }

        return $query->find((int) ($data['campaign_id'] ?? 0))
            ?? throw ValidationException::withMessages(['campaign_id' => 'Select a valid campaign.']);
    }

    /** Works out which Admin owns a new lead. */
    private function resolveOwnerForCreate(array $data, ?User $actor, ?PublicLeadForm $form, Campaign $campaign): ?int
    {
        if ($form) {
            return $form->user_id;
        }
        if ($actor->isAdmin()) {
            if (! empty($data['admin_id']) && (int) $data['admin_id'] !== $actor->id) {
                throw ValidationException::withMessages(['admin_id' => 'Leads you create are always owned by you.']);
            }

            return $actor->id;
        }

        $adminId = ! empty($data['admin_id']) ? (int) $data['admin_id'] : null;
        $this->assertEligibleAdmin($adminId, $campaign->id);

        return $adminId;
    }

    private function assertEligibleAdmin(?int $adminId, int $campaignId): void
    {
        if ($adminId && ! User::query()->eligibleAdmins($campaignId)->whereKey($adminId)->exists()) {
            throw ValidationException::withMessages(['admin_id' => 'The selected admin is not active or does not have this campaign.']);
        }
    }

    private function assertEligibleManager(mixed $userId, int $campaignId): void
    {
        if ($userId && ! User::query()->eligibleManagers($campaignId)->whereKey((int) $userId)->exists()) {
            throw ValidationException::withMessages(['assigned_to' => 'The selected advisor is not an active manager of this campaign.']);
        }
    }

    /** @param class-string<CampaignLeadStatus|CampaignPaymentStatus> $model */
    private function resolveStatus(string $model, mixed $id, Campaign $campaign, string $field): CampaignLeadStatus|CampaignPaymentStatus|null
    {
        if ($id) {
            return $model::query()->where('campaign_id', $campaign->id)->where('is_active', true)->find((int) $id)
                ?? throw ValidationException::withMessages([$field => 'The selected status does not belong to this campaign.']);
        }

        return $model === CampaignLeadStatus::class ? $campaign->defaultLeadStatus() : $campaign->defaultPaymentStatus();
    }

    private function belongsToCampaign(string $model, mixed $id, Campaign $campaign): bool
    {
        return $id && $model::query()->where('campaign_id', $campaign->id)->whereKey((int) $id)->exists();
    }

    private function currentValue(Lead $lead, string $key): mixed
    {
        $value = $lead->getAttribute($key);

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    private function snapshot(Lead $lead): array
    {
        return [
            ...collect(self::DETAIL_FIELDS)->mapWithKeys(fn ($k) => [$k => $this->currentValue($lead, $k)])->all(),
            'follow_up_date' => $this->currentValue($lead, 'follow_up_date'),
            'admin' => $lead->admin?->name,
            'campaign' => $lead->campaign?->name,
        ];
    }

    private function formatNumber(int $id): string
    {
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) Setting::get('lead_number_prefix'))) ?: 'RL';

        return $prefix.'-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
