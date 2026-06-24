# 11 — Deployment & DevOps

## Containers

- **backend**: PHP 8.4-FPM image (app code, Composer deps, opcache tuned).
- **nginx**: serves the API and proxies to PHP-FPM.
- **frontend**: Next.js (standalone build) container.
- **postgres**: PostgreSQL 16. **redis**: Redis 7 (cache, sessions, queues).
- **horizon**: queue worker container (Laravel Horizon).
- **scheduler**: runs `php artisan schedule:run` each minute.

## Phase 1 — Single Server (docker-compose)

```
            ┌──────── nginx ────────┐
 client ───▶│  /api → php-fpm       │───▶ postgres
            │  /    → next.js       │───▶ redis ◀── horizon, scheduler
            └───────────────────────┘
```
`infra/docker-compose.yml` brings up the full stack for dev/staging and small prod.

## Phase 2 — Horizontal App Tier

```
        ┌── Load Balancer ──┐
        ▼                   ▼
   app server 1   ...   app server N      (stateless PHP-FPM + nginx)
        └─────────┬─────────┘
                  ▼
        managed PostgreSQL (primary + read replicas)
        managed Redis (cache + queues)         object storage: S3 / R2
```
App tier is stateless (sessions/cache in Redis, files in S3/R2) so it scales out
freely. Queues scale by adding Horizon workers.

## Phase 3 — Kubernetes / Cloud

- Deployments for api, frontend, horizon, scheduler (CronJob); HPA on CPU/queue depth.
- Managed Postgres with replicas + PgBouncer; managed Redis; ingress + cert-manager.
- Secrets via cluster secret store; config via env. Blue/green or rolling deploys.

## CI/CD Pipeline

```
push / PR
  ├─ lint (Pint/PHPStan, ESLint/tsc)
  ├─ backend tests (Pest + tenant-isolation suite) on Postgres+Redis services
  ├─ frontend build + tests
  ├─ build & scan images (SAST + dependency + image scan)
  └─ on main: push images → migrate → deploy (staging → prod with approval)
```
Zero-downtime deploys use expand/contract migrations (see 02-DATABASE).

## Observability

- **Telescope** (local/staging) for request/query/job introspection.
- **Horizon** for queue throughput, failures, retries.
- **Prometheus** metrics endpoint + exporters (app, php-fpm, postgres, redis);
  **Grafana** dashboards (MRR/ARR business metrics + system health); alerting on
  queue backlog, error rate, failed payments, expiring subscriptions.
- Structured JSON logs shipped to a central store with request-id correlation.

## Environments

`local` (compose) → `staging` (prod-like) → `production`. Separate credentials, keys,
and gateway sandboxes per environment.
