# AI Agent Overview

This document gives AI developers a high level overview of this **Paraloom** codebase. This app is an LLM visibility tracker–it uses LLMs to track how often a brand shows up in LLM responses. The backend is Laravel 12 and the frontend is Vue 3 with Pinia stores.

## Prompts, Responses & Visibility

Prompts ask large language models about a campaign or organization. Each prompt is run through background jobs that call one or more LLM providers (currently OpenAI's `gpt‑4o`). Every response records the provider, model, generated content and any search metadata. The response text is scanned for **terms** belonging to the team's owned organization and any competitor organizations in the same campaign. Found terms are attached to the response and the prompt, incrementing mention counts.

These mention counts power visibility metrics:

-   **PromptController@index** returns prompts with response and term counts and calculates `mentions_percentage` – the percentage of responses that mention the team's primary organization.
-   **OrganizationVisibilityController@index** aggregates mentions across all prompts in a campaign and ranks organizations by visibility (mentions ÷ total responses).
-   **OrganizationVisibilityChartController** and **PromptVisibilityChartController** provide visibility over time using daily, weekly or monthly intervals.

## Laravel Models

This section summarises the main Eloquent models and how they relate to each other.

### User

-   Uses Laravel Sanctum for API authentication.
-   Relationships:
    -   `ownedTeams` – has many **Team** records via `owner_id`.
    -   `teams` – belongs to many teams through the `team_user` pivot.
    -   `joinedTeams` – teams where the invitation is accepted.
    -   `currentTeam` – the team selected via `current_team_id`.

### Team

-   Represents a group of users.
-   `owner` (User) and many `users` via `team_user`.
-   Has many `terms`, `prompts`, `organizations`, `articles`, `conversations` and `jobStatuses`.
-   Removing a team deletes related resources and queued jobs.

### Organization

-   Belongs to a team and optionally a campaign.
-   Stores brand details (website, logo, location, keywords array).
-   Has many `terms` and `articles`.
-   `is_competitor` flags whether the organization is a competitor.

### Term

-   Belongs to a team and an organization.
-   Many-to-many with `prompts` through `term_prompt` (with `count` and `last_found_at`).
-   Many-to-many with `responses` through `term_response`.

### Prompt

-   Belongs to a team and a campaign.
-   Many-to-many with `terms` through `term_prompt`.
-   Has many `responses` and `articles`.
-   Attribute `mentions_percentage` indicates how often the primary organization is mentioned.

### Response

-   Belongs to a prompt.
-   Many-to-many with `terms` via `term_response`.
-   Records provider, model, content, metadata and search queries.

### Article

-   Belongs to a team, organization and prompt.
-   Versioned via the `HasVersions` trait (`ArticleVersion` model).
-   Polymorphic `conversations` allow chat threads on an article.
-   Tracks Perplexity request status and check count.

### ArticleVersion

-   Belongs to an article and stores versioned data in JSON.

### Conversation

-   Belongs to a team.
-   Morphs to the `conversable` model (currently articles).
-   Has many `chats`.

### Chat

-   Belongs to a conversation.
-   When attached to an article conversation triggers an `ArticleChatCreated` event.

### InvitationToken

-   Belongs to a team and stores the invitation email, token and expiration.

### JobStatus

-   Belongs to a team.
-   Polymorphic `trackable` relation indicates which model the job is for.

#### Pivot Tables

-   `team_user` – connects users and teams with role and invitation metadata.
-   `term_prompt` – links terms to prompts and counts how often they appear.
-   `term_response` – links terms to responses.

# Laravel Controllers Overview

This section describes the main Laravel controllers and the JSON endpoints they expose. Use it as a quick reference when working with the API layer.

## Authentication

### AuthController

-   `register` – create a user or accept an invitation. Returns the created user and a token.
-   `login` – verify credentials and issue a token. Also sets the current team if not already chosen.
-   `logout` – revoke the current token.

### AuthPasswordController

-   `forgotPassword` – generate a password reset token and send a reset email.
-   `resetPassword` – validate the token and set a new password.

## Teams

### TeamController

Manages team CRUD and invitations.

-   `index` – list owned teams, joined teams and pending invitations.
-   `store` – create a team. Owner is added as admin.
-   `show` – show team details including members and pending invitations.
-   `update` – rename a team (owner or admin only).
-   `destroy`/`delete` – remove a team (owner only).
-   `invite` – invite a user via email and send an invitation link.
-   `acceptInvitation` / `declineInvitation` – manage invitation responses.
-   `removeMember` – remove a user from the team.
-   `updateMemberRole` – change a member role.
-   `switchTeam` – change the authenticated user’s current team.

### TeamPasswordResetController

-   `generateResetUrl` – owner or admin can generate a password reset link for a member.

## Organizations

### OrganizationController

CRUD for organizations (owned brand or competitors).

-   `index` – list organizations for the current team with term counts.
-   `store` – create an organization and default terms. Dispatches a job to scan past responses.
-   `show` – view a single organization.
-   `update` – update organization details.
-   `destroy` – delete a competitor organization.


### OrganizationCompetitorController

-   `find` – iterate over prompt responses to detect competitors using `FindCompetitorsInResponseJob`.

### OrganizationSearchController

-   `search` – search for brand names via BrandFetch API.
-   `brandDetails` – fetch details for a brand identifier.

### OrganizationVisibilityController

Provides visibility metrics (mentions / total responses) for organizations.

-   `index` – optimized DB queries with optional date range.
-   `indexAlternative` – Eloquent implementation.
-   `indexCached` – cached version of metrics.

### OrganizationVisibilityChartController

-   `chartData` – returns visibility over time (daily/weekly/monthly) for charting.

### SuperAdminOrganizationController

Endpoints for super admins to browse organizations across all teams.

-   `index` – filterable list with visibility percentages.
-   `stats` – summary counts of organizations and teams.
-   `teams` – return teams for filtering dropdowns.

### SuperAdminOrganizationExportController

-   `export` – export selected organizations along with prompts and responses.

## Terms

### TermController

Manage tracking terms per organization.

-   `index` – list terms for an organization with prompt counts.
-   `show` – term details including prompt pivot data.
-   `store` – create a term and scan past responses via job.
-   `destroy` – delete a term.

### TermGeneratorController

-   `generate` – given a domain, return associated brand keywords using Prism + search tool.

### TermResponsesController

-   `index` – list responses for a prompt that mention a given term.

## Prompts

### PromptController

CRUD for prompt records.

-   `index` – list prompts for the team with counts for terms and responses.
-   `show` – single prompt with its terms and articles.
-   `store` – create a prompt.
-   `update` – update a prompt.
-   `destroy` – delete a prompt.

### PromptGeneratorController

-   `generate` – create suggested prompts for an organization using Prism.

### PromptRunController

-   `store` – run a single prompt immediately or as a batch. Uses `RunPromptJob`.

### PromptRunBatchController

-   `store` – queue a batch job to run all prompts using `RunAllPromptsJob`.

### PromptResponsesController

-   `index` – list responses for a prompt.

### PromptExportController

-   `show` – export a prompt with its responses (provider, model, content, search).

## Job Status

### JobStatusController

-   `getTeamJobs` – list recent jobs for the team.
-   `cancelTeamJobs` – cancel all pending jobs.

## Articles

### ArticleController

CRUD plus Perplexity integration for writing articles.

-   `index` – list articles for the team.
-   `store` – create an article (optionally linked to a prompt).
-   `show` – single article with versions list.
-   `update` – update an article.
-   `destroy` – delete an article.
-   `getPerplexityResponse` – fetch async completion status.

### ArticleVersionController

-   `revert` – revert an article to a previous version.

### ArticleChatController

-   `index` – get chat history for an article (per conversation).
-   `store` – send a chat message to OpenAI and process asynchronously.

### ArticleConversationController

-   `index` – list conversations for an article.
-   `store` – create a new conversation.

# Frontend API and Pinia Stores Overview

This section explains the structure of the Vue 3 frontend. The Vue app lives under `resources/js` and interacts with the Laravel API through Pinia stores.

## Entry Point & Router

-   `app.js` sets up the Vue application with the Pinia store and router.
-   Routes are defined in `router/index.js` using Vue Router. Pages under `resources/js/pages` handle authentication, teams, organizations, prompts, articles and other features. Auth guards ensure users are logged in before accessing most routes.

## API Helper

All HTTP requests go through `services/api.js`, an Axios instance that automatically attaches the auth token from local storage and handles global error notifications.

## Pinia Stores (`stores` folder)

The stores act as the frontend gateway to the Laravel backend. Each store wraps a set of API endpoints and exposes reactive state and actions. Key stores include:

### `articleStore.js`

Manages article editing features and chat interactions.

-   **State:** list of articles, current article, loading flags, version data and chat messages.
-   **Actions:** fetch/create/update/delete articles, auto-save content, revert to past versions, fetch chats, send chat messages.

### `conversationStore.js`

Handles simple chat conversations not tied to articles.

-   **State:** list of conversations and currently active conversation.
-   **Actions:** fetch, create and update conversations, select the active conversation.

### `jobStatusStore.js`

Tracks background jobs dispatched by the backend.

-   **State:** list of job status records and batch details.
-   **Actions:** fetch/poll team jobs, cancel jobs and group jobs by class. Auto‑refresh runs while jobs are pending or processing.

### `notificationStore.js`

Lightweight store used to display toast notifications. Other stores add notifications when API requests fail.

### `organizationStore.js`

Works with organizations (owned brands and competitors).

-   **State:** organization list, selected organization, visibility metrics and current date range.
-   **Actions:** CRUD operations, onboarding, fetch visibility statistics and launch competitor discovery jobs.

### `promptStore.js`

Manages prompts sent to LLM providers.

-   **State:** list of prompts, loading flags, selected prompt details and responses.
-   **Actions:** fetch/update/create/delete prompts, run a single prompt or all prompts in batch, and load prompt responses.

### `teamStore.js`

Controls team membership and invitations.

-   **State:** teams owned and joined by the user, pending invites, current team and its members.
-   **Actions:** fetch teams, create/update/delete teams, invite members, accept/decline invites, switch current team and update member roles.

### `termStore.js`

Handles terms associated with an organization.

-   **State:** term list, selected term details, loading flags and responses for a term/prompt pair.
-   **Actions:** fetch/create/delete terms, show term details and load past responses mentioning that term. Uses `jobStatusStore` when scanning historical data.

# Frontend Authentication Overview

This file summarizes how authentication and basic authorization are handled on the Vue side of the application. It is intended for AI agents or developers exploring the `resources/js/services` directory.

## Auth Service (`auth.js`)

-   Provides `login`, `register`, `logout`, `forgotPassword`, and `resetPassword` methods.
-   Successful login/registration responses are expected to include a `token` and `user` object. These are stored in `localStorage` under the keys `token` and `user`.
-   `logout` removes these items from `localStorage` after calling `/logout` on the backend API.
-   `getToken`, `getUser`, and `isAuthenticated` are helpers for retrieving stored data or checking if a user is logged in.

## API Wrapper (`api.js`)

-   Wraps Axios and automatically sets `Authorization: Bearer <token>` on all requests when a `token` is present in `localStorage`.
-   Handles errors globally. A `401` response clears the token/user from storage and redirects to `/login`.

## Router Guards

-   Routes declare either `meta.requiresAuth` or `meta.guest` in `resources/js/router/index.js`.
-   A `beforeEach` hook checks for a stored token:
    -   Guest routes (`meta.guest`) redirect authenticated users to `/`.
    -   Auth‑protected routes (`meta.requiresAuth`) redirect unauthenticated users to `/login` and preserve the desired URL in the `redirect` query parameter.

## Authorization

-   There is no role-based logic on the frontend beyond the route checks described above. Team membership and roles are enforced by the backend API. Frontend stores (e.g., `teamStore`) fetch data after login and rely on the backend to determine the current team and permissions.

The routes are defined in `resources/js/router/index.js`. Most pages require authentication (`meta.requiresAuth`) except the auth related screens (`meta.guest`). Below is a summary of the main route paths and their corresponding page components:

| Path                         | Name                        | Component                                     |
| ---------------------------- | --------------------------- | --------------------------------------------- |
| `/`                          | `home`                      | `Dashboard.vue`                               |
| `/dashboard`                 | `dashboard`                 | `Dashboard.vue`                               |
| `/login`                     | `login`                     | `auth/Login.vue`                              |
| `/register`                  | `register`                  | `auth/Register.vue`                           |
| `/forgot-password`           | `forgot-password`           | `auth/ForgotPassword.vue`                     |
| `/reset-password`            | `reset-password`            | `auth/ResetPassword.vue`                      |
| `/invitations`               | `invitations.index`         | `invitations/Index.vue`                       |
| `/teams/:id`                 | `teams.show`                | `teams/Show.vue`                              |
| `/teams/create`              | `teams.create`              | `teams/Create.vue`                            |
| `/organizations`             | `organizations.index`       | `organizations/Index.vue`                     |
| `/organizations/create`      | `organizations.create`      | `organizations/Create.vue`                    |
| `/organizations/:id/edit`    | `organizations.edit`        | `organizations/Edit.vue`                      |
| `/prompts`                   | `prompts.index`             | `prompts/Index.vue`                           |
| `/articles`                  | `articles.index`            | `articles/Index.vue`                          |
| `/articles/:id/edit`         | `articles.edit`             | `articles/Edit.vue`                           |
| `/super-admin/organizations` | `super-admin.organizations` | `super-admin/Organizations.vue` (lazy loaded) |

Navigation guards redirect guests and require auth tokens. Team checks are skipped for creating teams and viewing invitations.

## Page Summaries

### Dashboard

Main landing page after login showing job activity and visibility rankings for organizations. Uses components such as `VisibilityScore` and `VisibilityChart`.

### Auth Pages

Located in `pages/auth`:

-   **Login.vue** – user login form with email/password.
-   **Register.vue** – registration form. If an invitation token is provided it joins a team.
-   **ForgotPassword.vue** – request a password reset email.
-   **ResetPassword.vue** – set a new password using token from email link.

### Invitations

`pages/invitations/Index.vue` displays pending team invitations and allows the user to accept or decline them.

### Teams

-   **Create.vue** – create a new team and its primary organization using an organization search component.
-   **Show.vue** – manage a team, including members, invitations, and role changes.

### Organizations

-   **Index.vue** – lists the owned organization and competitor organizations. Provides actions to add competitors and trigger competitor discovery jobs.
-   **Create.vue** – add a competitor organization by domain search.
-   **Edit.vue** – edit organization details and manage associated terms.

### Prompts

`pages/prompts/Index.vue` lists prompts, provides actions to run prompts, generate new ones, and view details via a side sheet.

### Articles

Pages under `pages/articles` manage user written content:

-   **Index.vue** – list existing articles with create, edit, and delete actions.
-   **Edit.vue** – rich text editor with settings, version history, and chat integration.
    (Additional files like `"Edit copy.vue"` appear to be unused duplicates.)

### Super Admin

`pages/super-admin/Organizations.vue` – admin dashboard to search, filter, and export organizations across all teams. Only reachable at `/super-admin/organizations`.
