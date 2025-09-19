# Campaigns Domain

Purpose: organize prompts, organizations, and responses into marketing initiatives so teams can track visibility across shared goals.

## Backend Snapshot
- Campaigns belong to teams and group prompts plus related organizations for reporting.
- Batch endpoints (e.g., `POST /api/teams/{team}/campaigns/{campaign}/prompt-run-batch`) execute multiple prompts while enforcing response quotas.
- Visibility controllers expose campaign-level rankings and time-series metrics derived from prompt and response data.
- Authorization policies scope campaign access to users on the corresponding team.

## Frontend Snapshot
- Campaign dashboards summarize prompt performance, response volume, and organization mentions for the active campaign.
- Batch run actions allow users to execute all campaign prompts and immediately reflect quota usage in the UI.
- Charts and tables render campaign visibility trends using aggregated API responses.
