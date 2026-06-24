# 06 — Module System & Domain Modules

## Dynamic Module System

Modules are **database records**, not code constants. A Super Admin can create a
module from the dashboard and attach it to plans — **no deploy required**. Code that
implements a module registers a `code` (e.g. `payroll`); the module's *availability*
to a tenant is purely data (plan → module mapping + subscription status).

```
modules            : code, name, description, is_active, sort_order
features           : module_id, code, type(boolean|limit|quota)
plan_module        : plan_id ↔ module_id
plan_feature       : plan_id ↔ feature_id, value
subscription.overrides : per-tenant grants/removals & add-ons
```

Initial modules: **Attendance, Payroll, Leave, Recruitment, Assets, Performance,
Expenses**. Future: Accounting, ERP, CRM, Mobile Apps, AI Analytics. Each new module
is additive — register code + seed a `modules` row + map to plans.

Every module is **independent**: it functions on its own and integrates with others
only when both are licensed (graceful degradation otherwise).

---

## Attendance Module

**Mode 1 — HR manual attendance:** mark Present / Absent / Half Day / Leave for any
employee or in bulk for a date.

**Mode 2 — Employee self-attendance:** Check In / Check Out, computed working hours,
overtime, personal history.

- One record per `(company, employee, work_date)` (unique). Source tracked
  (`manual|self|gps|qr|biometric`) so future capture methods slot in without schema
  change.
- **Reports:** daily, weekly, monthly, per-employee summary. **Exports:** PDF, Excel, CSV.
- **Future-ready** (interface stubs, gated by features): GPS, QR, biometric, mobile,
  face recognition.

## Leave Module

- **Leave types** (paid/unpaid/sick/...), **policies** (accrual rate, max balance,
  carry-forward cap, encashment rules), **balances** per employee/type/year.
- **Workflow:** Employee → Manager → HR approval (configurable). Each step recorded in
  `leave_request_approvals` with actor + decision + timestamp.
- **Auto-accrual** (scheduled), **carry-forward** at year boundary, **encashment**
  feeding payroll.
- **Calendar** view of team/company leave; integrates with Attendance (approved leave
  marks attendance) and Payroll (LOP deductions).

## Payroll Module

- **Salary structures** per employee composed of **components**: Basic, HRA,
  Allowances, Bonus, Incentives (earnings) and Deductions, Tax, PF, ESI.
- **Payroll run** per `(company, year, month)`: draft → processing → completed →
  locked. Bulk processing via chunked queued jobs; **payslip** per employee with a
  JSON `breakdown` of every component.
- **Attendance integration** (when licensed): auto working days, auto overtime, auto
  leave/LOP deductions. When Attendance is *not* licensed, payroll uses a default
  working-days config.
- **Reports:** payroll summary, salary register, deduction report, tax report.
- **Goal:** a payroll run completes in **fewer than 5 clicks** (select period →
  review → approve → process → publish), with a guided review screen.

## Employee Module (Core HR)

- Profiles, documents (S3/R2, virus-scan hook), departments, designations,
  employment history, salary history.
- **Bulk import** via Excel/CSV with a validation/preview step and row-level error
  reporting (queued for large files).
- **Employee portal**: attendance, leave requests, payslips, profile updates
  (some fields require HR approval).

## Module ↔ Subscription Integration

Each module route group is wrapped in `module:<code>`; granular actions add
`feature:<code>`. Disabling a module (expiry/suspension/downgrade) hides it in the UI
and rejects its APIs with `403 MODULE_NOT_LICENSED`, while **all data is preserved**.
