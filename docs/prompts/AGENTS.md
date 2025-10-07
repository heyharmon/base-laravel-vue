# Prompts Domain

Purpose: define the questions sent to LLM providers, orchestrate execution pipelines, and surface visibility analytics for teams.

## Backend Snapshot
- Model: `Prompt` belongs to a team and campaign, with many `responses` and many-to-many `terms` through `term_prompt`.
- Controllers: `PromptController` serves prompt listings, triggers prompt runs, and calculates `mentions_percentage`.
- Background jobs fan out calls to providers such as OpenAI `gpt-4o`, store raw responses, and enqueue term scanning.
- Visibility endpoints aggregate prompt-level metrics for dashboards and charts.

## Frontend Snapshot
- `resources/js/pages/prompts/Index.vue` lists prompts, surfaces usage progress, and allows running prompts individually or in batches.
- Components like `PromptToolbar.vue` expose actions for filtering, generation, and execution.
- Charts in visibility views consume aggregated prompt metrics to visualize trends by period.
