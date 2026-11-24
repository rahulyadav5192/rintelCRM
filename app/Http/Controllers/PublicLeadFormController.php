<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\PublicLeadForm;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every Admin has one public lead form URL; leads submitted through it are owned by that Admin.
 * Super Admins manage all URLs; an Admin can view and copy their own.
 */
class PublicLeadFormController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->isAdmin(), 403);

        $admins = User::query()->where('role', Role::Admin->value)
            ->when($user->isAdmin(), fn ($q) => $q->whereKey($user->id))
            ->with(['publicForm', 'campaigns' => fn ($q) => $q->active()->where('is_public', true)->select('campaigns.id', 'campaigns.name')])
            ->orderBy('name')->get();

        return Inertia::render('PublicForms/Index', [
            'canManage' => $user->can('manage-public-forms'),
            'admins' => $admins->map(fn (User $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'email' => $a->email,
                'is_active' => $a->is_active,
                'public_campaigns' => $a->campaigns->pluck('name'),
                'form' => $a->publicForm ? [
                    'id' => $a->publicForm->id,
                    'url' => $a->publicForm->url(),
                    'is_active' => $a->publicForm->is_active,
                    'submissions_count' => $a->publicForm->submissions_count,
                    'last_reset_at' => $a->publicForm->last_reset_at?->toIso8601String(),
                    'created_at' => $a->publicForm->created_at?->toIso8601String(),
                ] : null,
            ]),
        ]);
    }

    public function store(Request $request, User $admin): RedirectResponse
    {
        $this->authorize('manage-public-forms');
        abort_unless($admin->isAdmin(), 422, 'Public forms can only be created for Admins.');
        if ($admin->publicForm()->exists()) {
            return back()->with('error', 'This admin already has a public form. Use "Reset URL" to issue a new link.');
        }

        $form = PublicLeadForm::ensureFor($admin, $request->user()->id);
        ActivityLogger::log('public_forms', 'public_form.generated', "Public lead URL generated for {$admin->name}", $form->id, null, ['admin' => $admin->name]);

        return back()->with('success', "Public URL generated for {$admin->name}.");
    }

    public function regenerate(PublicLeadForm $publicForm): RedirectResponse
    {
        $this->authorize('manage-public-forms');
        $publicForm->rotateToken();
        $publicForm->last_reset_at = now();
        $publicForm->save();
        $publicForm->load('admin:id,name');

        ActivityLogger::log('public_forms', 'public_form.reset', "Public lead URL reset for {$publicForm->admin?->name} (previous URL invalidated)", $publicForm->id);

        return back()->with('success', 'A new URL has been issued. The previous URL no longer works.');
    }

    public function toggle(PublicLeadForm $publicForm): RedirectResponse
    {
        $this->authorize('manage-public-forms');
        $publicForm->is_active = ! $publicForm->is_active;
        $publicForm->save();
        $publicForm->load('admin:id,name');
        $state = $publicForm->is_active ? 'activated' : 'deactivated';
        ActivityLogger::log('public_forms', "public_form.{$state}", "Public lead URL {$state} for {$publicForm->admin?->name}", $publicForm->id);

        return back()->with('success', "Public URL {$state}.");
    }
}
