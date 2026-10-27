"use client";

import { useEffect, useState } from "react";

import type { AppViewerRole } from "@planeward/app-sdk";
import { readDemoRole, switchDemoRole } from "@repo/planeward/client";
import { Button } from "@repo/ui/components/button";

/**
 * Shown on every page in demo mode, so nobody mistakes a preview for the
 * signed-in app. Lets you switch between the admin and member demo viewers.
 */
export function DemoBanner() {
  const [role, setRole] = useState<AppViewerRole | null>(null);

  useEffect(() => {
    setRole(readDemoRole());
  }, []);

  const other: AppViewerRole = role === "member" ? "admin" : "member";

  return (
    <div
      role="status"
      className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-b border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm"
    >
      <span>
        <strong>Demo mode.</strong> Planeward sign-in is off; you are viewing as
        the demo {role ?? "…"}. Turn it off before registering the app.
      </span>
      {role ? (
        <Button
          size="xs"
          variant="outline"
          onClick={() => switchDemoRole(other)}
        >
          View as {other}
        </Button>
      ) : null}
    </div>
  );
}
