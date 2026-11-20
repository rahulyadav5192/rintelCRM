<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        $this->authorize('manage-settings');

        return Inertia::render('Settings/Index', ['settings' => Setting::allWithDefaults()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage-settings');
        $data = $request->validate([
            'lead_number_prefix' => ['required', 'string', 'max:6', 'regex:/^[A-Za-z0-9]+$/'],
            'public_form_heading' => ['required', 'string', 'max:120'],
            'public_form_intro' => ['nullable', 'string', 'max:500'],
            'public_form_success' => ['required', 'string', 'max:500'],
        ]);
        $data['lead_number_prefix'] = strtoupper($data['lead_number_prefix']);

        $before = Setting::allWithDefaults();
        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }
        [$old, $new] = ActivityLogger::diff($before, Setting::allWithDefaults());
        if ($new) {
            ActivityLogger::log('settings', 'settings.updated', 'System settings updated', null, $old, $new);
        }

        return back()->with('success', 'Settings saved.');
    }
}
