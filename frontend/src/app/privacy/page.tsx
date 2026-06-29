import MarketingPage from "@/components/MarketingPage";

export const metadata = { title: "Privacy Policy — HRMS SaaS" };

export default function PrivacyPage() {
  return (
    <MarketingPage title="Privacy Policy" updated="June 2026">
      <p>
        This Privacy Policy explains how HRMS SaaS (&ldquo;we&rdquo;, &ldquo;us&rdquo;) collects, uses, and protects
        information when you use our platform. This page is a template and should be reviewed by your
        legal counsel before production use.
      </p>

      <h2>Information we collect</h2>
      <ul>
        <li>Account data: company details, administrator names, and contact information.</li>
        <li>Employee data your company chooses to store, including profile, attendance, and payroll records.</li>
        <li>Usage and device data needed to operate and secure the service.</li>
      </ul>

      <h2>How we use information</h2>
      <p>
        We use data solely to provide and improve the service: authenticating users, running the
        modules you enable (attendance, leave, payroll, billing), and supporting you. We do not sell
        personal data.
      </p>

      <h2>Data isolation &amp; security</h2>
      <p>
        Each company&apos;s data is logically isolated by a strict multi-tenant model. Sensitive statutory
        identifiers (such as PAN, Aadhaar, and bank account numbers) are encrypted at rest, and access
        is governed by role-based permissions.
      </p>

      <h2>Data retention</h2>
      <p>
        We retain your data for as long as your account is active. If you cancel, access is paused but
        data is preserved for a recovery window before deletion, and you may request an export.
      </p>

      <h2>Your rights</h2>
      <p>
        Depending on your jurisdiction, you may have rights to access, correct, or delete personal
        data. Contact us via the form on our homepage to exercise these rights.
      </p>
    </MarketingPage>
  );
}
