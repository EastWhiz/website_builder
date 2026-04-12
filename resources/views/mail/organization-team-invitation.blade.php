<x-mail::message>
Dear {{ $inviteeName }},

I hope this message finds you well.

You have been invited by **{{ $organizationName }}** to join our professional network. We would be delighted to have you as part of our community.

Kindly take a moment to review and accept the invitation at your convenience.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

If you have any questions or need assistance during the process, please feel free to reach out.

Warm regards,<br>
**{{ $inviterName }}**<br>
{{ $inviterRole }}<br>
{{ $organizationName }}
</x-mail::message>
