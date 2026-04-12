<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganizationMailSettingsRequest;
use App\Models\OrganizationMailSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class OrganizationMailSettingsController extends Controller
{
    public function update(UpdateOrganizationMailSettingsRequest $request): RedirectResponse
    {
        $organization = $request->user()->currentOrganization();
        if (!$organization) {
            abort(422, 'No active organization context found.');
        }

        $validated = $request->validated();
        $encryption = $validated['smtp_encryption'] === 'none' ? null : $validated['smtp_encryption'];

        OrganizationMailSetting::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'smtp_host' => $validated['smtp_host'],
                'smtp_port' => (int) $validated['smtp_port'],
                'smtp_encryption' => $encryption,
                'smtp_username' => $validated['smtp_username'],
                'smtp_password' => $validated['smtp_password'],
                'mail_from_address' => $validated['mail_from_address'],
                'mail_from_name' => $validated['mail_from_name'] ?? null,
            ]
        );

        return Redirect::route('profile.edit');
    }
}
