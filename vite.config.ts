/// <reference types="vitest/config" />

import { fileURLToPath } from "node:url";

import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";

export default defineConfig({
  plugins: [
    laravel({
      input: ["resources/css/app.css", "resources/js/app.tsx"],
      refresh: true,
    }),
    react(),
    tailwindcss(),
  ],
  resolve: {
    alias: { "@": fileURLToPath(new URL("./resources/js", import.meta.url)) },
  },
  server: {
    watch: {
      ignored: ["**/storage/framework/views/**"],
    },
  },
  test: {
    include: ["resources/js/**/*.test.ts"],
    environment: "node",
  },
});
