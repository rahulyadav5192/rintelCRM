<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\PublicLeadForm;
use App\Models\Setting;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Unauthenticated lead capture. The owning Admin comes exclusively from the URL token; any
 * admin_id in the payload is ignored. Only id + name of eligible advisors is ever exposed.
 */
class PublicLeadController extends Controller
{
    private const MIN_FILL_SECONDS = 3;

    public function __construct(private readonly LeadService $leads) {}

    public function show(string $token): Response
    {
        $form = $this->resolve($token);
        $available = (bool) $form;

        return Inertia::render('Public/LeadForm', [
            'available' => $available,
            'campaigns' => $available ? $this->campaigns($form)->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'description' => $c->description, 'show_statuses' => $c->public_show_statuses,
            ]) : [],
            'titles' => Lead::TITLES,
            'formToken' => Crypt::encryptString((string) now()->timestamp),
            'texts' => [
                'heading' => Setting::get('public_form_heading'),
                'intro' => Setting::get('public_form_intro'),
                'success' => Setting::get('public_form_success'),
            ],
            'submitted' => session('public_lead_submitted', false),
        ])->withViewData(['noindex' => true]);
    }

    public function options(string $token, int $campaign): JsonResponse
    {
        $form = $this->resolve($token);
        abort_unless($form, 404);
        $model = $this->campaigns($form)->firstWhere('id', $campaign);
        abort_unless($model, 404);

        return response()->json([
            'managers' => $this->leads->eligibleManagers($model->id)->map->only('id', 'name'),
            ...($model->public_show_statuses ? $this->leads->statusOptions($model) : ['lead_statuses' => [], 'payment_statuses' => []]),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $form = $this->resolve($token);
        abort_unless($form, 404);

        // Spam protection: honeypot field must stay empty and the form must not be submitted instantly.
        if (filled($request->input('website')) || ! $this->formTimingOk((string) $request->input('form_token'))) {
            throw ValidationException::withMessages(['form' => 'Your submission could not be verified. Please reload the page and try again.']);
        }

        $request->merge([
            'mobile' => preg_replace('/[\s\-()]/', '', (string) $request->input('mobile')),
            'email' => $request->filled('email') ? strtolower(trim((string) $request->input('email'))) : null,
            'post_code' => $request->filled('post_code') ? strtoupper(trim((string) $request->input('post_code'))) : null,
        ]);

        $data = $request->validate([
            'title' => ['nullable', Rule::in(Lead::TITLES)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'mobile' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'post_code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9 ]{2,10}$/'],
            'campaign_id' => ['required', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'lead_status_id' => ['nullable', 'integer'],
            'payment_status_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'mobile.regex' => 'Enter a valid mobile number.',
            'post_code.regex' => 'Enter a valid post code.',
            'campaign_id.required' => 'Please choose a campaign.',
        ]);

        $this->leads->create($data, null, $form);

        return redirect()->route('public-leads.show', $token)->with('public_lead_submitted', true);
    }

    private function resolve(string $token): ?PublicLeadForm
    {
        return PublicLeadForm::findActiveByToken($token);
    }

    /** The owning Admin's active campaigns that are enabled for public forms. */
    private function campaigns(PublicLeadForm $form)
    {
        return $form->admin->campaigns()->active()->where('is_public', true)
            ->orderBy('name')->get(['campaigns.id', 'campaigns.name', 'campaigns.description', 'campaigns.public_show_statuses']);
    }

    private function formTimingOk(string $formToken): bool
    {
        try {
            $renderedAt = (int) Crypt::decryptString($formToken);
        } catch (\Throwable) {
            return false;
        }
        $age = now()->timestamp - $renderedAt;

        return $age >= self::MIN_FILL_SECONDS && $age <= 60 * 60 * 6;
    }
}
