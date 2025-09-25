module.exports = {
  root: true,
  env: {
    browser: true,
    es2022: true,
    node: true
  },
  extends: ['eslint:recommended', 'plugin:import/recommended', 'prettier'],
  parserOptions: {
    ecmaVersion: 'latest',
    sourceType: 'module'
  },
  ignorePatterns: [
    'node_modules/**',
    'vendor/**',
    'public/assets/svg/**',
    'public/assets/css/**',
    'public/assets/fonts/**',
    'public/assets/images/**',
    'dist/**'
  ],
  settings: {
    'import/resolver': {
      node: {
        extensions: ['.js']
      }
    }
  },
  rules: {
    'no-console': ['warn', { allow: ['warn', 'error'] }],
    'no-unused-vars': [
      'error',
      {
        argsIgnorePattern: '^_',
        ignoreRestSiblings: true,
        caughtErrors: 'none'
      }
    ],
    'import/no-extraneous-dependencies': ['error', { devDependencies: true }]
  }
};
