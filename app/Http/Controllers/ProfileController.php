<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->load('department');

        return view('profile.edit', [
            'user' => $user,
            'stats' => [
                'requested' => $user->requestedTickets()->count(),
                'assigned_open' => $user->assignedTickets()->open()->count(),
                'resolved' => $user->assignedTickets()->whereNotNull('resolved_at')->count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($request->user())],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $request->input('password')]);

        return back()->with('success', 'Password changed.');
    }
}
