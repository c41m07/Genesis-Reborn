import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(__dirname, '../../../../');

const read = (relativePath) => readFileSync(path.join(projectRoot, relativePath), 'utf-8');

test('base layout exposes live regions and focus traps', () => {
  const layout = read('templates/layouts/base.php');
  assert(layout.includes('role="status" aria-live="polite" aria-atomic="true"'));
  assert(layout.includes('data-focus-trap'));
  assert(layout.includes('aria-controls="primary-sidebar"'));
});

test('dashboard page uses bootstrap card components', () => {
  const dashboard = read('templates/pages/dashboard/index.php');
  assert(dashboard.includes('class="card'));
  assert(dashboard.includes('dashboard-hero__stats'));
  assert(dashboard.includes('role="list"'));
});

test('colony page renders queue data hooks', () => {
  const colony = read('templates/pages/colony/index.php');
  assert(colony.includes('data-queue-wrapper="buildings"'));
  assert(colony.includes('data-queue-count'));
  assert(colony.includes('building-card__header'));
});

test('fleet page keeps mission forms accessible', () => {
  const fleet = read('templates/pages/fleet/index.php');
  assert(fleet.includes('mission-form'));
  assert(fleet.includes('card'));
  assert(fleet.includes('data-async="queue"') || fleet.includes('csrf_token'));
});
