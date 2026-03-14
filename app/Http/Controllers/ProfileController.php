<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Setting;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $crmSettings = null;
        if ($user && $user->email === 'admin@gmail.com') {
            $crmSettings = [
                'crm_mode' => Setting::get('crm_mode', 'production'),
                'crm_url_production' => Setting::get('crm_url_production', 'https://crm.diy'),
                'crm_url_dev' => Setting::get('crm_url_dev', ''),
                'crm_verify_ssl' => Setting::get('crm_verify_ssl', '1'),
            ];
        }

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'crmSettings' => $crmSettings,
            'deepl_api_key' => $request->user()->deepl_api_key,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Update the user's DeepL API key only.
     */
    public function updateDeeplApiKey(Request $request): RedirectResponse
    {
        $request->validate([
            'deepl_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->deepl_api_key = $request->input('deepl_api_key');
        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
