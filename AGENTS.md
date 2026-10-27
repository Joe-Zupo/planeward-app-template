# AGENTS.md

Conventions for AI agents working in this repo (a Planeward Community App template).

## Layout

- `apps/web`: the Next.js 16 app (port 3005).
- `packages/app-sdk`: `@planeward/app-sdk`, the public SDK. Builds to `dist` with tsdown.
- `packages/planeward`: `@repo/planeward` (`/client`, `/server`, `/env`).
- `packages/db`: `@repo/db` (Drizzle schema, client, migrations).
- `packages/ui`: `@repo/ui` (shadcn/ui + Tailwind v4).
- `packages/{typescript,eslint,prettier}-config`: shared configs.

Internal packages are named `@repo/*` and are JIT TypeScript: they export `src/*.ts` directly and are listed in the app's `transpilePackages`. Only `@planeward/app-sdk` has a build step; turbo `dev`, `build`, `typecheck`, `lint` and `test` depend on `^build`. Workspace deps use `workspace:*`; shared versions live in the `catalog:` in `pnpm-workspace.yaml`.

## Commands

```bash
pnpm dev                 # all dev servers
pnpm build               # build everything
pnpm typecheck
pnpm lint
pnpm test
pnpm format:check        # or format:write
pnpm db:generate         # after schema changes
pnpm db:migrate
pnpm --filter <pkg> <script>
```

After editing files, run Prettier on the changed paths: `pnpm exec prettier --write <paths>`.

## Rules

- **Every API route calls `requireViewer(request)`** from `@repo/planeward/server` first (`{ role: "admin" }` for staff-only routes). Return its `response` when `ok` is false.
- **Demo mode** (`PLANEWARD_AUTH_MODE="demo"`) makes `requireViewer` return a fixed demo viewer without checking a token. It lives in `packages/planeward/src/demo.ts` and is refused on Vercel production. Never add another way to skip `requireViewer`, and never let demo mode read installation or channel ids from the request.
- **Scope data by claims only.** `installationId`, `channelId` and the author (`sub`) come from the verified viewer, never from the request body, query or headers. The one deliberate exception is an admin choosing a _target_ channel (`/api/announce`): the installation still comes from the claims, and Planeward enforces which channels the app may post to.
- Use `planewardForViewer(viewer)` for Planeward API calls; never build a client from a client-supplied installation id.
- Webhooks go through `handlePlanewardWebhook`; do not process a payload before signature verification.
- Secrets (`PLANEWARD_APP_SECRET`, `PLANEWARD_WEBHOOK_SECRET`) are server-only. Read env through `@repo/planeward/env`, not `process.env` in app code (exception: `apps/web/src/lib/env-status.ts`, which only reports set/unset for the setup page).
- Do not add `X-Frame-Options` or rely on cookies: the app runs in a cross-origin iframe.
- **Schema changes:** edit `packages/db/src/schema.ts`, then `pnpm db:generate` to create a migration. Never hand-edit generated migrations.

## Next.js

This is a recent Next.js version whose APIs may differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` before writing Next code.

## Turborepo

Turbo config and CLI behavior vary by version. Read `docs/README.md` in the installed `turbo` package (`node -p "require.resolve('turbo/package.json')"`) and the relevant pages before changing `turbo.json`.
