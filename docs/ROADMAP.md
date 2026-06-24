# 12 — Roadmap & Future Expansion

This platform is built in **reviewable increments**. Each phase ends with a green CI
build and a PR. Earlier phases unblock later ones.

## Build Sequencing

### Phase 0 — Foundation (in progress)
- [x] Architecture blueprint (`docs/`)
- [ ] Laravel 12 backend scaffold + folder structure (service/repository layers)
- [ ] Docker / compose / Nginx for local dev
- [ ] Base API conventions: response envelope, error codes, auth scaffolding

### Phase 1 — Platform Spine
- [ ] Multi-tenant core (`BelongsToTenant` scope, tenant middleware, base model)
- [ ] Identity & Auth (JWT + refresh, users, sessions, login history, MFA scaffolding)
- [ ] RBAC (roles, permissions, permission groups, middleware + policies)
- [ ] Subscription Engine + Feature Access Service + module/feature/plan seeders
- [ ] Tenant-isolation test suite in CI

### Phase 2 — Billing
- [ ] Gateway abstraction + Razorpay adapter (Cashfree/PayU next)
- [ ] Invoices, payments, webhooks (idempotent), tax/GST, PDF generation
- [ ] Subscription lifecycle jobs (sweep, renew, dunning)

### Phase 3 — Core HR + first revenue modules
- [ ] Employee/Department/Designation + bulk import
- [ ] Attendance (manual + self) + reports/exports
- [ ] Leave (types, policies, balances, approval workflow, calendar)

### Phase 4 — Payroll
- [ ] Salary components/structures, payroll runs, payslips
- [ ] Attendance/leave integration, statutory (PF/ESI/tax), reports

### Phase 5 — Frontend
- [ ] Next.js scaffold + design system + API client + auth
- [ ] Employee portal → Company Admin panel → Super Admin panel
- [ ] Command palette, global search, onboarding

### Phase 6 — Reporting, Notifications, Hardening
- [ ] Report engine + scheduled delivery
- [ ] Multi-channel notifications + preferences
- [ ] Observability (Prometheus/Grafana), load testing, security review

## Future Expansion Strategy

- **New modules** (Recruitment, Assets, Performance, Expenses → Accounting, ERP, CRM,
  AI Analytics) are additive: register a module code, seed a `modules` row, map to
  plans, build the domain folder. No core changes.
- **New payment gateways** (Stripe, regional gateways): implement the `PaymentGateway`
  contract; switch via config.
- **New capture methods** for attendance (GPS/QR/biometric/face): the `source` column
  and feature flags already accommodate them.
- **Mobile apps**: consume the same versioned REST API; entitlements drive feature
  availability identically.
- **AI Analytics**: read models / replicas feed analytics without impacting OLTP.
- **Internationalization**: currency + timezone + locale are per-company; tax strategy
  is pluggable beyond India GST.

## Definition of Done (per increment)

1. Code follows the layering in `01-ARCHITECTURE.md`.
2. Tenant-scoped + subscription-gated where applicable.
3. Tests (incl. tenant isolation) pass in CI.
4. Documented in the relevant `docs/` file if behavior/schema changed.
5. Pushed to a branch with an open PR for review.
