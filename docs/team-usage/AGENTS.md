# Team Usage Ledger

Purpose: keep subscription usage accurate even when prompts, responses, or articles are removed.

## Backend Snapshot
- Ledger table: `team_usage_events` stores `team_id`, `resource_type`, and immutable quantity rows per creation event (`database/migrations/2025_09_15_000000_create_team_usage_events_table.php`).
- Model helper: `App\Models\TeamUsageEvent::record` inserts events and centralises type constants.
- Emitters: `Response` and `Article` call the helper from `booted()` when a row is created so deletions downstream never reclaim quota (`app/Models/Response.php`, `app/Models/Article.php`).
- Consumers: `Team::responsesUsed` / `articlesUsed` sum ledger quantities inside the current billing window, ensuring `responsesRemaining` and `articlesRemaining` stay consistent (`app/Models/Team.php`).
- Backfill: the migration seeds events for existing responses and articles, preserving historical usage before the ledger went live.

## Operational Notes
- Adjustments: to grant or revoke usage manually, insert a compensating ledger row with the desired `quantity` (positive to add consumption, negative to credit back) rather than editing counters.
- Deletions: removing prompts, responses, or articles is safe—the ledger is the source of truth and should not be purged without an explicit retention decision.
- Reporting: new usage dashboards should query the ledger instead of counting live resources to remain aligned with quota enforcement.
