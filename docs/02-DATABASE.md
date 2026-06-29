# 02 — Database Schema & ERD

PostgreSQL 16. Conventions for **every** table:

- Surrogate `id BIGSERIAL` PK; public-facing entities also carry a `uuid` (indexed,
  unique) so internal ids are never exposed in URLs.
- Audit columns: `created_at`, `updated_at`, `deleted_at` (soft delete),
  `created_by`, `updated_by`.
- Tenant-owned tables carry `company_id BIGINT NOT NULL` with a FK to `companies`
  and a composite index leading with `company_id`.
- Money stored as `BIGINT` minor units (paise/cents) + a `currency` char(3); never
  floats. Rates/percentages stored as `NUMERIC`.
- Enums modeled as Postgres native enums or constrained `varchar` with check
  constraints, mirrored by PHP enums.

## Entity Groups & ERD

```
PLATFORM (no company_id)
  companies ─1─┬─< subscriptions >─1─ plans ─<─ plan_module >─ modules
               │                       │
               │                       └─<─ plan_feature >─ features
               ├─< invoices ─< invoice_lines
               ├─< payments
               └─< audit_logs

IDENTITY (company_id, except super admins)
  companies ─1─< users >─< model_has_roles >─ roles ─< role_has_permissions >─ permissions
                 │
                 ├─< sessions
                 ├─< login_histories
                 └─1 mfa_secrets

CORE HR (company_id)
  companies ─1─< departments ─< designations
            ─1─< employees ─┬─< employee_documents
                            ├─1 employment_histories
                            └─1 user (optional self-service link)

ATTENDANCE (company_id)
  employees ─1─< attendance_records ;  companies ─1─< shifts
  attendance_records >─ shifts (optional)

LEAVE (company_id)
  companies ─1─< leave_types ─< leave_policies
  employees ─1─< leave_balances (per type/year)
  employees ─1─< leave_requests ─< leave_request_approvals

PAYROLL (company_id)
  companies ─1─< salary_components
  employees ─1─< salary_structures ─< salary_structure_lines
  companies ─1─< payroll_runs ─< payslips ─< payslip_lines
```

## Core Table Definitions (abridged DDL)

### Platform

```sql
CREATE TABLE companies (
    id            BIGSERIAL PRIMARY KEY,
    uuid          UUID NOT NULL UNIQUE,
    name          VARCHAR(180) NOT NULL,
    slug          VARCHAR(120) NOT NULL UNIQUE,
    status        VARCHAR(20)  NOT NULL DEFAULT 'active', -- active|suspended|cancelled
    timezone      VARCHAR(64)  NOT NULL DEFAULT 'Asia/Kolkata',
    currency      CHAR(3)      NOT NULL DEFAULT 'INR',
    country       CHAR(2)      NOT NULL DEFAULT 'IN',
    gstin         VARCHAR(20),
    settings      JSONB        NOT NULL DEFAULT '{}',
    created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ, deleted_at TIMESTAMPTZ
);

CREATE TABLE modules (        -- dynamic; admin-creatable
    id BIGSERIAL PRIMARY KEY,
    code        VARCHAR(60) NOT NULL UNIQUE,   -- attendance|payroll|leave...
    name        VARCHAR(120) NOT NULL,
    description TEXT,
    is_active   BOOLEAN NOT NULL DEFAULT true,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ
);

CREATE TABLE features (       -- granular flags/limits, attached to modules
    id BIGSERIAL PRIMARY KEY,
    module_id BIGINT REFERENCES modules(id),
    code      VARCHAR(80) NOT NULL UNIQUE,     -- e.g. attendance.gps, payroll.bulk
    name      VARCHAR(140) NOT NULL,
    type      VARCHAR(20) NOT NULL,            -- boolean|limit|quota
    created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ
);

CREATE TABLE plans (
    id BIGSERIAL PRIMARY KEY,
    code           VARCHAR(60) NOT NULL UNIQUE,
    name           VARCHAR(120) NOT NULL,
    billing_cycle  VARCHAR(20) NOT NULL,        -- monthly|yearly
    base_price     BIGINT NOT NULL,             -- minor units
    currency       CHAR(3) NOT NULL DEFAULT 'INR',
    included_seats INT NOT NULL DEFAULT 0,
    per_seat_price BIGINT NOT NULL DEFAULT 0,   -- additional seat price
    trial_days     INT NOT NULL DEFAULT 0,
    is_public      BOOLEAN NOT NULL DEFAULT true,
    is_active      BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ, deleted_at TIMESTAMPTZ
);

CREATE TABLE plan_module (    -- which modules a plan grants
    plan_id BIGINT REFERENCES plans(id),
    module_id BIGINT REFERENCES modules(id),
    PRIMARY KEY (plan_id, module_id)
);

CREATE TABLE plan_feature (   -- feature values per plan (limits/flags)
    plan_id    BIGINT REFERENCES plans(id),
    feature_id BIGINT REFERENCES features(id),
    value      VARCHAR(80) NOT NULL,            -- 'true' | '100' | 'unlimited'
    PRIMARY KEY (plan_id, feature_id)
);

CREATE TABLE subscriptions (
    id BIGSERIAL PRIMARY KEY,
    uuid        UUID NOT NULL UNIQUE,
    company_id  BIGINT NOT NULL REFERENCES companies(id),
    plan_id     BIGINT NOT NULL REFERENCES plans(id),
    status      VARCHAR(20) NOT NULL,           -- trial|active|grace|expired|suspended|cancelled
    seats       INT NOT NULL DEFAULT 0,         -- purchased seats
    trial_ends_at      TIMESTAMPTZ,
    current_period_start TIMESTAMPTZ,
    current_period_end   TIMESTAMPTZ,
    grace_ends_at        TIMESTAMPTZ,
    cancelled_at         TIMESTAMPTZ,
    auto_renew  BOOLEAN NOT NULL DEFAULT true,
    overrides   JSONB NOT NULL DEFAULT '{}',    -- per-tenant feature/module overrides & add-ons
    created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ, deleted_at TIMESTAMPTZ
);
CREATE INDEX idx_subscriptions_company ON subscriptions(company_id);
CREATE INDEX idx_subscriptions_status_period ON subscriptions(status, current_period_end);
```

### Billing

```sql
CREATE TABLE invoices (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL UNIQUE,
    company_id BIGINT NOT NULL REFERENCES companies(id),
    subscription_id BIGINT REFERENCES subscriptions(id),
    number      VARCHAR(40) NOT NULL UNIQUE,    -- INV-2026-000123
    status      VARCHAR(20) NOT NULL,           -- draft|open|paid|void|uncollectible
    subtotal    BIGINT NOT NULL,
    tax_total   BIGINT NOT NULL DEFAULT 0,
    total       BIGINT NOT NULL,
    currency    CHAR(3) NOT NULL DEFAULT 'INR',
    gstin       VARCHAR(20),
    issued_at   TIMESTAMPTZ, due_at TIMESTAMPTZ, paid_at TIMESTAMPTZ,
    meta        JSONB NOT NULL DEFAULT '{}',
    created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ
);
CREATE TABLE invoice_lines (
    id BIGSERIAL PRIMARY KEY,
    invoice_id BIGINT NOT NULL REFERENCES invoices(id),
    description VARCHAR(200) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_amount BIGINT NOT NULL,
    tax_rate NUMERIC(5,2) NOT NULL DEFAULT 0,   -- e.g. 18.00 for GST
    line_total BIGINT NOT NULL
);
CREATE TABLE payments (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL UNIQUE,
    company_id BIGINT NOT NULL REFERENCES companies(id),
    invoice_id BIGINT REFERENCES invoices(id),
    gateway    VARCHAR(30) NOT NULL,            -- razorpay|cashfree|payu|stripe
    gateway_payment_id VARCHAR(120),
    status     VARCHAR(20) NOT NULL,            -- created|authorized|captured|failed|refunded
    amount     BIGINT NOT NULL,
    currency   CHAR(3) NOT NULL DEFAULT 'INR',
    raw_payload JSONB,
    created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ
);
CREATE TABLE webhook_events (   -- idempotency + audit for gateway callbacks
    id BIGSERIAL PRIMARY KEY,
    gateway VARCHAR(30) NOT NULL,
    event_id VARCHAR(160) NOT NULL,
    type VARCHAR(80) NOT NULL,
    payload JSONB NOT NULL,
    processed_at TIMESTAMPTZ,
    UNIQUE (gateway, event_id)
);
```

### Core HR / Attendance / Leave / Payroll (key tables)

```sql
CREATE TABLE employees (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL UNIQUE,
    company_id BIGINT NOT NULL REFERENCES companies(id),
    user_id    BIGINT REFERENCES users(id),       -- self-service login (optional)
    employee_code VARCHAR(40) NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name  VARCHAR(80),
    email      VARCHAR(160),
    phone      VARCHAR(20),
    department_id  BIGINT REFERENCES departments(id),
    designation_id BIGINT REFERENCES designations(id),
    manager_id     BIGINT REFERENCES employees(id),
    date_of_joining DATE,
    status     VARCHAR(20) NOT NULL DEFAULT 'active', -- active|on_leave|terminated
    created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ, deleted_at TIMESTAMPTZ,
    UNIQUE (company_id, employee_code)
);

CREATE TABLE attendance_records (
    id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES companies(id),
    employee_id BIGINT NOT NULL REFERENCES employees(id),
    work_date  DATE NOT NULL,
    status     VARCHAR(15) NOT NULL,             -- present|absent|half_day|leave|holiday
    check_in   TIMESTAMPTZ, check_out TIMESTAMPTZ,
    worked_minutes INT, overtime_minutes INT NOT NULL DEFAULT 0,
    source     VARCHAR(20) NOT NULL DEFAULT 'manual', -- manual|self|gps|qr|biometric
    created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ,
    UNIQUE (company_id, employee_id, work_date)
) PARTITION BY RANGE (work_date);             -- monthly partitions

CREATE TABLE leave_requests (
    id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES companies(id),
    employee_id BIGINT NOT NULL REFERENCES employees(id),
    leave_type_id BIGINT NOT NULL REFERENCES leave_types(id),
    start_date DATE NOT NULL, end_date DATE NOT NULL,
    days NUMERIC(4,1) NOT NULL,
    status VARCHAR(15) NOT NULL DEFAULT 'pending', -- pending|approved|rejected|cancelled
    reason TEXT,
    created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ
);

CREATE TABLE payroll_runs (
    id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES companies(id),
    period_month INT NOT NULL, period_year INT NOT NULL,
    status VARCHAR(15) NOT NULL DEFAULT 'draft', -- draft|processing|completed|locked
    total_gross BIGINT, total_deductions BIGINT, total_net BIGINT,
    processed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ,
    UNIQUE (company_id, period_year, period_month)
);
CREATE TABLE payslips (
    id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES companies(id),
    payroll_run_id BIGINT NOT NULL REFERENCES payroll_runs(id),
    employee_id BIGINT NOT NULL REFERENCES employees(id),
    gross BIGINT NOT NULL, deductions BIGINT NOT NULL, net BIGINT NOT NULL,
    worked_days NUMERIC(4,1), lop_days NUMERIC(4,1) DEFAULT 0,
    breakdown JSONB NOT NULL DEFAULT '{}',
    created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ
);
```

## Indexing Strategy

- Lead every tenant index with `company_id` (it is in nearly every WHERE clause).
- Composite indexes matched to access patterns, e.g.
  `attendance_records (company_id, employee_id, work_date)`,
  `leave_requests (company_id, status, start_date)`,
  `subscriptions (status, current_period_end)` for expiry sweeps.
- Partial indexes for hot, selective predicates (e.g. `WHERE status='pending'`).
- GIN indexes on `JSONB` columns only where queried.

## Partitioning Strategy

High-volume, append-mostly tables are **range-partitioned by date**:
`attendance_records` (monthly), `audit_logs` (monthly), `notifications` (monthly).
Old partitions can be detached/archived to cold storage per data-retention policy.
This keeps indexes small and supports fast pruning.

## Migration Strategy

- One migration per table; never edit a shipped migration — add a new one.
- Backwards-compatible deploys: add columns nullable → backfill via job → enforce
  not-null in a later migration (expand/contract).
- Seeders provide canonical modules, features, default plans, permissions, and a
  demo tenant for local/dev.
- Reference data (modules/features/permissions) is idempotently re-seedable.


---

## Enhancement schema (employee records, documents, attendance, payroll)

These additive migrations (`2026_06_29_0001xx_*`) extend the schema without
breaking existing data. All new business tables carry `company_id` and are
tenant-scoped.

### `employees` (expanded — deep records)

New nullable columns grouped by concern:

- **Personal:** `profile_photo_path`, `gender`, `date_of_birth`, `blood_group`,
  `marital_status`, `nationality`.
- **Contact:** `emergency_contact_name`, `emergency_contact_phone`,
  `current_address`, `permanent_address`.
- **Employment:** `employment_type` (`full_time|part_time|contract|intern`),
  `work_location`, `shift_id` → `shifts`, `confirmation_date`, `date_of_exit`.
- **Salary / statutory:** `bank_account_name`, `bank_account_number`*,
  `bank_ifsc`, `pan`*, `aadhaar`*, `uan`, `pf_number`, `esi_number`.

\* Stored in `text` columns and **encrypted at rest** via Eloquent `encrypted`
casts. API responses mask all but the last 4 characters.

### `employee_documents` (new — document vault)

Versioned, tenant- and employee-scoped document metadata. Binary content lives
on a private disk; only the path is stored. Columns: `uuid`, `company_id`,
`employee_id`, `type`, `title`, `original_name`, `disk`, `path`, `mime`,
`size`, `version`, `uploaded_by`, soft deletes. Re-uploading the same `type`
inserts a new row with `version = max(version)+1`, preserving history.

### `attendance_records` (expanded)

Added: `break_in`, `break_out`, `break_minutes`, `late_minutes`,
`early_minutes`, `locked` (set true when a payroll period is finalized), `notes`.
Locked rows reject edits until the period is reopened.

### `attendance_correction_requests` (new)

Audit trail for the correction workflow: `uuid`, `company_id`, `employee_id`,
`work_date`, `requested_check_in`, `requested_check_out`, `requested_status`,
`reason`, `status` (`pending|approved|rejected`), `requested_by`,
`reviewed_by`, `reviewed_at`, `review_note`.

### `payroll_runs` (expanded) + `payroll_adjustments` (new)

`payroll_runs` gains `mode` (`payroll_only|attendance_payroll`), `locked_at`,
`reopened_at`, `reopened_by`. `payroll_adjustments` is an immutable audit line
per run: `type` (`bonus|incentive|penalty|other|lock|reopen|recalculate`),
`label`, `amount` (minor units, may be negative), `note`, optional
`employee_id`, `created_by`.
