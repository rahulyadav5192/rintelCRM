<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => fn () => $user ? $this->authPayload($user) : null,
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    private function authPayload(User $user): array
    {
        $user->loadMissing(['permissions']);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'permissions' => $user->permissionValues(),
            ],
            // Navigation hints only — every route is independently authorized on the server.
            'can' => [
                'viewCampaigns' => $user->can('viewAny', Campaign::class),
                'viewUsers' => $user->can('viewAny', User::class),
                'managePublicForms' => $user->can('manage-public-forms'),
                'manageStatuses' => $user->can('manage-statuses'),
                'viewOwnPublicForm' => $user->isAdmin(),
                'viewReports' => $user->can('view-reports'),
                'viewActivityLogs' => $user->can('view-activity-logs'),
                'manageSettings' => $user->can('manage-settings'),
                'createLeads' => $user->hasPermission(Permission::CreateLeads),
                'exportLeads' => $user->hasPermission(Permission::ExportLeads),
            ],
            'campaigns' => $user->isSuperAdmin()
                ? []
                : Campaign::query()->accessibleBy($user)->active()->orderBy('name')->get(['id', 'name', 'code']),
        ];
    }
}
