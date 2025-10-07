# Invitations Domain

Purpose: coordinate how teams invite collaborators, track outstanding tokens, and record acceptance outcomes.

## Backend Snapshot
- Model: `InvitationToken` captures the invitee email, token value, expiration timestamp, and owning team.
- Team endpoints create invitations, list pending invitations, and clean up tokens after acceptance or expiration.
- Registration and login flows accept a valid token, attach the user to the inviting team, and mark the invitation complete.
- Pivot table `team_user` stores role metadata once an invitation is accepted.

## Frontend Snapshot
- Team settings pages surface the invitation list alongside actions to create or revoke invites.
- `resources/js/pages/invitations/Index.vue` shows pending invitations for the authenticated user with accept/decline controls.
- Auth forms read `token` query params to automatically populate invitation tokens when launched from email links.
