import { existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import type { NextConfig } from "next";

// Env lives in the repo root `.env` (one file for the app, drizzle-kit and
// turbo). Next only reads `apps/web/.env*`, so load the root file here.
// Variables already in the environment (turbo, your host) win.
const rootEnv = fileURLToPath(new URL("../../.env", import.meta.url));
if (existsSync(rootEnv)) {
  process.loadEnvFile(rootEnv);
}

const nextConfig: NextConfig = {
  transpilePackages: ["@repo/ui", "@repo/planeward", "@repo/db"],
  // No `headers()` here on purpose: this app runs in an iframe on any
  // Planeward community host, including custom domains. Do not add
  // `X-Frame-Options` or a restrictive CSP `frame-ancestors`.
};

export default nextConfig;
