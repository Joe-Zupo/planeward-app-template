# Planeward App Template

A starter for building Planeward Community Apps: third-party Next.js apps that Planeward shows in an iframe inside a community. Your app appears as a **member channel** (`/experience`) and a **staff dashboard** (`/dashboard`), and gets:

- a verified viewer identity (short-lived signed token from the host),
- a typed API client for the Planeward API,
- signed webhooks (`app.installed`, `app.uninstalled`, and more).

## Layout

| Path                                                             | Package              | What it is                                                                                |
| ---------------------------------------------------------------- | -------------------- | ----------------------------------------------------------------------------------------- |
| `apps/web`                                                       | `@repo/web`          | Next.js app on port 3005: setup page, experience, dashboard, API routes                   |
| `packages/app-sdk`                                               | `@planeward/app-sdk` | Public SDK: iframe handshake, token verification, webhooks, API client (builds to `dist`) |
| `packages/planeward`                                             | `@repo/planeward`    | React provider/hooks and server helpers (`requireViewer`, `handlePlanewardWebhook`)       |
| `packages/db`                                                    | `@repo/db`           | Drizzle schema, client and migrations                                                     |
| `packages/ui`                                                    | `@repo/ui`           | shadcn/ui components and the Tailwind v4 theme                                            |
| `packages/typescript-config`, `eslint-config`, `prettier-config` | `@repo/*-config`     | Shared configs                                                                            |

## Quickstart

Requires Node 24+, pnpm 11 and Docker.

```bash
pnpm install
cp .env.example .env
docker compose up -d   # Postgres on :5433
pnpm db:migrate
pnpm dev               # app on http://localhost:3005
```

Open http://localhost:3005 for the setup page. It lists the URLs to register and which env vars are set.

## Register in Planeward

1. In **Settings › Apps › Developer**, create an app with:
   - Experience URL: `http://localhost:3005/experience`
   - Dashboard URL: `http://localhost:3005/dashboard`
   - Webhook URL: `http://localhost:3005/api/webhooks/planeward`
   - Both scopes: **Community — read members** (`COMMUNITY_MEMBERS_READ`) and **Community — post messages as the app** (`COMMUNITY_MESSAGES_WRITE`).
2. Copy the app ID, app secret and webhook secret into `.env` (`PLANEWARD_APP_ID`, `PLANEWARD_APP_SECRET`, `PLANEWARD_WEBHOOK_SECRET`), and set `PLANEWARD_BASE_URL` to your Planeward deployment. Restart `pnpm dev`.
3. Install it from the install link (or **Install into this workspace**).
4. In **Community › Apps**, add it as a channel.

## How it works

1. **Handshake.** The page loads in the iframe and `PlanewardProvider` performs a `postMessage` handshake with the host. The host returns the context, theme and a short-lived **viewer token** (a JWT signed by Planeward with `aud` = your app ID).
2. **Viewer token.** `useViewerFetch()` sends that token as `Authorization: Bearer <token>` on every call to your own API.
3. **`requireViewer`.** Every API route starts with `requireViewer(request)`, which verifies the token against `PLANEWARD_BASE_URL` and `PLANEWARD_APP_ID` and returns the claims (or a 401/403 response).
4. **Scoping.** `installationId` and `channelId` come **only** from the verified claims. All data (for example the Board `posts`) is read and written by those ids, so one community can never see another's data. `planewardForViewer(viewer)` builds the API client for the claims' installation.
5. **Webhooks.** `/api/webhooks/planeward` uses `handlePlanewardWebhook`: it verifies the signature on the raw body, dedupes on `x-planeward-delivery` (stored in `webhook_deliveries`), records installs and uninstalls in `installations`, then calls your handler.

## Security notes

- **One app ID per deployment.** Tokens are checked against `PLANEWARD_APP_ID` (`aud`). A token for another app fails with `PlanewardTokenAudienceError`: `Token is for app "<aud>", but this backend expects "<appId>". Check PLANEWARD_APP_ID.`
- **Never trust ids from request bodies.** Use `viewer.installationId` / `viewer.channelId`, not values the client sends.
- **Don't rely on cookies in the iframe.** Third-party cookies are blocked in most browsers. Authenticate each request with the viewer token.
- **Leave the app frameable.** Don't set `X-Frame-Options`, and leave `frame-ancestors` open: communities can run on custom domains.
- **Keep the app secret server-only.** `PLANEWARD_APP_SECRET` and `PLANEWARD_WEBHOOK_SECRET` must never reach client code.
- **Webhooks only process after signature verification.** Unsigned or tampered deliveries get a 401 and nothing is stored.
- **Production webhook URLs must be public https.** `localhost` works only for development.

## Customizing

- Replace the Board example (`apps/web` experience, `/api/posts`, and the `posts` table) with your feature. Keep `requireViewer` and claims-derived ids in every route.
- Add tables in `packages/db/src/schema.ts`, then run `pnpm db:generate` and `pnpm db:migrate`.

## Scripts

| Command                                | What it does                          |
| -------------------------------------- | ------------------------------------- |
| `pnpm dev`                             | Run everything in dev mode            |
| `pnpm build`                           | Build all packages and the app        |
| `pnpm typecheck` / `lint` / `test`     | Checks across the workspace           |
| `pnpm format:check` / `format:write`   | Prettier                              |
| `pnpm db:generate` / `pnpm db:migrate` | Generate and apply Drizzle migrations |

## Releasing the SDK

`@planeward/app-sdk` is published to npm from this repo by `.github/workflows/app-sdk-release.yml` on `app-sdk-v*.*.*` tags. See [`packages/app-sdk/README.md`](packages/app-sdk/README.md) for the steps.
