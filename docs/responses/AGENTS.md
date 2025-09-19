# Responses Domain

Purpose: capture provider outputs, attach term matches, and feed visibility metrics used across dashboards.

## Backend Snapshot
- Model: `Response` belongs to a prompt and tracks provider, model, generated content, metadata, and search context.
- Many-to-many relationships with `Term` via `term_response` persist match counts for owned and competitor organizations.
- Scanning services parse response text, attach matched terms, and update prompt and organization mention tallies.
- Aggregation controllers (e.g., `OrganizationVisibilityController`, `PromptVisibilityChartController`) expose metrics for timelines and rankings.

## Frontend Snapshot
- Prompt detail drawers and visibility dashboards render response excerpts alongside mention counts and provider stats.
- Visualization components consume aggregated response data to chart visibility trends over configurable periods.
- Usage overlays warn when response quotas are exhausted before new prompt runs are scheduled.
