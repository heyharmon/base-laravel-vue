# Auth Domain

Purpose: orchestrate user signup, login, logout, and credential recovery so agents can authenticate API requests.

## Backend Snapshot
- Controllers: `AuthController` (register, login, logout) and `AuthPasswordController` (forgot/reset password) drive Sanctum token issuance.
- Sanctum tokens tie each session to a `current_team_id`, enabling team-scoped queries.
- Invitation tokens can be consumed during registration to auto-associate a user with a team.
- Password reset flow queues notifications that deliver reset links referencing stored tokens.
- Horizon dashboard protection relies on HTTP basic auth. The middleware alias `horizonBasicAuth` challenges non-local requests and validates credentials defined in `HORIZON_BASIC_AUTH_USERNAME` / `HORIZON_BASIC_AUTH_PASSWORD` env vars before Horizon renders.

## Frontend Snapshot
- Vue pages in `resources/js/pages/auth` collect credentials for login/registration and trigger password reset requests.
- Shared composables/Pinia stores maintain the authenticated user, current team, and active token for API clients.
- Route guards leverage the auth store to protect team resources and redirect unauthenticated users to the login page.
