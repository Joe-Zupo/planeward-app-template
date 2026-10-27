import assert from "node:assert/strict";
import { describe, it } from "node:test";

import {
  DEMO_CHANNEL_ID,
  DEMO_INSTALLATION_ID,
  DEMO_PRODUCTION_ERROR,
  demoClaims,
  demoViewerFromToken,
  encodeDemoToken,
  resolveAuthMode,
} from "./demo";

describe("resolveAuthMode", () => {
  it("is planeward unless demo is asked for", () => {
    assert.equal(resolveAuthMode(undefined, undefined), "planeward");
    assert.equal(resolveAuthMode("", "preview"), "planeward");
    assert.equal(resolveAuthMode("planeward", "preview"), "planeward");
    assert.equal(resolveAuthMode("DEMO", "preview"), "planeward");
  });

  it("allows demo locally and on previews", () => {
    assert.equal(resolveAuthMode("demo", undefined), "demo");
    assert.equal(resolveAuthMode("demo", "development"), "demo");
    assert.equal(resolveAuthMode(" demo ", "preview"), "demo");
  });

  it("refuses demo on a production deployment", () => {
    assert.throws(() => resolveAuthMode("demo", "production"), {
      message: DEMO_PRODUCTION_ERROR,
    });
    assert.equal(resolveAuthMode("planeward", "production"), "planeward");
  });
});

describe("demo viewer", () => {
  it("round-trips the role and view through the demo token", () => {
    const viewer = demoViewerFromToken(
      encodeDemoToken(demoClaims("member", "dashboard"))
    );
    assert.equal(viewer.role, "member");
    assert.equal(viewer.view, "dashboard");
    assert.equal(viewer.channelId, null);
  });

  it("ignores every id in the token and keeps the demo ids", () => {
    const forged = encodeDemoToken({
      ...demoClaims("admin", "experience"),
      installationId: "inst_real_customer",
      channelId: "chan_real",
      sub: "cm_real",
    });
    const viewer = demoViewerFromToken(forged);
    assert.equal(viewer.installationId, DEMO_INSTALLATION_ID);
    assert.equal(viewer.channelId, DEMO_CHANNEL_ID);
    assert.equal(viewer.sub, "demo_admin");
  });

  it("falls back to the admin experience viewer for missing or junk tokens", () => {
    for (const token of [null, "", "not.a.jwt", "a.%%%.b"]) {
      const viewer = demoViewerFromToken(token);
      assert.equal(viewer.role, "admin");
      assert.equal(viewer.view, "experience");
      assert.equal(viewer.channelId, DEMO_CHANNEL_ID);
    }
  });

  it("encodes non-ASCII names", () => {
    const token = encodeDemoToken({
      ...demoClaims("member", "experience"),
      name: "Zoë",
    });
    assert.match(token, /^[\w-]+\.[\w-]+\.$/);
  });
});
