# Organizations Domain

Purpose: represent brands monitored for visibility, distinguish primary vs competitor orgs, and surface related content.

## Backend Snapshot
- Model: `Organization` belongs to a team and optionally a campaign, with relationships to `terms`, `articles`, and visibility metrics.
- Fields capture brand metadata (website, logo, keywords, location) and competitor status via `is_competitor`.
- Controllers aggregate mention counts per organization, rank competitors, and supply data for visibility charts.
- Terms associated with an organization drive response scanning and mention tallies.

## Frontend Snapshot
- Vue pages in `resources/js/pages/organizations` list owned and competitor organizations with create/edit flows.
- Users can trigger competitor discovery jobs and manage associated terms within the organization UI.
- Visibility dashboards compare organizations across prompts and campaigns using chart components.
