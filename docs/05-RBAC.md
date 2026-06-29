# 05 — Role-Based Access Control

Permissions are **database-driven**. Roles are bundles of permissions; companies may
create **custom roles** from permission groups and role templates. Every API endpoint
validates a permission (and tenancy).

## Built-in Roles

| Role | Scope | Summary |
|------|-------|---------|
| **Super Admin** | Platform (no tenant) | Everything platform-side; can act within a tenant for support (audited). |
| **Company Admin** | Tenant | Full control of their company: settings, subscription, billing, all modules. |
| **HR Manager** | Tenant | Manage employees, attendance, leave, run payroll; no billing/subscription control. |
| **Manager** | Tenant, team | Approve leave, view team attendance/profiles; no payroll, no company settings. |
| **Employee** | Tenant, self | Self-attendance, leave requests, own payslips, own profile. |

## Permission Model

- Permission codes: `domain.resource.action`, e.g. `payroll.run.execute`,
  `employee.profile.update`, `leave.request.approve`, `subscription.manage`.
- **Permission groups** bundle related permissions for easy assignment
  (e.g. "Attendance Management", "Payroll Management").
- Roles ↔ permissions and users ↔ roles via pivot tables (Spatie-style schema).
- A user's effective permissions = union of all their roles' permissions, intersected
  with the company's **licensed modules** (you cannot exercise a payroll permission if
  payroll isn't subscribed).

## RBAC Matrix (representative)

| Capability (permission) | Super Admin | Company Admin | HR Manager | Manager | Employee |
|---|:---:|:---:|:---:|:---:|:---:|
| Manage companies/plans/modules | ✅ | — | — | — | — |
| View platform revenue (MRR/ARR) | ✅ | — | — | — | — |
| Suspend/extend any subscription | ✅ | — | — | — | — |
| Manage own subscription/billing | ✅* | ✅ | — | — | — |
| Company settings | ✅* | ✅ | — | — | — |
| Manage roles & permissions | ✅* | ✅ | — | — | — |
| Create/edit employees | ✅* | ✅ | ✅ | — | — |
| Bulk import employees | ✅* | ✅ | ✅ | — | — |
| Mark attendance (others) | ✅* | ✅ | ✅ | team | — |
| Self check-in/out | — | ✅ | ✅ | ✅ | ✅ |
| Approve/reject leave | ✅* | ✅ | ✅ | team | — |
| Request leave | — | ✅ | ✅ | ✅ | ✅ |
| Configure salary structures | ✅* | ✅ | ✅ | — | — |
| Run payroll | ✅* | ✅ | ✅ | — | — |
| View any payslip | ✅* | ✅ | ✅ | — | — |
| View own payslip | — | ✅ | ✅ | ✅ | ✅ |
| View audit logs | ✅ | ✅ (own tenant) | — | — | — |

`✅* = Super Admin acting within a tenant for support; every such action is audited.`
"team" = limited to the manager's reporting employees.

## Enforcement

```php
// Route-level
Route::middleware(['auth:api','tenant','permission:payroll.run.execute','module:payroll'])
     ->post('/payroll/runs', [PayrollRunController::class,'store']);

// Policy-level (object ownership / team scope)
$this->authorize('approve', $leaveRequest); // LeaveRequestPolicy checks manager-of
```

- **Middleware**: `permission:*` (RBAC) + `module:*` / `feature:*` (subscription).
- **Policies**: object-level checks (ownership, team membership, same tenant).
- Deny-by-default: missing permission ⇒ `403 FORBIDDEN`.


---

## Added permissions (Phase 7 enhancements)

| Permission | Granted to (built-in roles) | Purpose |
|------------|-----------------------------|---------|
| `employee.document.view` | Company Admin, HR Manager, Manager | View/download an employee's vault documents |
| `employee.document.manage` | Company Admin, HR Manager | Upload / delete vault documents |
| `attendance.correction.request` | Company Admin, HR Manager, Manager, Employee | Raise an attendance correction request |
| `attendance.correction.approve` | Company Admin, HR Manager, Manager | Approve / reject correction requests |
| `payroll.run.reopen` | Company Admin, HR Manager | Reopen a locked payroll run (unlocks the attendance period) |

`employee.import` (already defined) now backs the bulk CSV import endpoint. All
checks remain database-driven on the `api` guard — no hardcoded role logic.



---

## Added permission (Phase 7 — devices)

| Permission | Granted to (built-in roles) | Purpose |
|------------|-----------------------------|---------|
| `attendance.device.manage` | Company Admin, HR Manager | Register / revoke biometric (kiosk) attendance devices |
