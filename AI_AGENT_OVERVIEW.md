# AI Agent Overview

This document gives AI developers a concise view of the **LLM Mention Tracker** codebase. The app records how brands show up in LLM responses. The backend is Laravel 12 and the frontend is Vue 3 with Pinia stores.

## Core Domain

### Teams and Users

-   **Team** – group of users. Teams own most resources.
-   **User** – authenticates via Laravel Sanctum. Users can belong to many teams and switch their current team. Team invitations are handled via tokens and email.

### Organizations & Terms

-   **Organization** – represents a company/brand. Each team has one primary organization (not a competitor) and many competitor organizations. Organizations store website, logo, industry, location and other info. They also keep an array of suggested terms (`terms`).
-   **Term** – word or phrase to track. Terms belong to an organization and a team. A `is_recommended` flag marks suggested terms waiting for user approval.

### Prompts & Responses

-   **Prompt** – text sent to LLMs. Prompts belong to a team and have many terms through the `term_prompt` table which stores `count` and `last_found_at`. A computed attribute `mentions_percentage` shows how often the team’s owned organization is mentioned in its responses.
-   **Response** – output returned from an LLM. Each response stores the provider, model, content, metadata and any search queries used. Responses belong to a prompt and relate to terms via `term_response`.

### Jobs & JobStatus

-   **JobStatus** – tracks queued jobs. Uses a polymorphic `trackable` relation so any model (prompt, term, organization, etc.) can own job status records. Fields include progress, status, batch id and error messages.
-   **TrackableJob** – base job class providing helpers to mark status updates.
-   **RunPromptJob** – sends a prompt to one or more LLM providers using Prism and optional search tools. It saves the response, records search queries, checks for terms and triggers competitor discovery on a prompt’s first response.
-   **RunAllPromptsJob** – batch runs every prompt for a team.
-   **CheckTermInPastResponsesJob** – scans previous responses for a term and updates pivot tables.
-   **FindCompetitorsInResponseJob** – analyzes a prompt response to extract competitor organizations and creates them with associated terms.
-   **GeneratePrompt** – turns a term term into a new prompt and immediately queues it for execution.
-   **GenerateOrganizationKeywords** – generates terms for an organization and then dispatches `GeneratePrompt` for each term.

### Services & Tools

-   **JobDispatcherService** – wraps the queue/batch API and automatically creates `JobStatus` entries when dispatching or batching jobs.
-   **ChatService** – simple conversation service that stores chats and uses the OpenAI PHP client to generate assistant replies.
-   **BrandFetchService** – wrapper around the BrandFetch API for looking up brand details.
-   **SearchApiTool**, **SearchTool**, **SearchToolFirecrawl** – Prism tools used by jobs to perform external web searches.

## API Layer

Key controllers provide JSON endpoints (see `routes/api.php`):

-   Authentication: `AuthController` and `AuthPasswordController`.
-   Team management and invitations: `TeamController`.
-   Organization CRUD, onboarding, competitor generation, visibility metrics and brand search.
-   Term CRUD plus generator and recommendations.
-   Prompt CRUD, generation, running individual prompts or all prompts in batch, and viewing prompt responses.
-   Analytics endpoints for term stats, prompt stats and time‑series data.
-   Job status retrieval (`JobStatusController`).
-   Conversation and chat endpoints for the demo chat feature.

## Frontend Structure

-   Vue 3 with Pinia. Stores live in `resources/js/stores` (teams, prompts, terms, organizations, jobs, chat, etc.).
-   Components are under `resources/js/components` and pages under `resources/js/pages`. Routing is configured in `resources/js/router/index.js`.
-   API calls are made through `resources/js/services/api.js` which attaches the auth token from local storage.

This overview should help you navigate the codebase and implement additional features confidently. Check the models, jobs and controllers referenced above for further detail.
