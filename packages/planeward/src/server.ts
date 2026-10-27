import { eq, sql } from "drizzle-orm";

import {
  createPlanewardClient,
  PlanewardApiError,
  type PlanewardClient,
} from "@planeward/app-sdk/server";
import { db, installations, webhookDeliveries, type Database } from "@repo/db";

import { demoViewerFromToken, resolveAuthMode, type AuthMode } from "./demo";
import { env } from "./env";
import {
  authenticateViewer,
  jsonError,
  readBearerToken,
  unauthorized,
  type RequireViewerOptions,
  type RequireViewerResult,
  type Viewer,
} from "./viewer";
import {
  processPlanewardWebhook,
  type HandlePlanewardWebhookOptions,
  type WebhookStore,
} from "./webhook";

export type { PlanewardClient } from "@planeward/app-sdk/server";
export type { AuthMode } from "./demo";
export type {
  RequireViewerOptions,
  RequireViewerResult,
  Viewer,
} from "./viewer";
export type {
  HandlePlanewardWebhookOptions,
  InstallationRecord,
  WebhookStore,
} from "./webhook";

/**
 * Verify `Authorization: Bearer <viewer token>` against this deployment's
 * `PLANEWARD_APP_ID`. Returns the verified viewer, or a ready-made JSON
 * `Response` (401 missing/invalid token or uninstalled installation, 403 when
 * `role: "admin"` is required).
 */
export async function requireViewer(
  request: Request,
  options: RequireViewerOptions = {}
): Promise<RequireViewerResult> {
  if (authMode() === "demo") {
    // No Planeward token to verify: act as the demo viewer the page asked for.
    const viewer = demoViewerFromToken(readBearerToken(request));
    if (options.role === "admin" && viewer.role !== "admin") {
      return { ok: false, response: jsonError(403, "Staff only") };
    }
    return { ok: true, viewer };
  }

  const result = await authenticateViewer(request, options, {
    appId: env.PLANEWARD_APP_ID,
    baseUrl: env.PLANEWARD_BASE_URL,
  });
  if (!result.ok) {
    return result;
  }
  // Viewer tokens live 5 minutes; a recorded `app.uninstalled` cuts them off
  // right away. Installs we never heard about (no webhook yet) stay allowed.
  const [installation] = await db
    .select({ uninstalledAt: installations.uninstalledAt })
    .from(installations)
    .where(eq(installations.id, result.viewer.installationId))
    .limit(1);
  if (installation?.uninstalledAt) {
    return {
      ok: false,
      response: unauthorized("This app was uninstalled from the community."),
    };
  }
  return result;
}

/**
 * Planeward API client acting on the viewer's installation. The installation
 * id comes from the verified token only, never from request input.
 */
export function planewardForViewer(viewer: Viewer): PlanewardClient {
  if (authMode() === "demo") {
    // There is no real installation to call the API for.
    const unavailable = () =>
      Promise.reject(
        new PlanewardApiError(
          503,
          "The Planeward API is not available in demo mode. Install the app and set PLANEWARD_AUTH_MODE=planeward."
        )
      );
    return {
      community: {
        members: { list: unavailable, get: unavailable },
        messages: { create: unavailable },
      },
    };
  }
  return createPlanewardClient({
    secret: env.PLANEWARD_APP_SECRET,
    installationId: viewer.installationId,
    baseUrl: env.PLANEWARD_BASE_URL,
  });
}

/**
 * "planeward" verifies viewer tokens. "demo" skips Planeward sign-in, for
 * previews before the app can be installed. Throws on a production deployment
 * set to demo, so the bypass can never go live by accident.
 */
export function authMode(): AuthMode {
  return resolveAuthMode(env.PLANEWARD_AUTH_MODE, env.VERCEL_ENV);
}

/** Drizzle-backed {@link WebhookStore}. */
function createDrizzleWebhookStore(database: Database): WebhookStore {
  return {
    async claimDelivery(delivery) {
      const inserted = await database
        .insert(webhookDeliveries)
        .values(delivery)
        .onConflictDoNothing()
        .returning({ deliveryId: webhookDeliveries.deliveryId });
      return inserted.length > 0;
    },
    async releaseDelivery(deliveryId) {
      await database
        .delete(webhookDeliveries)
        .where(eq(webhookDeliveries.deliveryId, deliveryId));
    },
    async updateInstallation(installationId, apply) {
      await database.transaction(async (tx) => {
        // Serializes concurrent deliveries for one installation, including
        // the first ones, when there is no row yet to lock FOR UPDATE.
        await tx.execute(
          sql`select pg_advisory_xact_lock(hashtextextended(${installationId}, 0))`
        );
        const [existing] = await tx
          .select()
          .from(installations)
          .where(eq(installations.id, installationId))
          .limit(1);
        const { id, ...next } = apply(existing ?? null);
        await tx
          .insert(installations)
          .values({ id, ...next })
          .onConflictDoUpdate({ target: installations.id, set: next });
      });
    },
  };
}

/**
 * Route handler body for `POST /api/webhooks/planeward`:
 * - 401 bad signature, 400 missing `x-planeward-delivery` or bad payload;
 * - 200 `{ duplicate: true }` for a delivery already handled;
 * - persists `app.installed` / `app.uninstalled`, then calls `onEvent`; 204;
 * - 500 if either throws (the delivery is released so the retry runs again).
 */
export function handlePlanewardWebhook(
  request: Request,
  options: HandlePlanewardWebhookOptions = {}
): Promise<Response> {
  return processPlanewardWebhook(request, options, {
    secret: env.PLANEWARD_WEBHOOK_SECRET,
    store: createDrizzleWebhookStore(db),
  });
}
