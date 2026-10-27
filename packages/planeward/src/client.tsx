"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useSyncExternalStore,
  type ReactNode,
} from "react";

import {
  createPlanewardApp,
  type AppContext,
  type AppTheme,
  type AppViewerRole,
  type PlanewardApp,
} from "@planeward/app-sdk";

import {
  DEMO_CHANNEL_ID,
  demoClaims,
  encodeDemoToken,
  type AuthMode,
} from "./demo";
import {
  assertSameOrigin,
  createPlanewardStore,
  INITIAL_PLANEWARD_STATE,
  withViewerToken,
  type PlanewardStatus,
  type PlanewardStore,
} from "./store";

export type { PlanewardStatus } from "./store";
export type { AuthMode } from "./demo";

const DEMO_ROLE_KEY = "planeward-demo-role";

/** The role the demo viewer acts as in this browser. Defaults to admin. */
export function readDemoRole(): AppViewerRole {
  try {
    return window.localStorage.getItem(DEMO_ROLE_KEY) === "member"
      ? "member"
      : "admin";
  } catch {
    return "admin";
  }
}

/** Switch the demo viewer's role, then reload so every view picks it up. */
export function switchDemoRole(role: AppViewerRole): void {
  try {
    window.localStorage.setItem(DEMO_ROLE_KEY, role);
  } catch {
    // Storage blocked; the reload keeps the default role.
  }
  window.location.reload();
}

/**
 * Stands in for the Planeward host in demo mode: ready at once, light theme,
 * and an unsigned demo token for the current role and page.
 */
function createDemoApp(): PlanewardApp {
  const view = window.location.pathname.startsWith("/dashboard")
    ? "dashboard"
    : "experience";
  const context: AppContext = {
    view,
    channelId: view === "experience" ? DEMO_CHANNEL_ID : null,
    theme: "light",
  };
  return {
    ready: Promise.resolve(context),
    context,
    getToken: async () => encodeDemoToken(demoClaims(readDemoRole(), view)),
    onThemeChange: () => () => undefined,
    destroy: () => undefined,
  };
}

// One handshake per page lifetime: module scope survives StrictMode's double
// mount and Provider remounts. Created lazily so the server never builds one.
let browserStore: PlanewardStore | null = null;

function getBrowserStore(authMode: AuthMode): PlanewardStore {
  browserStore ??= createPlanewardStore(
    authMode === "demo"
      ? { isFramed: () => true, createApp: createDemoApp }
      : {
          isFramed: () => window.parent !== window,
          createApp: () => createPlanewardApp(),
        }
  );
  return browserStore;
}

const getServerSnapshot = () => INITIAL_PLANEWARD_STATE;

const PlanewardStoreContext = createContext<PlanewardStore | null>(null);

/**
 * `authMode` comes from the server (`authMode()` in `@repo/planeward/server`).
 * In "demo" the page runs without Planeward, as a demo viewer.
 */
export function PlanewardProvider({
  children,
  authMode = "planeward",
}: {
  children: ReactNode;
  authMode?: AuthMode;
}) {
  const store =
    typeof window === "undefined" ? null : getBrowserStore(authMode);
  return (
    <PlanewardStoreContext.Provider value={store}>
      {children}
    </PlanewardStoreContext.Provider>
  );
}

const noopSubscribe = () => () => undefined;
const rejectOnServer = () =>
  Promise.reject(new Error("Viewer tokens are only available in the browser."));

export function usePlaneward(): {
  status: PlanewardStatus;
  error: string | null;
  context: AppContext | null;
  theme: AppTheme;
  getToken: () => Promise<string>;
} {
  const store = useContext(PlanewardStoreContext);
  const state = useSyncExternalStore(
    store?.subscribe ?? noopSubscribe,
    store?.getSnapshot ?? getServerSnapshot,
    getServerSnapshot
  );
  return {
    status: state.status,
    error: state.error,
    context: state.context,
    theme: state.theme,
    getToken: store?.getToken ?? rejectOnServer,
  };
}

/**
 * `fetch` for this app's own API with `Authorization: Bearer <viewer token>`.
 * Other headers in `init` are kept. Cross-origin URLs are refused so the
 * token never leaves the app's backend.
 */
export function useViewerFetch(): (
  input: string,
  init?: RequestInit
) => Promise<Response> {
  const { getToken } = usePlaneward();
  return useCallback(
    async (input: string, init?: RequestInit) => {
      assertSameOrigin(input, window.location.origin);
      const token = await getToken();
      return fetch(input, withViewerToken(init, token));
    },
    [getToken]
  );
}

/** Mirrors the host theme onto `<html data-theme>` and `color-scheme`. */
export function PlanewardThemeSync(): null {
  const { theme } = usePlaneward();
  useEffect(() => {
    const root = document.documentElement;
    root.dataset.theme = theme;
    root.style.colorScheme = theme;
  }, [theme]);
  return null;
}
