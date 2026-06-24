# 03 — Subscription Engine

The subscription system is the **heart of the platform**. Every module, feature,
limit, and seat is validated through one centralized engine. **No hardcoded plan
logic anywhere** — entitlements are computed from database records.

## Concepts

| Concept | Meaning |
|---------|---------|
| **Module** | A sellable capability (attendance, payroll, leave). Dynamic, admin-creatable. |
| **Feature** | A granular flag/limit/quota inside a module (`payroll.bulk`, `attendance.gps`, `employees.max`). |
| **Plan** | A priced bundle: base price + included seats + per-seat price + a set of modules + feature values. |
| **Subscription** | A company's live instance of a plan with status, period, seats, and per-tenant `overrides`. |
| **Entitlement** | The *resolved* set of modules + feature values for a company at request time. |

## Entitlement Resolution

```
Entitlement(company) =
    Plan.modules ∪ Plan.features          (base bundle)
    ⊕ Subscription.overrides              (add-ons / custom grants / removals)
    ⊗ SubscriptionStatus gate             (active/trial/grace ⇒ enabled; else disabled)
```

Resolution is computed once per request and cached in Redis (keyed by
`company_id` + subscription `updated_at`), so checks are O(1) lookups. Cache is
invalidated whenever the subscription, plan, or overrides change.

## Feature Access Service (the single gate)

```php
interface FeatureAccess
{
    public function canAccessModule(string $moduleCode): bool;
    public function canUseFeature(string $featureCode): bool;     // boolean features
    public function featureLimit(string $featureCode): ?int;      // null = unlimited
    public function canAddEmployee(int $count = 1): bool;         // seat check
    public function canRunPayroll(): bool;                        // module + status
    public function assert(string $ability, mixed $context = null): void; // throws 403
}
```

- **`canAddEmployee`** compares current active employee count vs
  `included_seats + purchased extra seats` (or `employees.max` feature). Over-limit
  attempts return `403 SEAT_LIMIT_REACHED` with an upgrade CTA.
- **`canRunPayroll`** requires the `payroll` module AND a non-blocked status.
- All checks short-circuit to **deny** when the subscription is `expired`,
  `suspended`, or `cancelled`.

Enforcement points:
1. **Middleware** `module:payroll` / `feature:payroll.bulk` on route groups.
2. **Service layer** `featureAccess->assert(...)` before any mutating action.
3. **Frontend** mirrors entitlements (received via `/me/entitlements`) to hide/disable
   UI — but the backend is always authoritative.

## Subscription Lifecycle (state machine)

```
            trialEnds / activate(payment)
   ┌───────┐ ─────────────────────────────▶ ┌────────┐
   │ trial │                                  │ active │
   └───┬───┘ ◀──────── reactivate ──────────  └───┬────┘
       │ trial expires (no payment)               │ period ends + payment fails
       ▼                                           ▼
   ┌─────────┐                               ┌───────────┐
   │ expired │ ◀──── grace expires ───────── │  grace    │
   └────┬────┘                               └─────┬─────┘
        │ reactivate (pay)                         │ payment succeeds
        └──────────────▶ active ◀──────────────────┘

   active/grace ── admin/customer cancel ─▶ cancelled ── reactivate ─▶ active
   any state ──── super-admin suspend ────▶ suspended  ── unsuspend ─▶ prior
```

| Status | Module access | Data | Billing |
|--------|---------------|------|---------|
| trial | full (per plan) | live | none until convert |
| active | full | live | invoiced each period |
| grace | full (configurable) | live | dunning/retries |
| expired | **disabled** | **preserved** | renewal required |
| suspended | **disabled** | **preserved** | manual reactivation |
| cancelled | **disabled** | **preserved** | reactivation creates new period |

**Data is never deleted on expiry/cancellation.** Reactivation restores access to
all preserved data.

## Seat & Module Billing

- **Base plan** includes N seats (e.g. Starter 25, Growth 100).
- **Additional seats** billed at `per_seat_price` (configurable per plan, e.g. ₹20/₹30).
- **Module-based billing**: modules can be priced individually as add-ons recorded in
  `subscription.overrides` and reflected on invoices.
- **Add-ons**: one-off or recurring extras (extra storage, premium support).
- All amounts flow into the Billing Engine ([04-BILLING.md](./04-BILLING.md)) for
  proration on upgrade/downgrade, tax, and invoice generation.

## Upgrade / Downgrade

- **Upgrade**: immediate entitlement change; proration charged for remaining period.
- **Downgrade**: scheduled for next period end (so paid period isn't lost); guarded so
  current usage (e.g. employee count) doesn't exceed the new plan's limits without an
  explicit confirmation/cleanup step.

## Scheduled Jobs

- `subscriptions:sweep` (daily/hourly): transition trial→expired, active→grace→expired
  based on dates; emit `SubscriptionExpiringSoon` (T-7/T-3/T-1) and `SubscriptionExpired`.
- `subscriptions:renew`: attempt auto-renewal charges, with retry/backoff on failure.
