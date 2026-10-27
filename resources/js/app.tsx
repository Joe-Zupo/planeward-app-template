import { StrictMode, type ComponentType } from "react";

import { createRoot } from "react-dom/client";

import { Toaster } from "@/components/ui/sonner";
import { DashboardPage } from "@/pages/dashboard";
import { ExperiencePage } from "@/pages/experience";
import { SetupPage } from "@/pages/setup";
import { PlanewardProvider, PlanewardThemeSync } from "@/planeward/client";

/**
 * Each Laravel page route renders `resources/views/app.blade.php` with a page
 * name and props (see `PageController`). This mounts the matching component.
 */
const pages: Record<string, ComponentType<any>> = {
  setup: SetupPage,
  experience: ExperiencePage,
  dashboard: DashboardPage,
};

const root = document.getElementById("app");

if (root) {
  const Page = pages[root.dataset.page ?? ""];
  const props = JSON.parse(root.dataset.props ?? "{}");

  createRoot(root).render(
    <StrictMode>
      <PlanewardProvider>
        <PlanewardThemeSync />
        {Page ? <Page {...props} /> : null}
        <Toaster position="bottom-center" />
      </PlanewardProvider>
    </StrictMode>
  );
}
