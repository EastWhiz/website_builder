<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Setting;
use App\Support\OrganizationAccess;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'invitation' => $request->boolean('invitation'),
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $crmSyncData = null;

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request, &$crmSyncData) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Step 3.4: invitation activation completion.
                // If this user was invited to an organization, mark membership accepted.
                DB::table('organization_user')
                    ->where('user_id', $user->id)
                    ->where('status', 'invited')
                    ->update([
                        'status' => 'active',
                        'accepted_at' => now(),
                        'updated_at' => now(),
                    ]);

                $activatedOrgId = (int) (DB::table('organization_user')
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->orderByDesc('updated_at')
                    ->value('organization_id') ?? 0);
                if ($activatedOrgId > 0) {
                    OrganizationAccess::migrateUserOrganizationScopedData((int) $user->id, null, $activatedOrgId);
                }

                $membership = DB::table('organization_user as ou')
                    ->leftJoin('roles as r', 'r.id', '=', 'ou.role_id')
                    ->where('ou.user_id', $user->id)
                    ->where('ou.status', 'active')
                    ->whereNull('ou.deleted_at')
                    ->orderByDesc('ou.updated_at')
                    ->select([
                        'ou.organization_id',
                        'r.key as role_key',
                    ])
                    ->first();
                if ($membership && (int) $membership->organization_id > 0) {
                    $crmSyncData = [
                        'organization_id' => (int) $membership->organization_id,
                        'org_role' => (string) ($membership->role_key ?: 'media_buyer'),
                        'user' => $user->fresh(),
                    ];
                }

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status == Password::PASSWORD_RESET) {
            if (is_array($crmSyncData)) {
                $organization = Organization::query()->find((int) ($crmSyncData['organization_id'] ?? 0));
                $syncUser = $crmSyncData['user'] ?? null;
                $this->syncOrganizationMembershipToCrm(
                    $organization,
                    $syncUser,
                    (string) ($crmSyncData['org_role'] ?? 'media_buyer'),
                    'active',
                    false,
                    null,
                    true
                );
            }

            return redirect()->route('login')->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }

    /**
     * Best-effort organization membership sync to CRM.
     */
    private function syncOrganizationMembershipToCrm(
        ?Organization $organization,
        mixed $memberUser,
        string $orgRole = 'media_buyer',
        string $membershipStatus = 'active',
        bool $setAsPrimaryOwner = false,
        ?string $passwordHashOverride = null,
        bool $updatePasswordForExisting = false
    ): void {
        try {
            if (!$organization || !$memberUser) {
                return;
            }

            $nameParts = preg_split('/\s+/', trim((string) $memberUser->name)) ?: [];
            $firstName = trim((string) ($nameParts[0] ?? 'User'));
            $lastName = trim((string) (count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : $firstName));
            $webBuilderUserId = (string) ('U'.$memberUser->id);
            $primaryWebBuilderUserId = (string) ('U'.((int) ($organization->primary_user_id ?: $memberUser->id)));

            $payload = [
                'organizationId' => (int) $organization->id,
                'organizationName' => (string) $organization->name,
                'organizationStatus' => (string) $organization->status,
                'webBuilderUserId' => $webBuilderUserId,
                'primaryWebBuilderUserId' => $primaryWebBuilderUserId,
                'setAsPrimaryOwner' => $setAsPrimaryOwner,
                'orgRole' => $orgRole,
                'membershipStatus' => $membershipStatus,
                'email' => (string) $memberUser->email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'contact' => (string) ($memberUser->phone ?? ''),
                'passwordHash' => (string) ($passwordHashOverride ?: ($memberUser->password ?? '')),
                'updatePasswordForExisting' => $updatePasswordForExisting,
            ];

            $baseUrl = Setting::getCrmBaseUrl();
            $response = Http::withOptions(['verify' => Setting::getCrmVerifySsl()])
                ->timeout(15)
                ->post($baseUrl . '/api/v1/sync-organization-membership', $payload);

            if (!$response->successful()) {
                Log::error('CRM organization member sync failed (password reset flow)', [
                    'organization_id' => $organization->id,
                    'user_id' => $memberUser->id,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('CRM organization member sync exception (password reset flow)', [
                'organization_id' => $organization?->id,
                'user_id' => $memberUser?->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
