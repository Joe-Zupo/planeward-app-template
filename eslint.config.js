import js from "@eslint/js";
import prettierConfig from "eslint-config-prettier";
import react from "eslint-plugin-react";
import reactHooks from "eslint-plugin-react-hooks";
import globals from "globals";
import tseslint from "typescript-eslint";

export default tseslint.config(
  {
    ignores: [
      "node_modules/**",
      "vendor/**",
      "public/**",
      "bootstrap/ssr/**",
      "storage/**",
    ],
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ["**/*.{ts,tsx}"],
    rules: {
      "@typescript-eslint/no-unused-vars": [
        "warn",
        {
          args: "none",
          varsIgnorePattern: "^React$|^_",
          caughtErrorsIgnorePattern: "^_|^err|^error",
          ignoreRestSiblings: true,
        },
      ],
      "@typescript-eslint/no-explicit-any": "off",
      "@typescript-eslint/no-empty-object-type": "off",
    },
  },
  {
    files: ["resources/js/**/*.tsx"],
    ...react.configs.flat.recommended,
    ...react.configs.flat["jsx-runtime"],
    languageOptions: {
      ...react.configs.flat.recommended.languageOptions,
      globals: { ...globals.browser },
    },
    // "detect" calls context.getFilename, which ESLint 10 removed.
    settings: { react: { version: "19.3" } },
  },
  reactHooks.configs.flat.recommended,
  {
    rules: {
      "react/prop-types": "off",
      "react-hooks/exhaustive-deps": "off",
      "react-hooks/set-state-in-effect": "off",
    },
  },
  prettierConfig
);
