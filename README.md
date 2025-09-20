# Paraloom

Tracks brand visibility in LLM responses for marketing teams. Agents: start with `docs/AGENTS.md`.

## Redis & Horizon

Install the Redis server with Homebrew:

- `brew install redis`

Start the Redis daemon locally:

- `brew services start redis`

Horizon commands:

- `php artisan horizon` — start processing jobs
- `php artisan horizon:terminate` — stop the Horizon workers
- `php artisan horizon:clear-metrics` — clear stored Horizon metrics
- `php artisan horizon:forget --all` — delete all failed jobs
- `php artisan horizon:clear` — delete all jobs from the default queue
- `php artisan horizon:clear --queue=emails` — delete jobs in a specific queue (e.g., `emails`)
- Horizon dashboard access in staging / production is guarded by HTTP basic auth. Configure `HORIZON_BASIC_AUTH_USERNAME` and `HORIZON_BASIC_AUTH_PASSWORD` in each environment; the `horizonBasicAuth` middleware challenges requests before the dashboard loads.
