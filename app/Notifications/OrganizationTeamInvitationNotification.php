<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationTeamInvitationNotification extends Notification
{
    public function __construct(
        public string $token,
        public string $organizationName,
        public string $inviterName,
        public string $inviterRoleLabel,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'invitation' => '1',
        ], false));

        return (new MailMessage)
            ->subject('Invitation to Join Our Network')
            ->markdown('mail.organization-team-invitation', [
                'inviteeName' => $notifiable->name,
                'organizationName' => $this->organizationName,
                'inviterName' => $this->inviterName,
                'inviterRole' => $this->inviterRoleLabel,
                'acceptUrl' => $acceptUrl,
            ]);
    }
}
