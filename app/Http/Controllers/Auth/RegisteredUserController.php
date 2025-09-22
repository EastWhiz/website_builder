<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'phone' => 'required|string|max:20|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // ADD CRM USER CREATE API.
        $response = Http::post('https://crm.diy/api/v1/create-user', [
            'first_name' => explode(' ', $request->name)[0] ?? $request->name,
            'last_name' => explode(' ', $request->name)[1] ?? (explode(' ', $request->name)[0] ?? $request->name),
            'email' => $request->email,
            'contact' => $request->phone,
            'web_builder_user_id' => (string) ("U" . $user->id),
        ]);

        $responseData = $response->json();
        // Log::info('CRM User Creation API Response: ' . json_encode($responseData));

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('memberDashboard', absolute: false));
    }
}
