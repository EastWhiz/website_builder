<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class OrganizationMailerRegistry
{
    /**
     * Register a dedicated SMTP mailer for the organization and return its name.
     */
    public static function register(Organization $organization): ?string
    {
        $organization->loadMissing('mailSetting');
        $setting = $organization->mailSetting;
        if (!$setting) {
            return null;
        }

        $name = 'organization_smtp_'.$organization->id;
        $smtpConfig = config('mail.mailers.smtp', []);

        Config::set("mail.mailers.{$name}", [
            'transport' => 'smtp',
            'url' => null,
            'host' => $setting->smtp_host,
            'port' => (int) $setting->smtp_port,
            'encryption' => $setting->smtp_encryption ?: null,
            'username' => $setting->smtp_username,
            'password' => $setting->smtp_password,
            'timeout' => (int) ($smtpConfig['timeout'] ?? 30),
            'local_domain' => $smtpConfig['local_domain'] ?? null,
        ]);

        Mail::purge($name);

        return $name;
    }
}
