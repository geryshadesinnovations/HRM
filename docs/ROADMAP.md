# 12 — Roadmap & Future Expansion

This platform is built in **reviewable increments**. Each phase ends with a green CI
build and a PR. Earlier phases unblock later ones.

## Build Sequencing

### Phase 0 — Foundation
- [x] Architecture blueprint (`docs/`)
- [x] Laravel 12 backend scaffold + folder structure (service/repository layers)
- [x] Docker / compose / Nginx for local dev
- [x] Base API conventions: response envelope, error codes, auth scaffolding

### Phase 1 — Platform Spine
- [x] Multi-tenant core (`BelongsToTenant` scope, tenant middleware, base model)
- [x] Identity & Auth (JWT + refresh, users) — sessions/login history/MFA still pending
- [x] RBAC (roles, permissions, middleware)
- [x] Subscription Engine + Feature Access Service + module/feature/plan seeders
- [x] Tenant-isolation test suite in CI

### Phase 2 — Billing
- [x] Gateway abstraction + manual adapter + GatewayManager
- [x] Razorpay adapter (orders + signature-verified webhooks). Cashfree/PayU next
- [x] Invoices, payments, webhooks (idempotent), tax/GST — PDF generation still pending
- [x] Subscription lifecycle job (sweep). Auto-renew/dunning still pending

### Phase 3 — Core HR + first revenue modules
- [x] Employee/Department/Designation (seat-gated). Bulk import still pending
- [x] Attendance (manual + self check-in/out, worked/overtime). Exports still pending
- [x] Leave (types, balances, request → approve/reject, attendance integration). Calendar endpoint done

### Phase 4 — Payroll
- [x] Salary components/structures, payroll runs, payslips
- [x] Attendance/leave integration (LOP proration). Statutory (PF/ESI/tax) + reports still pending

### Phase 5 — Frontend
- [x] Next.js scaffold + design system (Tailwind) + API client + JWT auth
- [x] Login/registration, dashboard, and screens for Employees, Attendance, Leave, Payroll, Billing (role + entitlement gated)
- [ ] Command palette, global search, onboarding wizard

### Phase 6 — Reporting, Notifications, Hardening
- [x] Report service (attendance/leave/payroll/employees) + CSV export. Scheduled delivery + PDF still pending
- [x] In-app notifications (per-user) wired into leave/payroll/billing/lifecycle. Email best-effort; SMS pending
- [ ] Observability (Prometheus/Grafana), load testing, security review, MFA, audit logs

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


### Phase 7 — SaaS Owner Portal & Public Website
- [x] Super Admin platform analytics dashboard (companies, MRR/ARR, subscriptions, module usage, renewals, health)
- [x] Company management: list/search/filter, suspend/activate, soft-delete, reset password, secure impersonation
- [x] Plan & pricing management (CRUD, duplicate, enable/disable) — drives public pricing
- [x] Public marketing website (hero, features, dynamic pricing, FAQ, contact form)
- [x] Contact inquiries inbox (public submit + admin manage)
- [x] Company registration profile fields (phone, GST, industry, headcount, address)
- [x] Light/Dark theme switcher
- [ ] Deep employee records + document vault (req #5)
- [ ] Attendance breaks, correction workflow, GPS/biometric (req #6)
- [ ] Payroll two-mode + period lock + adjustment audit (req #7)
- [ ] Command palette, global search, coupons/discounts/taxes, full doc regeneration
