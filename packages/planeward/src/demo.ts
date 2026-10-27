import type { AppView, AppViewerRole } from "@planeward/app-sdk";
import type { AppViewerTokenClaims } from "@planeward/app-sdk/server";

/**
 * Demo auth mode lets the app run outside a Planeward community, for example
 * on a Vercel preview before the app can be installed. Every request acts as
 * a fixed demo viewer instead of a Planeward-signed viewer token.
 *
 * It is a deliberate auth bypass, so it never runs on a production
 * deployment: `resolveAuthMode` throws there instead of falling back quietly.
 */
export type AuthMode = "planeward" | "demo";

export const DEMO_INSTALLATION_ID = "demo_installation";
export const DEMO_WORKSPACE_ID = "demo_workspace";
export const DEMO_CHANNEL_ID = "demo_channel";

export const DEMO_PRODUCTION_ERROR =
  "PLANEWARD_AUTH_MODE=demo is not allowed on a production deployment. Remove it from the Production environment.";

/**
 * `PLANEWARD_AUTH_MODE=demo` turns demo mode on, except on Vercel production
 * (`VERCEL_ENV=production`), where it throws. Anything else is "planeward".
 */
export function resolveAuthMode(
  requested: string | undefined,
  vercelEnv: string | undefined
): AuthMode {
  if (requested?.trim() !== "demo") return "planeward";
  if (vercelEnv === "production") throw new Error(DEMO_PRODUCTION_ERROR);
  return "demo";
}

const isRole = (value: unknown): value is AppViewerRole =>
  value === "member" || value === "admin";

/** The demo viewer. Ids are fixed; only the role and view vary. */
export function demoClaims(
  role: AppViewerRole,
  view: AppView,
  nowSeconds = Math.floor(Date.now() / 1000)
): AppViewerTokenClaims {
  return {
    iss: "demo",
    aud: "demo",
    sub: role === "admin" ? "demo_admin" : "demo_member",
    iat: nowSeconds,
    exp: nowSeconds + 300,
    workspaceId: DEMO_WORKSPACE_ID,
    installationId: DEMO_INSTALLATION_ID,
    channelId: view === "experience" ? DEMO_CHANNEL_ID : null,
    view,
    role,
    name: role === "admin" ? "Demo Admin" : "Demo Member",
    avatar: null,
  };
}

function base64UrlEncode(text: string): string {
  const bytes = new TextEncoder().encode(text);
  let binary = "";
  for (const byte of bytes) binary += String.fromCharCode(byte);
  return btoa(binary)
    .replace(/\+/g, "-")
    .replace(/\//g, "_")
    .replace(/=+$/, "");
}

function base64UrlDecode(text: string): string {
  const base64 = text.replace(/-/g, "+").replace(/_/g, "/");
  const bytes = Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
  return new TextDecoder().decode(bytes);
}

/**
 * An unsigned, JWT-shaped demo token (`alg: none`). The browser sends it like
 * a viewer token, so the UI code is the same in both modes. It proves
 * nothing: the server reads only the role and view from it, and only in demo
 * mode.
 */
export function encodeDemoToken(claims: AppViewerTokenClaims): string {
  const header = base64UrlEncode(JSON.stringify({ alg: "none", typ: "JWT" }));
  return `${header}.${base64UrlEncode(JSON.stringify(claims))}.`;
}

/**
 * The demo viewer for a request's bearer token. Only the role and view are
 * read from the token; the ids are always the demo ids. A missing or
 * unreadable token gets the admin demo viewer on the experience view.
 */
export function demoViewerFromToken(
  token: string | null
): AppViewerTokenClaims {
  let role: AppViewerRole = "admin";
  let view: AppView = "experience";
  try {
    const payload: unknown = JSON.parse(
      base64UrlDecode(token?.split(".")[1] ?? "")
    );
    if (payload && typeof payload === "object") {
      if ("role" in payload && isRole(payload.role)) role = payload.role;
      if ("view" in payload && payload.view === "dashboard") view = "dashboard";
    }
  } catch {
    // Not a demo token; keep the defaults.
  }
  return demoClaims(role, view);
}
