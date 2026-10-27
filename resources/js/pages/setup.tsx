import { CopyField } from "@/components/copy-field";
import { Badge } from "@/components/ui/badge";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

type EnvStatus = { name: string; set: boolean };

const SCOPES = [
  {
    name: "COMMUNITY_MEMBERS_READ",
    use: "Dashboard member list and community.membership.* webhooks",
  },
  {
    name: "COMMUNITY_MESSAGES_WRITE",
    use: "“Announce as the app” on the member view",
  },
];

/** Props come from `PageController::setup`: the request origin and set/unset env vars. */
export function SetupPage({
  origin,
  env,
}: {
  origin: string;
  env: EnvStatus[];
}) {
  const isLocal = /^https?:\/\/(localhost|127\.0\.0\.1)(:|$)/.test(origin);
  const missing = env.filter((entry) => !entry.set).length;

  const urls = [
    { label: "Experience URL", value: `${origin}/experience` },
    { label: "Dashboard URL", value: `${origin}/dashboard` },
    { label: "Webhook URL", value: `${origin}/api/webhooks/planeward` },
  ];

  return (
    <main className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-10 sm:py-16">
      <header className="flex flex-col gap-2">
        <p className="text-sm text-muted-foreground">Planeward Community App</p>
        <h1 className="text-3xl font-semibold tracking-tight">Setup</h1>
        <p className="text-muted-foreground">
          This app runs inside a Planeward community as an iframe. Register the
          URLs below, install the app, then add it to your community as a
          channel.
        </p>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Environment</CardTitle>
          <CardDescription>
            Read from <code>.env</code>. Values are never shown.
            {missing > 0
              ? ` ${missing} missing: copy .env.example to .env and fill it in.`
              : " Everything is set."}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ul className="divide-y">
            {env.map((entry) => (
              <li
                key={entry.name}
                className="flex items-center justify-between gap-4 py-2"
              >
                <code className="text-sm">{entry.name}</code>
                <Badge variant={entry.set ? "secondary" : "destructive"}>
                  {entry.set ? "Set" : "Unset"}
                </Badge>
              </li>
            ))}
          </ul>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>URLs to register</CardTitle>
          <CardDescription>
            In Planeward: Settings › Apps › Developer. Built from this request’s
            host; use your public HTTPS domain in production.
          </CardDescription>
        </CardHeader>
        {isLocal ? (
          <CardContent>
            <p className="rounded-md bg-muted px-3 py-2 text-sm">
              Planeward can’t reach <code>localhost</code>. Start a tunnel to
              port 3005 (
              <code>cloudflared tunnel --url http://localhost:3005</code>) and
              open this page through the tunnel URL to get the URLs to register.
            </p>
          </CardContent>
        ) : null}
        <CardContent className="flex flex-col gap-4">
          {urls.map((url) => (
            <CopyField key={url.label} label={url.label} value={url.value} />
          ))}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Scopes to request</CardTitle>
        </CardHeader>
        <CardContent>
          <ul className="flex flex-col gap-3">
            {SCOPES.map((scope) => (
              <li key={scope.name} className="flex flex-col gap-0.5">
                <code className="text-sm font-medium">{scope.name}</code>
                <span className="text-sm text-muted-foreground">
                  {scope.use}
                </span>
              </li>
            ))}
          </ul>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Steps</CardTitle>
        </CardHeader>
        <CardContent>
          <ol className="flex list-decimal flex-col gap-2 pl-5 text-sm">
            <li>
              Start Postgres with <code>docker compose up -d</code> and run{" "}
              <code>php artisan migrate</code>.
            </li>
            <li>
              Run <code>pnpm dev</code>, start a tunnel to port 3005, and open
              this page through the tunnel.
            </li>
            <li>
              Create the app in Planeward under Settings › Apps › Developer with
              the URLs and scopes above.
            </li>
            <li>
              Copy the app id, app secret and webhook secret into{" "}
              <code>.env</code>, then restart <code>pnpm dev</code>.
            </li>
            <li>
              Install the app in your workspace. The <code>app.installed</code>{" "}
              webhook stores the installation.
            </li>
            <li>
              Add the app to your community as a channel and open it. Staff can
              open the dashboard under Community › Apps.
            </li>
          </ol>
        </CardContent>
      </Card>
    </main>
  );
}
