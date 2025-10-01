import { afterEach, beforeEach, test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', {
  url: 'https://example.com/',
});

globalThis.window = dom.window;
globalThis.document = dom.window.document;
globalThis.HTMLElement = dom.window.HTMLElement;
globalThis.Element = dom.window.Element;
globalThis.Node = dom.window.Node;
globalThis.Event = dom.window.Event;
globalThis.CustomEvent = dom.window.CustomEvent;

Object.defineProperty(document, 'readyState', {
  configurable: true,
  value: 'complete',
});

globalThis.CSS = {
  escape(value) {
    return String(value);
  },
};

const originalDateNow = Date.now;
let nextIntervalId = 1;
const activeIntervals = new Map();
const clearedIntervals = new Set();

window.setInterval = (callback, delay) => {
  const id = nextIntervalId++;
  activeIntervals.set(id, { callback, delay });

  return id;
};

window.clearInterval = (id) => {
  clearedIntervals.add(id);
  activeIntervals.delete(id);
};

const countdownModule = await import('../modules/countdown.js');

const { init, destroy, refresh } = countdownModule;

beforeEach(() => {
  document.body.innerHTML = '';
  activeIntervals.clear();
  clearedIntervals.clear();
  nextIntervalId = 1;
  Date.now = () => 100 * 1000;
  window.Date.now = Date.now;
});

afterEach(() => {
  destroy();
  Date.now = originalDateNow;
  window.Date.now = originalDateNow;
});

test('init updates countdown values and queue summary', () => {
  document.body.innerHTML = `
        <div data-queue-wrapper="buildings">
            <span data-queue-count>0</span>
        </div>
        <section class="queue-block" data-server-now="100" data-queue="buildings">
            <ul class="queue-list">
                <li class="queue-list__item" data-endtime="130">
                    <span class="countdown"></span>
                </li>
            </ul>
            <p class="empty-state" hidden>Vide</p>
        </section>
    `;

  init();

  const countdown = document.querySelector('.countdown');
  assert.equal(countdown?.textContent, '00:30');

  const wrapper = document.querySelector('[data-queue-wrapper="buildings"]');
  assert(wrapper instanceof HTMLElement);
  assert.equal(wrapper.dataset.queueCount, '1');

  const emptyState = document.querySelector('.empty-state');
  assert(emptyState instanceof HTMLElement);
  assert(emptyState.hasAttribute('hidden'));

  assert.equal(activeIntervals.size, 1);
});

test('refresh reinitialises countdowns and clears completed items', () => {
  document.body.innerHTML = `
        <div data-queue-wrapper="research">
            <span data-queue-count>1</span>
        </div>
        <section class="queue-block" data-server-now="100" data-queue="research" data-empty="Aucune recherche en cours">
            <ul class="queue-list">
                <li class="queue-list__item" data-endtime="105">
                    <span class="countdown">pending</span>
                </li>
            </ul>
            <p class="empty-state" hidden></p>
        </section>
    `;

  init();

  const block = document.querySelector('.queue-block');
  if (block instanceof HTMLElement) {
    block.setAttribute('data-server-now', '210');
  }

  Date.now = () => 200 * 1000;
  window.Date.now = Date.now;

  refresh();

  const list = document.querySelector('.queue-list');
  assert(list instanceof HTMLElement);
  assert.equal(list.hasAttribute('hidden'), true);

  const emptyState = document.querySelector('.empty-state');
  assert(emptyState instanceof HTMLElement);
  assert.equal(emptyState.hasAttribute('hidden'), false);
  assert.equal(emptyState.textContent, 'Aucune recherche en cours');

  const wrapper = document.querySelector('[data-queue-wrapper="research"]');
  assert(wrapper instanceof HTMLElement);
  assert.equal(wrapper.dataset.queueCount, '0');

  assert.ok(clearedIntervals.size >= 1);
});
