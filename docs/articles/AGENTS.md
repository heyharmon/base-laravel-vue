# Articles Domain

Purpose: manage long-form content generated from prompt insights, including version history and collaborative discussion threads.

## Backend Snapshot
- Model: `Article` belongs to a team, organization, and prompt; versions persisted through `ArticleVersion` via `HasVersions`.
- Polymorphic `Conversation` and `Chat` models attach discussion threads to articles and emit `ArticleChatCreated` events.
- Articles track integration with external research providers (e.g., Perplexity) including request status and check counts.
- Controllers enforce team usage limits when creating new articles and expose version history endpoints.

## Frontend Snapshot
- Vue pages in `resources/js/pages/articles` support listing, creating, and editing articles with rich text editors.
- Edit views surface version timelines, chat drawers, and prompt context to aid content updates.
- Usage progress components warn when article quotas are reached to prevent blocked saves.
