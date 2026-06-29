# 10 — Frontend & UX

Next.js (App Router) + TypeScript + Tailwind + shadcn/ui. Premium, fast, uncluttered.
Reference quality: Keka / Zoho People / BambooHR / Stripe Dashboard / Linear / Notion —
inspiration only, no cloning.

## App Structure

```
frontend/src/app/
├── (auth)/         login, signup (company), forgot-password, mfa
├── (super-admin)/  platform dashboard, companies, plans, modules, billing, audit
├── (company)/      company admin: dashboard, employees, attendance, leave, payroll,
│                   departments, settings, subscription, billing
└── (employee)/     self-service: dashboard, my attendance, my leave, payslips, profile
```

- Route groups map to the three personas; layout + nav differ per group.
- Middleware redirects by role and blocks access to module routes the tenant isn't
  licensed for (entitlements fetched from `/me/entitlements`).

## State Management

- **React Query** for all server state: typed hooks per resource, caching, optimistic
  updates, background refetch, retry. The single API client injects the JWT, handles
  401→refresh→retry, and parses the standard error envelope.
- **Zustand** for client state: auth/session, current tenant, entitlements snapshot,
  command-palette + UI state. Persisted slices in memory; tokens in secure storage.

## Design System

- `components/ui/*` shadcn primitives (button, dialog, table, form, toast, sheet…).
- Tokens for color/spacing/typography; light + dark; fully responsive (mobile-friendly
  per requirement). Accessible (focus states, keyboard nav, ARIA).
- Data tables: server-side pagination/sort/filter, column visibility, export buttons.

## Signature UX

- **Command palette** (⌘K): jump to any page, run quick actions (add employee, run
  payroll, request leave), global search across employees/pages.
- **Quick actions** on dashboards; **global search** in the top bar.
- **Onboarding** (HubSpot-style): post-signup checklist (add employees → configure
  payroll → invite team) to get value within 10 minutes.
- **Empty states** that teach; **skeleton loaders**; toasts for every mutation.
- **Entitlement-aware UI:** unlicensed modules show an upgrade CTA, not a dead link.

## The Three Experiences

- **Super Admin:** MRR/ARR, active companies, revenue trends, expiring subscriptions,
  failed payments; manage companies/plans/modules/subscriptions/payments/invoices/audit;
  suspend / extend / upgrade / downgrade / refund.
- **Company Admin:** employee count, attendance/payroll/leave summaries, subscription
  status; manage employees/attendance/payroll/departments/leave; company, subscription,
  and billing settings.
- **Employee:** attendance check-in/out + history, leave requests + balances, payslips,
  profile updates.

## Performance

- Server Components for static/heavy data; Client Components only where interactive.
- Code-split per route group; prefetch on hover; image optimization; bundle budgets.



---

## Command palette, search & marketing site (Phase 7)

- **Command palette** (`components/CommandPalette.tsx`, mounted in `Shell`) opens
  with ⌘K / Ctrl+K or the header Search button. It blends local navigation with
  remote tenant-scoped results from `GET /search?q=` (employees, departments),
  with full keyboard navigation (↑/↓/↵/Esc).
- **Marketing site** — the landing page adds a Why-us section, a plan comparison
  table, and testimonials. New static pages `/about`, `/privacy`, `/terms` share
  `components/MarketingPage.tsx` chrome and are linked from the footer.
- **Documentation** — a self-contained HTML manual set lives at
  `docs/site/index.html` and is served by the app at `/docs/index.html` (copied to
  `frontend/public/docs/`). It includes the test credentials.
