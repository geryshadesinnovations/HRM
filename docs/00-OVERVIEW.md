# 00 — Overview & Principles

## Product Vision

A commercial, subscription-driven HRMS SaaS where companies subscribe to modules
independently and pay based on **employee seats + active modules + add-ons**. The
platform must scale from a single company to 10,000+ companies on the same codebase
without a redesign.

## Design Principles

1. **Subscription is the spine.** Every capability is gated by the Subscription
   Engine. There is no feature that bypasses entitlement checks.
2. **Tenant isolation by default.** A request can never read or write another
   tenant's data. Isolation is enforced at the data layer (global scope), not left to
   individual queries.
3. **Data is never destroyed automatically.** Expiry/suspension disables *access*,
   not data. Reactivation restores full access.
4. **Configuration over code.** Plans, modules, features, limits, prices, roles, and
   permissions are database records. Selling a new plan or module requires no deploy.
5. **Modules are independent.** Attendance works without Payroll; Payroll consumes
   Attendance when present but degrades gracefully when it is not licensed.
6. **Premium, fast UX.** New users productive within 10 minutes; command palette,
   global search, quick actions; no ERP-style clutter.

## Actors

| Actor | Scope | Examples |
|-------|-------|----------|
| **Super Admin** | Platform-wide (no tenant) | Manages companies, plans, modules, billing, audit |
| **Company Admin** | Single tenant | Owns company settings, subscription, employees |
| **HR Manager** | Single tenant | Runs payroll, manages attendance/leave, employees |
| **Manager** | Single tenant, team-scoped | Approves leave, views team attendance |
| **Employee** | Single tenant, self-scoped | Self-attendance, leave requests, payslips, profile |

## System Context (C4 Level 1)

```
                    ┌─────────────────────────┐
   Employees /      │     Next.js Frontend     │
   HR / Admins ────▶│  (Super Admin / Company  │
                    │   Admin / Employee SPA)  │
                    └────────────┬─────────────┘
                                 │ HTTPS / JWT (REST)
                                 ▼
                    ┌─────────────────────────┐      ┌──────────────┐
                    │      Laravel 12 API      │◀────▶│  PostgreSQL  │
                    │  (tenant-scoped, subs-   │      └──────────────┘
                    │   gated, service layer)  │      ┌──────────────┐
                    │                          │◀────▶│    Redis     │
                    └───┬──────────┬───────────┘      └──────────────┘
                        │          │
              webhooks  │          │ async jobs (Horizon)
                        ▼          ▼
              ┌──────────────┐  ┌──────────────────────────┐
              │ Payment GWs  │  │ Mail / SMS / S3 / R2      │
              │ Razorpay/... │  │ object storage, exports   │
              └──────────────┘  └──────────────────────────┘
```

## Bounded Contexts

- **Platform**: Companies, plans, modules, subscriptions, billing, invoices, audit.
- **Identity & Access**: Users, roles, permissions, sessions, MFA, login history.
- **Core HR**: Employees, departments, designations, documents, employment history.
- **Attendance**: Records, shifts, check-in/out, overtime, regularization.
- **Leave**: Types, policies, balances, requests, approval workflow, calendar.
- **Payroll**: Salary structures, components, payroll runs, payslips, statutory.
- **Reporting**: Cross-context read models and exports.
- **Notifications**: Multi-channel delivery and event subscriptions.

Each context maps to a backend domain module under `backend/app/Domains/*` and is
licensed (where customer-facing) through the module system.
