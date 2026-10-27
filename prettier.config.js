/** @type {import('prettier').Config} */
export default {
  useTabs: false,
  semi: true,
  singleQuote: false,
  tabWidth: 2,
  trailingComma: "es5",
  endOfLine: "auto",
  importOrder: [
    "^(react/(.*)$)|^(react$)",
    "",
    "<THIRD_PARTY_MODULES>",
    "",
    "^@planeward/(.*)$",
    "",
    "^@/(.*)$",
    "",
    "^[./]",
  ],
  importOrderParserPlugins: ["typescript", "jsx", "decorators-legacy"],
  plugins: [
    "@ianvs/prettier-plugin-sort-imports",
    "prettier-plugin-tailwindcss",
  ],
  tailwindStylesheet: "./resources/css/app.css",
};
