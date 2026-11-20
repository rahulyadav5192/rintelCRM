<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the global audit log (activity_logs). Lead-level history lives in lead_activities.
 */
class ActivityLogger
{
    public static function log(string $module, string $action, string $description, ?int $subjectId = null, ?array $old = null, ?array $new = null, ?int $userId = null): ActivityLog
    {
        $request = request();

        return ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'module' => $module,
            'action' => $action,
            'subject_id' => $subjectId,
            'description' => mb_substr($description, 0, 255),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    /** Returns [old, new] containing only attributes that actually changed. */
    public static function diff(array $before, array $after, array $hidden = ['password', 'remember_token', 'updated_at']): array
    {
        $old = [];
        $new = [];
        foreach ($after as $key => $value) {
            if (in_array($key, $hidden, true)) {
                continue;
            }
            $prev = $before[$key] ?? null;
            if ((string) json_encode($prev) !== (string) json_encode($value)) {
                $old[$key] = $prev;
                $new[$key] = $value;
            }
        }

        return [$old, $new];
    }
}
