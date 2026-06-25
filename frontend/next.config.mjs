/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  // The UI is a pure client-side SPA talking to the Laravel API; we don't want
  // a missing API at build time to fail the production build.
  eslint: { ignoreDuringBuilds: true },
};

export default nextConfig;
