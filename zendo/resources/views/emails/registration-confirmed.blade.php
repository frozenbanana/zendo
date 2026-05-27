<x-mail::message>
# Registration Confirmed

Hello {{ $guestName }},

Your registration for **{{ $eventTitle }}** has been confirmed!

We look forward to seeing you at the retreat center. If you have any questions, please don't hesitate to reach out.

<x-mail::button :url="url('/hub/events')" button-type="primary">
    View Event Details
</x-mail::button>

Thanks,<br>
The Zendo Team
</x-mail::message>