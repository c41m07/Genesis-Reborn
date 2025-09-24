import { qsa, qs, removeElement, whenReady } from './dom.js';
import { createServerClock, formatCountdown, toSeconds } from './time.js';
import { formatNumber } from './format.js';
import SELECTORS from './selectors.js';

const contexts = new WeakMap();
let listenersRegistered = false;

const cssEscape = (value) => {
  if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
    return CSS.escape(value);
  }

  return String(value).replace(/"/g, '\\"');
};

const ensureEmptyState = (block) => {
  let empty = qs(SELECTORS.emptyState, block);
  if (!(empty instanceof HTMLElement)) {
    empty = document.createElement('p');
    empty.className = 'empty-state';
    empty.dataset.generated = '1';
    block.append(empty);
  }

  const message = block.getAttribute('data-empty');
  if (message) {
    empty.textContent = message;
  }

  return empty;
};

const toggleEmptyState = (block, hasItems) => {
  const list = qs(SELECTORS.queueList, block);
  if (list instanceof HTMLElement) {
    if (hasItems) {
      list.removeAttribute('hidden');
    } else {
      list.setAttribute('hidden', '');
    }
  }

  const empty = ensureEmptyState(block);
  if (hasItems) {
    empty.setAttribute('hidden', '');
  } else {
    empty.removeAttribute('hidden');
  }
};

const updateQueueSummary = (block, count) => {
  const queueKey = block.getAttribute('data-queue');
  if (!queueKey) {
    return;
  }

  const selector = `${SELECTORS.queueWrapper}[data-queue-wrapper="${cssEscape(queueKey)}"]`;
  const wrapper = document.querySelector(selector);
  if (!(wrapper instanceof HTMLElement)) {
    return;
  }

  const countElement = wrapper.querySelector(SELECTORS.queueCount);
  if (countElement) {
    countElement.textContent = formatNumber(count);
  }

  if (wrapper instanceof HTMLElement) {
    wrapper.dataset.queueCount = String(count);
  }
};

const destroyBlock = (block) => {
  const context = contexts.get(block);
  if (!context) {
    return;
  }

  window.clearInterval(context.timerId);
  contexts.delete(block);
};

const tickBlock = (block) => {
  const context = contexts.get(block);
  if (!context) {
    return;
  }

  if (!block.isConnected) {
    destroyBlock(block);
    return;
  }

  const now = context.clock.now();
  let activeItems = 0;

  qsa(SELECTORS.queueItem, block).forEach((item) => {
    const target = toSeconds(item.getAttribute('data-endtime'), 0);
    if (!target) {
      return;
    }

    const remaining = target - now;
    if (remaining <= 0) {
      removeElement(item);
      return;
    }

    const countdown = item.querySelector(SELECTORS.countdown);
    if (countdown) {
      countdown.textContent = formatCountdown(remaining);
    }

    activeItems += 1;
  });

  toggleEmptyState(block, activeItems > 0);
  updateQueueSummary(block, activeItems);
};

const setupBlock = (block) => {
  if (!(block instanceof HTMLElement)) {
    return;
  }

  if (contexts.has(block)) {
    return;
  }

  const serverNow = toSeconds(block.getAttribute('data-server-now'), 0);
  const clock = createServerClock(serverNow);

  const timerId = window.setInterval(() => tickBlock(block), 1000);
  contexts.set(block, { clock, timerId });
  tickBlock(block);
};

export const init = (root = document) => {
  qsa(SELECTORS.queueBlock, root).forEach((block) => {
    setupBlock(block);
  });
};

export const destroy = (root = document) => {
  qsa(SELECTORS.queueBlock, root).forEach((block) => {
    destroyBlock(block);
  });
};

export const refresh = (root = document) => {
  destroy(root);
  init(root);
};

const registerListeners = () => {
  if (listenersRegistered) {
    return;
  }

  whenReady(() => init());
  document.addEventListener('queue:updated', () => refresh());
  listenersRegistered = true;
};

registerListeners();

export default {
  init,
  destroy,
  refresh,
};
