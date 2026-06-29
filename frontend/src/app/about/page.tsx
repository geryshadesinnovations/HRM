import MarketingPage from "@/components/MarketingPage";

export const metadata = { title: "About — HRMS SaaS" };

export default function AboutPage() {
  return (
    <MarketingPage title="About us">
      <p>
        HRMS SaaS is a modern, modular human-resource platform built for growing companies. We bring
        employee records, attendance, leave, and payroll together in one place — so HR teams can stop
        juggling spreadsheets and disconnected tools.
      </p>

      <h2>Our mission</h2>
      <p>
        To make great HR operations accessible to every company, not just the enterprises that can
        afford heavyweight systems. We believe software should be quick to adopt, transparent to
        price, and respectful of your data.
      </p>

      <h2>What we believe</h2>
      <ul>
        <li><strong>Simplicity wins.</strong> Onboarding should take minutes, not weeks.</li>
        <li><strong>You own your data.</strong> Strict tenant isolation and a clear export path — never locked in.</li>
        <li><strong>Pay for what you use.</strong> Modular plans that scale with your team.</li>
        <li><strong>Built for compliance.</strong> GST invoicing and statutory-ready payroll out of the box.</li>
      </ul>

      <h2>Who it&apos;s for</h2>
      <p>
        From a five-person startup running its first payroll to a multi-department organisation with
        thousands of employees across locations — the platform grows with you, one module at a time.
      </p>
    </MarketingPage>
  );
}
