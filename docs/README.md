# Architecture Blueprint

This directory is the **source of truth** for the HRMS SaaS platform. Code in
`backend/` and `frontend/` implements what is specified here. When a design decision
changes, update these documents first.

## Index

| # | Document | Covers |
|---|----------|--------|
| 00 | [Overview & Principles](./00-OVERVIEW.md) | Product vision, philosophy, system context, bounded contexts |
| 01 | [System Architecture](./01-ARCHITECTURE.md) | Backend layering, frontend architecture, multi-tenant strategy |
| 02 | [Database Schema & ERD](./02-DATABASE.md) | Tables, relationships, indexes, partitioning, migration strategy |
| 03 | [Subscription Engine](./03-SUBSCRIPTION.md) | Plans, features, limits, lifecycle, Feature Access Service |
| 04 | [Billing Engine](./04-BILLING.md) | Gateway abstraction, webhooks, invoices, tax/GST, seat billing |
| 05 | [RBAC](./05-RBAC.md) | Roles, permissions, the full RBAC matrix, enforcement |
| 06 | [Modules](./06-MODULES.md) | Module system + Attendance / Payroll / Leave / Employee specs |
| 07 | [API Specification](./07-API.md) | REST conventions, auth, error format, endpoint catalog |
| 08 | [Security](./08-SECURITY.md) | Tenant isolation, auth, audit, rate limiting, file uploads |
| 09 | [Reporting & Notifications](./09-REPORTING-NOTIFICATIONS.md) | Report engine, exports, multi-channel notifications |
| 10 | [Frontend & UX](./10-FRONTEND-UX.md) | App structure, state, command palette, the three panels |
| 11 | [Deployment & DevOps](./11-DEPLOYMENT.md) | Docker, Nginx, CI/CD, scaling phases, observability |
| 12 | [Roadmap](./ROADMAP.md) | Build sequencing and future expansion strategy |

## How to read this

- **Engineers** start at 01 (architecture) and 02 (schema), then the module they own.
- **Product/founders** start at 00 (overview) and 03/04 (subscription + billing).
- **DevOps** start at 11 (deployment) and 08 (security).



---

## Human-readable HTML manuals

A self-contained documentation site (12 manuals + **test credentials**) lives at
[`site/index.html`](site/index.html). It is also copied into the frontend and
served at `/docs/index.html` when the app is running. Open it in any browser — no
build step required.
