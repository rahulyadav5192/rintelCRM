<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\LeadFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('view-activity-logs');
        $filters = $request->only('search', 'module', 'user_id', 'date_from', 'date_to');
        $search = trim((string) ($filters['search'] ?? ''));

        $logs = ActivityLog::query()->with('user:id,name')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('description', 'like', "%{$search}%")->orWhere('action', 'like', "%{$search}%")->orWhere('ip_address', 'like', "%{$search}%")))
            ->when($filters['module'] ?? null, fn ($q, $m) => $q->where('module', $m))
            ->when(ctype_digit((string) ($filters['user_id'] ?? '')), fn ($q) => $q->where('user_id', (int) $filters['user_id']))
            ->when(LeadFilters::date($filters['date_from'] ?? null), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when(LeadFilters::date($filters['date_to'] ?? null), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('created_at')->latest('id')
            ->paginate(25)->withQueryString()
            ->through(fn (ActivityLog $l) => [
                'id' => $l->id, 'action' => $l->action, 'module' => $l->module, 'subject_id' => $l->subject_id,
                'description' => $l->description, 'old_values' => $l->old_values, 'new_values' => $l->new_values,
                'ip_address' => $l->ip_address, 'user' => $l->user?->name ?? 'System / Public', 'created_at' => $l->created_at?->toIso8601String(),
            ]);

        return Inertia::render('ActivityLogs/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'modules' => ActivityLog::query()->distinct()->orderBy('module')->pluck('module'),
            'users' => User::withTrashed()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
