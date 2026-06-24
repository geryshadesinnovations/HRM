# 08 — Security Architecture

Defense in depth across tenancy, identity, transport, application, and data.

## Tenant Isolation (most critical)

- `BelongsToTenant` global scope auto-filters every query by `company_id` and
  auto-fills it on insert. Developers cannot "forget" the filter.
- `tenant` middleware sets the active tenant from the JWT; mismatches between the
  token's company and a path/body `company_id` return `403 TENANT_MISMATCH`.
- Super Admin runs tenant-less; acting *into* a tenant is explicit and audited.
- **Test guard:** an automated suite seeds two tenants and asserts that no model,
  endpoint, or report can read/write across the boundary. Runs in CI.

## Authentication

- JWT **access** tokens (short TTL, ~15 min) + rotating **refresh** tokens (longer
  TTL, revocable, stored hashed). Refresh rotation detects token reuse → revoke chain.
- **MFA-ready:** TOTP secret per user (`mfa_secrets`), enforced per-role or per-company
  policy; recovery codes hashed.
- Passwords hashed with bcrypt/argon2id. Login throttling + lockout after N failures.
- **Login history & session tracking**: device, IP, user-agent, last-seen; users and
  admins can review/revoke sessions.

## Transport & Headers

- HTTPS only (HSTS). Security headers: CSP, `X-Content-Type-Options`,
  `X-Frame-Options`/frame-ancestors, `Referrer-Policy`.
- CORS locked to known frontend origins.

## Application

- **Rate limiting** per route group (auth endpoints stricter), per-IP + per-user.
- **CSRF** for any cookie-based flows; the SPA uses bearer tokens (CSRF-exempt) but
  refresh cookies (if used) are `SameSite=strict`, `HttpOnly`, `Secure`.
- **XSS**: output encoding on the frontend; never render unsanitized HTML.
- **SQL injection**: parameterized queries only (Eloquent/PDO bindings).
- **Mass assignment**: explicit `$fillable`; FormRequests validate every input.
- **Authorization**: deny-by-default; permission middleware + policies on every route.

## Files

- Uploads validated by MIME + extension + size; stored in S3/R2 with random keys,
  never executed; served via signed, expiring URLs. Virus-scan hook before finalizing.

## Auditing & Compliance

- `audit_logs` (partitioned) capture actor, tenant, action, target, before/after diff,
  IP, and request id for every sensitive mutation (billing, RBAC, payroll, employee
  data, super-admin tenant actions).
- Data-retention policy drives archival/detachment of old partitions; no automatic
  destruction of customer business data.
- Secrets via environment/secret manager; never committed. Gateway webhook signatures
  verified on every call.

## Secure SDLC

- Dependency scanning + SAST in CI; pinned versions; least-privilege DB/storage creds;
  separate keys per environment.
