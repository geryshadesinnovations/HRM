# 09 — Reporting & Notifications

## Reporting Engine

A reusable report abstraction shared by all modules:

```php
interface Report {
    public function query(ReportFilters $f): Builder; // tenant-scoped
    public function columns(): array;
    public function transform(iterable $rows): iterable;
}
```

- **Reports:** Attendance (daily/weekly/monthly/summary), Payroll (summary, salary
  register, deductions, tax), Employee, plus platform-side Revenue & Subscription
  reports for Super Admin.
- **Filters** (date range, department, status, employee) are declarative and validated.
- **Exporters:** PDF (print-styled), Excel (`.xlsx`), CSV. Large exports run as queued
  jobs that produce a file in S3/R2 and notify the user with a signed download link.
- **Scheduled delivery:** a report + filter + schedule (cron) + recipients record;
  the scheduler dispatches generation and emails the result.
- Heavy reports read from indexed/partitioned tables and may use read replicas at scale.

## Notification System

Event-driven, multi-channel, per-user/company preferences.

```
Domain event ─▶ NotificationDispatcher
                  ├─ resolve recipients + preferences + entitlements
                  └─ per channel → queued send job
Channels: in-app (persisted), email, SMS, WhatsApp (future)
```

- **Channels:** In-App (stored in `notifications`, partitioned; bell + read state),
  Email, SMS; **WhatsApp** is future-ready behind the same channel interface.
- **Events:** subscription expiry / expiring-soon / renewed, payment success / failure,
  payroll generated, leave approved / rejected, attendance missing, employee invited.
- **Templates** are data-driven and localizable; each channel renders the same
  notification payload differently.
- **Preferences:** users opt in/out per event×channel; transactional/critical
  (security, billing) are non-optional.
- **Reliability:** queued with retry/backoff; failures recorded; idempotent on
  `(event, recipient, channel)`.
