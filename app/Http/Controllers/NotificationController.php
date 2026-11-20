<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lightweight in-app notifications: recent activity by other users on leads the current user
 * can see (scoped by Lead::visibleTo). Managers only see activity on leads assigned to them.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $this->query($request);
        $since = $user->notifications_read_at ?? now()->subDays(7);

        return response()->json([
            'unread' => (clone $query)->where('created_at', '>', $since)->count(),
            'items' => $query->with(['user:id,name', 'lead:id,lead_number'])->latest('created_at')->latest('id')->limit(12)->get()->map(fn ($a) => [
                'id' => $a->id, 'description' => $a->description, 'user' => $a->user?->name ?? 'Public form',
                'lead_id' => $a->lead_id, 'lead_number' => $a->lead?->lead_number,
                'created_at' => $a->created_at?->toIso8601String(), 'unread' => $a->created_at > $since,
            ]),
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $request->user()->forceFill(['notifications_read_at' => now()])->saveQuietly();

        return response()->json(['ok' => true]);
    }

    private function query(Request $request)
    {
        $user = $request->user();
        $leads = Lead::query()->visibleTo($user)->select('leads.id');
        if ($user->isManager()) {
            $leads->where('leads.assigned_to', $user->id);
        }

        return LeadActivity::query()->whereIn('lead_id', $leads)
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $user->id))
            ->where('created_at', '>=', now()->subDays(30));
    }
}
