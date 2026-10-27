# AGENTS.md

Conventions for AI agents working in this repo, a Planeward Community App template built with Laravel and React.

## Layout

- `app/Planeward`: viewer token verification (`ViewerTokenVerifier`, `Viewer`), the API client (`PlanewardClient`, built with `Planeward::forViewer()`), webhooks (`Webhooks/WebhookProcessor`, `WebhookStore`, `InstallationRules`, `WebhookReceived`).
- `app/Http/Middleware/RequireViewer.php`: the `viewer` route middleware.
- `app/Http/Controllers`, `app/Http/Resources`, `app/Models`, `database/migrations`, `routes/{web,api}.php`: standard Laravel.
- `resources/js`: the React app. `pages/` are mounted by `app.tsx`, `components/ui/` holds shadcn/ui, `planeward/` holds the handshake store, `PlanewardProvider` and `useViewerFetch`.
- `resources/views/app.blade.php`: the single Blade shell.

`@planeward/app-sdk` comes from npm and is only used in the browser. Its server half is ported to PHP in `app/Planeward`. Keep `app/Planeward/Protocol.php` in step with the SDK's `protocol.ts`.

## Commands

```bash
pnpm dev                 # php artisan serve --port=3005 + vite build --watch
pnpm build
php artisan test         # PHPUnit
pnpm test                # Vitest
pnpm typecheck
pnpm lint
vendor/bin/pint          # PHP formatting (--test to check)
pnpm format:check        # or format:write
php artisan migrate
```

After editing files, format the changed paths: `vendor/bin/pint <paths>` for PHP, `pnpm exec prettier --write <paths>` for everything else.

## Rules

- **Every API route uses the `viewer` middleware** (`viewer:admin` for staff-only routes). The webhook route is the only exception. Read the viewer with `Viewer::from($request)`.
- **Scope data by claims only.** `installationId`, `channelId` and the author (`sub`) come from the verified viewer, never from the request body, query or headers. The one deliberate exception is an admin choosing a _target_ channel (`/api/announce`). The installation still comes from the claims, and Planeward enforces which channels the app may post to.
- Use `Planeward::forViewer($viewer)` for Planeward API calls. Never build a `PlanewardClient` from a client-supplied installation id.
- Webhooks go through `WebhookProcessor`. Do not read a payload before signature verification. Add behavior with a `WebhookReceived` listener, not by editing the processor.
- Secrets (`PLANEWARD_APP_SECRET`, `PLANEWARD_WEBHOOK_SECRET`) are server-only. Read settings through `config('planeward.*')`, never `env()` outside `config/`. Never pass them to a view or a `VITE_` variable.
- Do not add `X-Frame-Options`, sessions, cookies, CSRF or Sanctum. The app runs in a cross-origin iframe, and `bootstrap/app.php` removes the session and cookie middleware on purpose.
- API errors are `ApiError::make($status, $message)`, shaped `{ "error": "..." }`. Validate with `Validator::make` and return a 400 with the first message. Don't use `$request->validate()`, which returns Laravel's 422 shape.
- API JSON is camelCase through `app/Http/Resources`. Keep `resources/js/lib/types.ts` in step with the resources.
- **Schema changes:** add a new migration with `php artisan make:migration`. Never edit a migration that has already shipped.
