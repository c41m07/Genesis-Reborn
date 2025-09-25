const js = require('@eslint/js');
const globals = require('globals');
const importPlugin = require('eslint-plugin-import');

module.exports = [
  {
    ignores: [
      'node_modules/**',
      'vendor/**',
      'public/assets/svg/**',
      'public/assets/css/**',
      'public/assets/fonts/**',
      'public/assets/images/**',
      'dist/**',
    ],
  },
  {
    files: ['public/assets/js/**/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
    plugins: {
      import: importPlugin,
    },
    rules: {
      ...js.configs.recommended.rules,
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'no-unused-vars': [
        'error',
        {
          argsIgnorePattern: '^_',
          ignoreRestSiblings: true,
          caughtErrors: 'none',
        },
      ],
      'import/no-extraneous-dependencies': ['error', { devDependencies: true }],
    },
  },
];
