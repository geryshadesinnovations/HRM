import MarketingPage from "@/components/MarketingPage";

export const metadata = { title: "Terms & Conditions — HRMS SaaS" };

export default function TermsPage() {
  return (
    <MarketingPage title="Terms & Conditions" updated="June 2026">
      <p>
        These Terms govern your use of the HRMS SaaS platform. This page is a template and should be
        reviewed by your legal counsel before production use.
      </p>

      <h2>1. Accounts</h2>
      <p>
        You are responsible for maintaining the confidentiality of your login credentials and for all
        activity under your account. Notify us immediately of any unauthorised use.
      </p>

      <h2>2. Subscriptions &amp; billing</h2>
      <ul>
        <li>Plans begin with a free trial; paid subscriptions renew automatically unless cancelled.</li>
        <li>Fees are billed per the chosen plan, including any extra seats and add-on modules.</li>
        <li>Failed payments enter a dunning process; access may be suspended after repeated failures.</li>
        <li>Coupons and discounts are applied at checkout subject to their individual terms.</li>
      </ul>

      <h2>3. Acceptable use</h2>
      <p>
        You agree not to misuse the service, attempt to access other tenants&apos; data, or use the
        platform for unlawful purposes.
      </p>

      <h2>4. Data &amp; privacy</h2>
      <p>
        Your use of the service is also governed by our <a href="/privacy">Privacy Policy</a>. You
        retain ownership of the data you submit.
      </p>

      <h2>5. Service availability</h2>
      <p>
        We strive for high availability but the service is provided &ldquo;as is&rdquo;. Enterprise plans may
        include a separate service-level agreement.
      </p>

      <h2>6. Termination</h2>
      <p>
        You may cancel at any time. On termination, access is paused and data is retained for a
        recovery window before deletion.
      </p>
    </MarketingPage>
  );
}
