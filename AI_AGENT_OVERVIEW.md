# AI Agent Development Guide

This document summarizes the key components of the **LLM Mention Tracker** application. It is intended for AI agents working on the project to quickly gain context and effectively implement new features.

## Overview

The application tracks how often specified keywords appear in the responses of various Large Language Models (LLMs). Users create organizations and keywords that are associated with those organizations. Users then define prompts relevant to all organizations, run them across different providers, and collect responses to analyze keyword mentions. The stack consists of a Laravel API backend and a Vue 3 frontend.

## Core Concepts

### Models
- **Team** – Group of users. Most resources belong to a team.
- **Keyword** – Word or phrase to track in LLM responses.
- **Prompt** – Text sent to LLMs. Associated with multiple keywords.
- **Response** – Output from an LLM for a prompt. Linked to keywords found in the text.
- **JobStatus** – Tracks queued jobs for running prompts. Implements job progress and batch information.
- **Conversation/Chat** – Simple chat feature used by the `ChatService` to demonstrate AI conversations.

### Relationships
- Prompts -> Team (many-to-one) (prompts belong to a team) 
- Keywords -> Organization (many-to-one) (keywords belong to an organization) 
- Prompts <-> Keywords (many‑to‑many with count & last_found_at fields).
- Responses -> Prompt (one‑to‑many).
- Responses <-> Keywords (many‑to‑many).
- JobStatus uses a polymorphic `trackable` relation to associate job records with Prompts or other models.

### Services
- **ChatService** – Generates chat responses using Prism and stores conversation history.
- **JobDispatcherService** – Wraps Laravel’s queue/batch system and records `JobStatus` entries for jobs.

### Jobs
- **RunPromptJob** – Executes a prompt across configured LLM providers. It checks responses for keywords, saves search tool results, and updates `JobStatus` progress.
- **TrackableJob** – Base class for jobs that handles status updates (pending, processing, completed, failed).

### Tools
- **SearchApiTool** / **SearchTool** / **SearchToolFirecrawl** – Prism tools used by jobs to perform web searches when generating LLM responses.

### Controllers & API Routes
Key controllers expose REST endpoints for managing resources:
- `KeywordController`, `PromptController`, `ResponseController` – CRUD for core models.
- `PromptRunController` and `PromptRunBatchController` – Queue jobs to run prompts individually or in batch.
- `AnalyticsController` – Provides stats and time‑series data on keyword occurrences and prompt activity.
- `JobStatusController` – Retrieve job and batch information.
- `TeamController`, `AuthController`, `InvitationController` – Team management and authentication.
- Generator endpoints: `KeywordGeneratorController` and `PromptGeneratorController` use Prism + search tools to suggest keywords and prompts for a domain.

Routes are defined in `routes/api.php` and grouped by authentication and team requirements.

### Frontend Structure
- Vue 3 with Pinia stores (`resources/js/stores`). Stores mirror API resources (keywords, prompts, jobs, teams, etc.).
- Components organized under `resources/js/components` for prompts, keywords, jobs, chat, and generic UI elements.
- Pages under `resources/js/pages` with router configuration in `resources/js/router/index.js`.

### Authentication & Teams
The API uses Laravel Sanctum. Users belong to a `Team`; most queries scope results by the authenticated user’s current team. Invitation tokens allow team invitations and registration.

## Extending the App
- New jobs should extend `TrackableJob` to automatically record progress.
- When adding models that launch jobs, include the `HasJobStatus` trait to link to `JobStatus` records.
- API responses expect JSON. Use the existing `api.js` service for Axios calls in the frontend.
- For new analytics metrics, consider adding methods to `AnalyticsController` and corresponding Pinia stores/pages.
- Frontend UI is built with basic Tailwind and custom Vue components; follow existing patterns in `resources/js/components/ui`.

## Useful Files
- `app/Models` – Eloquent models and relationships.
- `app/Jobs` – Queue jobs including `RunPromptJob`.
- `app/Services` – Service classes such as `JobDispatcherService`.
- `routes/api.php` – REST API route definitions.
- `resources/js` – Vue application (components, pages, stores, router).

This overview aims to provide enough context for AI agents to understand how the application works and where to integrate additional functionality. Consult the source code and existing PRD documents (`README.md`, `PRD-job-statuses-and-dispatcher.md`) for detailed requirements.
