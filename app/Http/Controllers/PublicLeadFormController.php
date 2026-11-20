<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PublicLeadForm;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicLeadFormController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('manage-public-forms');

        $branches = Branch::query()->with(['publicForm', 'campaigns' => fn ($q) => $q->active()->where('is_public', true)->select('campaigns.id', 'campaigns.name')])
            ->orderBy('name')->get();

        return Inertia::render('PublicForms/Index', [
            'branches' => $branches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code,
                'is_active' => $b->is_active,
                'public_campaigns' => $b->campaigns->pluck('name'),
                'form' => $b->publicForm ? [
                    'id' => $b->publicForm->id,
                    'url' => $b->publicForm->url(),
                    'is_active' => $b->publicForm->is_active,
                    'submissions_count' => $b->publicForm->submissions_count,
                    'last_reset_at' => $b->publicForm->last_reset_at?->toIso8601String(),
                    'created_at' => $b->publicForm->created_at?->toIso8601String(),
                ] : null,
            ]),
        ]);
    }

    public function store(Branch $branch): RedirectResponse
    {
        $this->authorize('manage-public-forms');
        if ($branch->publicForm()->exists()) {
            return back()->with('error', 'This branch already has a public form. Use "Reset URL" to issue a new link.');
        }

        $form = new PublicLeadForm(['branch_id' => $branch->id, 'is_active' => true]);
        $form->branch_id = $branch->id;
        $form->created_by = request()->user()->id;
        $form->rotateToken();
        $form->save();

        ActivityLogger::log('public_forms', 'public_form.generated', "Public lead URL generated for {$branch->name}", $form->id, null, ['branch' => $branch->name]);

        return back()->with('success', "Public URL generated for {$branch->name}.");
    }

    public function regenerate(PublicLeadForm $publicForm): RedirectResponse
    {
        $this->authorize('manage-public-forms');
        $publicForm->rotateToken();
        $publicForm->last_reset_at = now();
        $publicForm->save();
        $publicForm->load('branch:id,name');

        ActivityLogger::log('public_forms', 'public_form.reset', "Public lead URL reset for {$publicForm->branch->name} (previous URL invalidated)", $publicForm->id);

        return back()->with('success', 'A new URL has been issued. The previous URL no longer works.');
    }

    public function toggle(PublicLeadForm $publicForm): RedirectResponse
    {
        $this->authorize('manage-public-forms');
        $publicForm->is_active = ! $publicForm->is_active;
        $publicForm->save();
        $publicForm->load('branch:id,name');
        $state = $publicForm->is_active ? 'activated' : 'deactivated';
        ActivityLogger::log('public_forms', "public_form.{$state}", "Public lead URL {$state} for {$publicForm->branch->name}", $publicForm->id);

        return back()->with('success', "Public URL {$state}.");
    }
}
