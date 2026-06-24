# HRMS SaaS Platform

An enterprise-grade, multi-tenant Human Resource Management System delivered as a
subscription SaaS. Companies subscribe to modules independently (Attendance, Payroll,
Leave, and more), with employee-seat + module-based billing.

> **Status:** Foundation in progress. This repository is being built in reviewable
> increments. See [`docs/`](./docs) for the full architecture blueprint and
> [`docs/ROADMAP.md`](./docs/ROADMAP.md) for build sequencing.

## Tech Stack

| Layer            | Technology |
|------------------|------------|
| Backend API      | Laravel 12, PHP 8.4, REST, Queues, Events, Scheduler |
| Frontend         | Next.js (App Router), TypeScript, Tailwind CSS, shadcn/ui, React Query, Zustand |
| Database         | PostgreSQL 16 |
| Cache / Queues   | Redis 7 (cache, sessions, Horizon queues) |
| Object Storage   | S3-compatible (AWS S3 / Cloudflare R2) |
| Auth             | JWT access + refresh tokens, MFA-ready |
| Infra            | Docker, Nginx, docker-compose (Phase 1), Kubernetes-ready |
| Observability    | Telescope, Horizon, Prometheus/Grafana hooks |

## Repository Layout

```
HRM/
├── docs/                # Architecture blueprint (the source of truth)
├── backend/             # Laravel 12 API (multi-tenant, subscription-driven)
├── frontend/            # Next.js application
└── infra/               # Docker, Nginx, CI/CD, deployment
```

## Architecture at a Glance

- **Multi-tenancy:** Single database, shared schema. Every business table carries
  `company_id`. Tenant isolation is enforced by a global Eloquent scope + resolution
  middleware so cross-tenant access is impossible by default.
- **Subscription-first:** A centralized Subscription Engine + Feature Access Service
  gate every module, feature, and limit. Plan logic is 100% database-driven — no
  hardcoded tier checks.
- **Module system:** Modules are dynamic database records. Plan → module mapping is
  data, not code, so new modules can be sold without redeploys.
- **Billing abstraction:** A gateway-agnostic billing layer with adapters for
  Razorpay, Cashfree, and PayU (Stripe-ready), driven by webhooks.

See [docs/](./docs) for full detail.
