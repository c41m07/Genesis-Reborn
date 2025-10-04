const FOCUSABLE_SELECTOR = [
  'a[href]:not([tabindex="-1"])',
  'button:not([disabled]):not([tabindex="-1"])',
  'textarea:not([disabled]):not([tabindex="-1"])',
  'input:not([type="hidden"]):not([disabled]):not([tabindex="-1"])',
  'select:not([disabled]):not([tabindex="-1"])',
  'details summary:not([tabindex="-1"])',
  '[tabindex]:not([tabindex="-1"])',
].join(', ');

const trapRegistry = new WeakMap();

const cleanupTabIndex = (element) => {
  if (!(element instanceof HTMLElement)) {
    return () => {};
  }

  if (element.hasAttribute('tabindex')) {
    return () => {};
  }

  element.setAttribute('tabindex', '-1');
  return () => {
    element.removeAttribute('tabindex');
  };
};

const getFocusableElements = (container) => {
  if (!(container instanceof HTMLElement)) {
    return [];
  }

  return Array.from(container.querySelectorAll(FOCUSABLE_SELECTOR)).filter((element) => {
    if (!(element instanceof HTMLElement)) {
      return false;
    }

    if (element.hasAttribute('disabled')) {
      return false;
    }

    if (element.getAttribute('aria-hidden') === 'true') {
      return false;
    }

    return true;
  });
};

export const createFocusTrap = (container) => {
  if (!(container instanceof HTMLElement)) {
    throw new TypeError('Focus trap container must be an HTMLElement');
  }

  let focusable = getFocusableElements(container);
  let releaseTabIndex = cleanupTabIndex(container);
  let previousFocus = null;

  const refreshFocusable = () => {
    focusable = getFocusableElements(container);
  };

  const focusElement = (element) => {
    if (element instanceof HTMLElement) {
      element.focus({ preventScroll: true });
    }
  };

  const handleKeydown = (event) => {
    if (event.key !== 'Tab') {
      return;
    }

    refreshFocusable();
    if (focusable.length === 0) {
      event.preventDefault();
      focusElement(container);
      return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;

    if (event.shiftKey) {
      if (active === first || !container.contains(active)) {
        event.preventDefault();
        focusElement(last);
      }
      return;
    }

    if (active === last || !container.contains(active)) {
      event.preventDefault();
      focusElement(first);
    }
  };

  const trap = {
    activate(options = {}) {
      const { initialFocus } = options;
      refreshFocusable();
      releaseTabIndex();
      releaseTabIndex = cleanupTabIndex(container);
      previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      container.addEventListener('keydown', handleKeydown);

      const fallback = focusable[0] ?? container;
      if (initialFocus instanceof HTMLElement && container.contains(initialFocus)) {
        focusElement(initialFocus);
      } else {
        focusElement(fallback);
      }
    },
    deactivate() {
      container.removeEventListener('keydown', handleKeydown);
      releaseTabIndex();
      container.blur?.();
      const focusTarget = previousFocus;
      previousFocus = null;
      if (focusTarget instanceof HTMLElement && focusTarget.isConnected) {
        setTimeout(() => {
          focusElement(focusTarget);
        }, 0);
      }
    },
    refresh: refreshFocusable,
  };

  trapRegistry.set(container, trap);

  return trap;
};

export const initFocusTraps = () => {
  if (typeof document === 'undefined') {
    return;
  }

  document.querySelectorAll('[data-focus-trap]').forEach((element) => {
    if (!(element instanceof HTMLElement)) {
      return;
    }

    if (trapRegistry.has(element)) {
      return;
    }

    const trap = createFocusTrap(element);
    element.addEventListener('focusin', () => {
      trap.refresh();
    });

    if (element.dataset.focusTrap === 'auto') {
      trap.activate();
    }

    trapRegistry.set(element, trap);
  });
};

export const getRegisteredFocusTrap = (element) => {
  if (!(element instanceof HTMLElement)) {
    return null;
  }

  return trapRegistry.get(element) ?? null;
};

export default {
  createFocusTrap,
  initFocusTraps,
  getRegisteredFocusTrap,
};
