# Teams Domain

Purpose: manage collaborative workspaces, membership, and usage limits that scope nearly every other resource.

## Backend Snapshot
- Core model: `Team` with relationships to users, prompts, organizations, articles, and visibility metrics.
- Controllers: `TeamController` for CRUD, membership, and invitation management; usage endpoints expose per-period resource consumption.
- Billing helpers: `Team::periodBounds`, `responsesUsed`, and `articlesUsed` compute subscription window metrics and enforce limits.
- Cascading deletes ensure dependent prompts, articles, terms, and queued jobs are cleaned up when a team is removed.

## Frontend Snapshot
- Vue pages in `resources/js/pages/teams` cover team creation, member administration, and invitation management.
- Super admin pages aggregate team usage reporting (`resources/js/pages/super-admin`).
- Pinia stores centralize the current team context and usage metrics for prompts and articles.
