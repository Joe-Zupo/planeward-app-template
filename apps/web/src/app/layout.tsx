import type { Metadata } from "next";

import { PlanewardProvider, PlanewardThemeSync } from "@repo/planeward/client";
import { authMode } from "@repo/planeward/server";
import { Toaster } from "@repo/ui/components/sonner";

import { DemoBanner } from "./_components/demo-banner";

import "@repo/ui/globals.css";

export const metadata: Metadata = {
  title: {
    default: "Planeward Community App",
    template: "%s · Planeward Community App",
  },
  description:
    "A starter app that runs inside a Planeward community: member view, staff dashboard, API and webhooks.",
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  // Throws on a production deployment set to demo, so it never renders there.
  const mode = authMode();
  return (
    // PlanewardThemeSync sets data-theme on <html> from the host after hydration.
    <html lang="en" suppressHydrationWarning>
      <body className="bg-background text-foreground min-h-dvh antialiased">
        <PlanewardProvider authMode={mode}>
          {mode === "demo" ? <DemoBanner /> : null}
          <PlanewardThemeSync />
          {children}
          <Toaster position="bottom-center" />
        </PlanewardProvider>
      </body>
    </html>
  );
}
