# 07 — API Specification

REST over HTTPS, JSON. Versioned under `/api/v1`. JWT access tokens (short-lived) +
refresh tokens (rotating). All tenant routes resolve the company from the token.

## Conventions

- **Auth header:** `Authorization: Bearer <access_token>`.
- **Pagination:** `?page=&per_page=` → envelope with `data` + `meta` (cursor option
  for large/streamed lists).
- **Filtering/sorting:** `?filter[status]=active&sort=-created_at`.
- **Idempotency:** mutating POSTs accept `Idempotency-Key` header (webhooks, payments).
- **Validation:** `422` with field errors. **Rate limits:** per-IP and per-user.

### Success envelope
```json
{ "data": { "...": "..." }, "meta": { "page": 1, "per_page": 20, "total": 134 } }
```
### Error envelope
```json
{ "error": { "code": "SEAT_LIMIT_REACHED",
             "message": "Your plan allows 25 employees.",
             "details": { "limit": 25, "current": 25 } } }
```
Canonical error codes include: `UNAUTHENTICATED`, `FORBIDDEN`, `VALIDATION_FAILED`,
`MODULE_NOT_LICENSED`, `FEATURE_NOT_AVAILABLE`, `SEAT_LIMIT_REACHED`,
`SUBSCRIPTION_INACTIVE`, `TENANT_MISMATCH`, `RATE_LIMITED`.

## Endpoint Catalog (v1, abridged)

### Auth & Identity
```
POST   /auth/register-company        # signup → creates company + admin + trial sub
POST   /auth/login                   # → access + refresh
POST   /auth/refresh                 # rotate tokens
POST   /auth/logout
GET    /me                           # profile + roles
GET    /me/entitlements              # resolved modules + feature values (UI gating)
POST   /me/mfa/enable | /verify
```

### Subscription & Billing (tenant)
```
GET    /subscription                 # current status, plan, seats, period
GET    /plans                        # public plans
POST   /subscription/upgrade
POST   /subscription/downgrade
POST   /subscription/cancel
POST   /subscription/reactivate
GET    /invoices                     # list / download PDF
GET    /invoices/{uuid}
POST   /billing/checkout             # create gateway order for an open invoice
```

### Webhooks (public, signature-verified)
```
POST   /webhooks/razorpay
POST   /webhooks/cashfree
POST   /webhooks/payu
```

### Employees (module: core HR)
```
GET    /employees            POST /employees            # canAddEmployee gate
GET    /employees/{uuid}     PATCH /employees/{uuid}     DELETE (soft)
POST   /employees/import     # Excel/CSV, async, returns job id
GET    /departments  POST /departments   ...  /designations ...
```

### Attendance (module: attendance)
```
POST   /attendance/check-in           # self
POST   /attendance/check-out          # self
POST   /attendance/mark               # HR manual (single/bulk)
GET    /attendance                    # filter by employee/date range
GET    /attendance/reports/{daily|weekly|monthly|summary}?export=pdf|excel|csv
```

### Leave (module: leave)
```
GET    /leave/types   POST /leave/types
GET    /leave/balances?employee=
POST   /leave/requests                # employee
POST   /leave/requests/{uuid}/approve # manager/HR (policy-checked)
POST   /leave/requests/{uuid}/reject
GET    /leave/calendar
```

### Payroll (module: payroll)
```
GET    /payroll/components  POST /payroll/components
GET    /payroll/structures/{employee}  PUT /payroll/structures/{employee}
POST   /payroll/runs                   # create run for period (canRunPayroll)
POST   /payroll/runs/{id}/process      # async bulk
POST   /payroll/runs/{id}/publish
GET    /payroll/runs/{id}/payslips     GET /payslips/{uuid}/pdf
GET    /payroll/reports/{summary|register|deductions|tax}?export=...
```

### Super Admin (platform, no tenant)
```
GET    /admin/metrics                 # MRR, ARR, active companies, churn
GET    /admin/companies   POST /admin/companies/{id}/suspend | /extend
GET    /admin/plans       POST /admin/plans      # CRUD
GET    /admin/modules     POST /admin/modules    # dynamic modules
GET    /admin/subscriptions  /admin/payments  /admin/invoices  /admin/audit-logs
```

Full OpenAPI 3.1 spec will live at `backend/openapi.yaml` and can be referenced from
steering/specs via `#[[file:backend/openapi.yaml]]`.


---

## Endpoints added (Phase 7 enhancements)

All require `auth:api` + `tenant`; permissions noted per route.

### Employees — deep records & bulk import

| Method | Path | Permission | Notes |
|--------|------|------------|-------|
| `POST` | `/employees/import` | `employee.import` | Multipart CSV upload. Returns `{summary:{total,created,failed}, rows:[...]}`. `207` if any row failed, `201` otherwise. |
| `GET` | `/employees/import/template` | `employee.import` | Downloads a CSV template with the recognised columns. |

Create/update employee endpoints now accept the deep-record fields (personal,
contact, employment, salary/statutory). Sensitive identifiers are masked on read.

### Employee document vault

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/employees/{employee}/documents` | `employee.document.view` |
| `POST` | `/employees/{employee}/documents` | `employee.document.manage` (multipart: `type`, `title?`, `file` ≤10 MB) |
| `GET` | `/employee-documents/{document}/download` | `employee.document.view` |
| `DELETE` | `/employee-documents/{document}` | `employee.document.manage` |

### Attendance — breaks & corrections

| Method | Path | Permission |
|--------|------|------------|
| `POST` | `/attendance/break-in` | `attendance.self` |
| `POST` | `/attendance/break-out` | `attendance.self` |
| `GET` | `/attendance/corrections` | `attendance.view` |
| `POST` | `/attendance/corrections` | `attendance.correction.request` |
| `POST` | `/attendance/corrections/{correction}/approve` | `attendance.correction.approve` |
| `POST` | `/attendance/corrections/{correction}/reject` | `attendance.correction.approve` |

### Payroll — mode, reopen & adjustments

| Method | Path | Permission |
|--------|------|------------|
| `POST` | `/payroll/runs` (now accepts `mode`) | `payroll.run.execute` |
| `POST` | `/payroll/runs/{run}/reopen` | `payroll.run.reopen` |
| `GET` | `/payroll/runs/{run}/adjustments` | `payroll.run.execute` |
| `POST` | `/payroll/runs/{run}/adjustments` | `payroll.run.execute` |
