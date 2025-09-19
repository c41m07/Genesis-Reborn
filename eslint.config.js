import js from "@eslint/js";
import globals from "globals";

const appFiles = ["public/assets/js/**/*.js"];

export default [
  {
    ignores: [
      "node_modules/**",
      "vendor/**",
      "public/assets/svg/**",
      "public/assets/css/**",
      "public/assets/fonts/**",
      "public/assets/images/**",
      "dist/**"
    ]
  },
  {
    files: appFiles,
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: "module",
      globals: {
        ...globals.browser
      }
    },
    rules: {
      ...js.configs.recommended.rules,
      "no-console": ["warn", { allow: ["warn", "error"] }],
      "no-unused-vars": [
        "error",
        {
          argsIgnorePattern: "^_",
          ignoreRestSiblings: true,
          caughtErrors: "none"
        }
      ]
    }
  }
];
