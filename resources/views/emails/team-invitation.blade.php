Hello {{ $user->name }},

You have been invited to join the team "{{ $team->name }}" as a {{ $role }}.

To accept this invitation, please log in to your account and visit your team invitations page.

Thanks,
{{ config('app.name') }}
