<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->load(['campaigns:id,name']);

        return Inertia::render('Profile/Edit', [
            'profile' => [
                ...$user->only('name', 'email', 'mobile'),
                'role' => $user->role->label(),
                'campaigns' => $user->campaigns->pluck('name'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'mobile' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
        ]);
        $user->fill($data)->save();

        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), 'max:255'],
        ]);
        $request->user()->forceFill(['password' => $data['password']])->save();
        $request->session()->regenerate();

        return back()->with('success', 'Password changed.');
    }
}
