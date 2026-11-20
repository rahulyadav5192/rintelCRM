<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Applies listing filters to a lead query. Always starts from Lead::visibleTo($user), so
 * filters can only ever narrow what the user is allowed to see — a branch_id or campaign_id
 * the user has no access to simply yields no rows.
 */
class LeadFilters
{
    public const KEYS = ['search', 'branch_id', 'campaign_id', 'assigned_to', 'lead_status_id', 'payment_status_id', 'date_from', 'date_to', 'source', 'sort', 'direction'];

    public const SORTABLE = [
        'lead_number' => 'leads.id',
        'lead_date' => 'leads.lead_date',
        'name' => 'leads.first_name',
        'mobile' => 'leads.mobile',
        'created_at' => 'leads.created_at',
    ];

    public static function fromRequest(Request $request): array
    {
        $filters = [];
        foreach (self::KEYS as $key) {
            $value = $request->query($key);
            if (is_string($value) && trim($value) !== '') {
                $filters[$key] = trim(mb_substr($value, 0, 100));
            }
        }

        return $filters;
    }

    public static function query(User $user, array $filters): Builder
    {
        $query = Lead::query()->visibleTo($user);

        if ($search = $filters['search'] ?? null) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $q) use ($search, $like) {
                $q->where('leads.lead_number', 'like', $like)
                    ->orWhere('leads.mobile', 'like', $like)
                    ->orWhere('leads.email', 'like', $like)
                    ->orWhere('leads.post_code', 'like', $like)
                    ->orWhere('leads.first_name', 'like', $like)
                    ->orWhere('leads.last_name', 'like', $like)
                    ->orWhereRaw("CONCAT_WS(' ', leads.first_name, leads.last_name) LIKE ?", [$like]);
                if (ctype_digit($search)) {
                    $q->orWhere('leads.id', (int) $search);
                }
            });
        }

        foreach (['branch_id', 'campaign_id', 'lead_status_id', 'payment_status_id'] as $key) {
            if (isset($filters[$key]) && ctype_digit($filters[$key])) {
                $query->where("leads.$key", (int) $filters[$key]);
            }
        }

        if (isset($filters['assigned_to'])) {
            $filters['assigned_to'] === 'unassigned'
                ? $query->whereNull('leads.assigned_to')
                : (ctype_digit($filters['assigned_to']) ? $query->where('leads.assigned_to', (int) $filters['assigned_to']) : null);
        }

        if (isset($filters['source']) && in_array($filters['source'], ['manual', 'public'], true)) {
            $query->where('leads.source', $filters['source']);
        }

        if (($from = self::date($filters['date_from'] ?? null)) !== null) {
            $query->whereDate('leads.lead_date', '>=', $from);
        }
        if (($to = self::date($filters['date_to'] ?? null)) !== null) {
            $query->whereDate('leads.lead_date', '<=', $to);
        }

        return $query;
    }

    public static function applySort(Builder $query, array $filters): Builder
    {
        $column = self::SORTABLE[$filters['sort'] ?? ''] ?? 'leads.id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($column, $direction)->orderBy('leads.id', $direction);
    }

    public static function date(?string $value): ?string
    {
        if ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false) {
            return $value;
        }

        return null;
    }
}
