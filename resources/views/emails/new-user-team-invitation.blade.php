Hello {{ $user->name }},

You have been invited to join the team "{{ $team->name }}" as a {{ $role }}.

Since you're new to our platform, you'll need to set up your account first.
Use the link below to set your name and password:

{{ url('/#/register?token=' . $token . '&email=' . urlencode($user->email)) }}

This link will expire in 24 hours.

Thanks,
{{ config('app.name') }}
