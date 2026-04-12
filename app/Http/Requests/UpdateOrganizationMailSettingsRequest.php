<?php

namespace App\Http\Requests;

use App\Support\OrganizationAccess;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationMailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $organization = $user->currentOrganization();
        if (!$organization) {
            return false;
        }

        return OrganizationAccess::canUserFullyManageTeam($user, $organization);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['required', 'in:tls,ssl,none'],
            'smtp_username' => ['required', 'email', 'max:255'],
            'smtp_password' => ['required', 'string', 'max:500'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'smtp_host.required' => 'The SMTP host is required.',
            'smtp_port.required' => 'The SMTP port is required.',
            'smtp_port.integer' => 'The SMTP port must be a valid number.',
            'smtp_encryption.required' => 'The encryption type is required.',
            'smtp_encryption.in' => 'Choose TLS, SSL, or None.',
            'smtp_username.required' => 'The username or email address is required.',
            'smtp_username.email' => 'Enter a valid email address for the SMTP username.',
            'smtp_password.required' => 'The app password is required.',
            'mail_from_address.required' => 'The from email address is required.',
            'mail_from_address.email' => 'Enter a valid from email address.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'smtp_host' => 'SMTP host',
            'smtp_port' => 'SMTP port',
            'smtp_encryption' => 'encryption',
            'smtp_username' => 'SMTP username',
            'smtp_password' => 'app password',
            'mail_from_address' => 'from email',
            'mail_from_name' => 'from name',
        ];
    }
}
