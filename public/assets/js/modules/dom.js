const toArray = (value) => {
  if (!value) {
    return [];
  }

  if (Array.isArray(value)) {
    return value;
  }

  return Array.from(value);
};

export const qs = (selector, scope = document) => {
  if (!selector) {
    return null;
  }

  return (scope ?? document).querySelector(selector);
};

export const qsa = (selector, scope = document) => {
  if (!selector) {
    return [];
  }

  return toArray((scope ?? document).querySelectorAll(selector));
};

export const createFragment = (html) => {
  const template = document.createElement('template');
  template.innerHTML = String(html ?? '');

  return template.content.cloneNode(true);
};

export const emptyElement = (element) => {
  if (!(element instanceof Element)) {
    return;
  }

  element.replaceChildren();
};

export const setTextContent = (element, value) => {
  if (!(element instanceof Element)) {
    return;
  }

  element.textContent = value;
};

export const toggleClass = (element, className, state) => {
  if (!(element instanceof Element) || !className) {
    return;
  }

  element.classList.toggle(className, state);
};

export const delegate = (root, eventName, selector, callback, options) => {
  const container = root ?? document;
  if (!container || typeof container.addEventListener !== 'function') {
    return () => {};
  }

  const listener = (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }

    const matched = target.closest(selector);
    if (matched && container.contains(matched)) {
      callback.call(matched, event, matched);
    }
  };

  container.addEventListener(eventName, listener, options);

  return () => container.removeEventListener(eventName, listener, options);
};

export const closestData = (element, attribute) => {
  if (!(element instanceof Element)) {
    return null;
  }

  const target = element.closest(`[${attribute}]`);

  return target?.getAttribute(attribute) ?? null;
};

export const readNumber = (value, fallback = 0) => {
  const numeric = Number(value);

  return Number.isFinite(numeric) ? numeric : fallback;
};

export const setAttributes = (element, attributes = {}) => {
  if (!(element instanceof Element) || !attributes || typeof attributes !== 'object') {
    return;
  }

  Object.entries(attributes).forEach(([key, val]) => {
    if (val === false || val === null || val === undefined) {
      element.removeAttribute(key);
    } else {
      element.setAttribute(key, String(val));
    }
  });
};

export const getDatasetValue = (element, key) => {
  if (!(element instanceof HTMLElement)) {
    return undefined;
  }

  return element.dataset?.[key];
};

export const getDatasetNumber = (element, key, fallback = 0) => {
  const raw = getDatasetValue(element, key);

  return readNumber(raw, fallback);
};

export const isElement = (value) => value instanceof Element;

export const removeElement = (element) => {
  if (!(element instanceof Element)) {
    return;
  }

  if (typeof element.remove === 'function') {
    element.remove();
  } else if (element.parentNode) {
    element.parentNode.removeChild(element);
  }
};

export const ensureElement = (root, selector) => {
  const element = qs(selector, root);
  if (element instanceof Element) {
    return element;
  }

  const created = document.createElement('div');
  created.className = selector.replace(/^[.#]/, '');
  (root ?? document.body).appendChild(created);

  return created;
};

export const whenReady = (callback) => {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', callback, { once: true });
  } else {
    callback();
  }
};

export default {
  qs,
  qsa,
  delegate,
  createFragment,
  emptyElement,
  setTextContent,
  toggleClass,
  setAttributes,
  closestData,
  readNumber,
  getDatasetValue,
  getDatasetNumber,
  isElement,
  removeElement,
  ensureElement,
  whenReady,
};
