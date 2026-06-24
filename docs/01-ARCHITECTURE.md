# 01 — System Architecture

## Backend Layering (Laravel 12)

Strict, one-directional dependencies. Controllers never touch Eloquent directly.

```
HTTP (Controller / FormRequest / Resource)
        │  validates input, shapes output, no business logic
        ▼
Service Layer (App\Domains\*\Services)
        │  business rules, orchestration, transactions, fires events
        ▼
Repository Layer (App\Domains\*\Repositories)
        │  all persistence; the only layer that knows Eloquent
        ▼
Models (tenant-aware) ──▶ PostgreSQL
```

Cross-cutting concerns live in `App\Support` and `App\Platform`:

- **Tenancy** — context holder, global scope, resolution middleware.
- **Subscription** — Subscription Engine + Feature Access Service.
- **Billing** — gateway contract + adapters.
- **Authorization** — policies, permission gates.
- **Events/Listeners** — domain events drive notifications, audit, payroll hooks.
- **Jobs** — queued work (payroll runs, exports, webhook processing, accruals).

### Backend folder structure

```
backend/app/
├── Domains/
│   ├── Company/        { Models, Services, Repositories, Policies, Events }
│   ├── Identity/       { User, Role, Permission, Auth services, MFA }
│   ├── Subscription/   { Plan, Subscription, Feature, FeatureAccessService, Engine }
│   ├── Billing/        { Gateways/, Invoice, Payment, Webhooks, TaxCalculator }
│   ├── Employee/       { Employee, Department, Designation, Document }
│   ├── Attendance/     { AttendanceRecord, Shift, services, reports }
│   ├── Leave/          { LeaveType, LeavePolicy, LeaveBalance, LeaveRequest }
│   ├── Payroll/        { SalaryStructure, Component, PayrollRun, Payslip }
│   ├── Reporting/      { report builders, exporters }
│   └── Notification/   { channels, dispatcher, templates }
├── Platform/
│   ├── Tenancy/        { TenantContext, BelongsToTenant scope, middleware }
│   ├── Http/           { base Controller, Resource, ApiResponse }
│   └── Concerns/       { traits: HasAuditColumns, HasUuid }
├── Support/            { helpers, value objects, enums }
└── Providers/
```

> **Why service + repository:** testability (mock repos), swappable persistence,
> a single place to enforce tenancy/transactions, and thin controllers.

## Multi-Tenant Strategy

**Single database, shared schema, row-level isolation by `company_id`.**

Chosen over database-per-tenant because:
- 10,000+ tenants on one Postgres cluster is operationally sane; thousands of
  databases/connections is not.
- Cheaper migrations and pooled connections.
- Cross-tenant platform analytics (MRR/ARR) are simple aggregate queries.

Isolation mechanics:

1. **Resolution middleware** sets the active tenant from the authenticated user's
   `company_id` (Super Admin requests run *without* a tenant and must opt into a
   tenant explicitly for support actions).
2. **`BelongsToTenant` global scope** auto-injects `where company_id = ?` on every
   query and auto-fills `company_id` on insert for tenant-aware models.
3. **Defense in depth:** foreign keys + a DB-level check that child rows share the
   parent's `company_id`; a test suite asserts no model leaks across tenants.

```php
// Every tenant-aware model:
class Employee extends Model {
    use BelongsToTenant; // adds global scope + auto company_id on create
}
// A query in tenant context is automatically scoped:
Employee::count(); // => SELECT count(*) ... WHERE company_id = <current>
```

Partitioning for high-volume tables (attendance, audit, notifications) is described
in [02-DATABASE.md](./02-DATABASE.md).

## Frontend Architecture (Next.js)

- **App Router** with three route groups: `(super-admin)`, `(company)`, `(employee)`,
  plus `(auth)`.
- **Server state:** React Query (caching, retries, optimistic updates).
- **Client state:** Zustand (auth/session, tenant context, UI such as command palette).
- **UI:** Tailwind + shadcn/ui design system; a shared `packages/ui` of primitives.
- **API client:** a typed fetch wrapper handling JWT refresh, tenant header, and the
  standard error envelope.

Detailed in [10-FRONTEND-UX.md](./10-FRONTEND-UX.md).

## Asynchronous Processing

| Concern | Mechanism |
|---------|-----------|
| Payroll runs, bulk imports, exports | Queued jobs (Horizon), chunked + idempotent |
| Webhooks | Stored raw, then processed by a job; idempotent on event id |
| Leave accrual, subscription expiry sweeps | Scheduler → dispatch jobs |
| Notifications | Event → listener → per-channel queued job |

## Key Cross-Cutting Flows

- **Every write** runs inside a service method, inside a DB transaction, emits a
  domain event, and (for sensitive actions) writes an audit log entry.
- **Every feature-gated action** calls the Feature Access Service before mutating.
- **Every list/read** is tenant-scoped automatically; pagination is mandatory.
