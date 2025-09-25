#!/usr/bin/env node
const { spawnSync } = require('node:child_process');
const path = require('node:path');

process.env.ESLINT_USE_FLAT_CONFIG = '0';

const args = process.argv.slice(2);
const binName = process.platform === 'win32' ? 'eslint.cmd' : 'eslint';
const eslintBin = path.join(__dirname, '..', 'node_modules', '.bin', binName);
const result = spawnSync(eslintBin, args, {
  stdio: 'inherit',
  env: process.env,
});

if (result.error) {
  throw result.error;
}

process.exit(result.status ?? 1);
