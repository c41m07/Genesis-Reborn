import SELECTORS from './selectors.js';

export const initSidebar = () => {
  const sidebar = document.querySelector(SELECTORS.sidebar);
  if (!(sidebar instanceof HTMLElement)) {
    return;
  }

  const overlay = document.querySelector(SELECTORS.sidebarOverlay);
  const toggles = Array.from(document.querySelectorAll(SELECTORS.sidebarToggle));
  const closes = Array.from(document.querySelectorAll(SELECTORS.sidebarClose));
  const body = document.body;

  const setSidebarState = (isOpen) => {
    sidebar.classList.toggle('sidebar--open', isOpen);
    overlay?.classList.toggle('is-visible', isOpen);
    body.classList.toggle('no-scroll', isOpen);
    toggles.forEach((toggle) => {
      if (toggle instanceof HTMLElement) {
        toggle.setAttribute('aria-expanded', String(isOpen));
      }
    });
  };

  const closeSidebar = () => setSidebarState(false);

  toggles.forEach((toggle) => {
    if (!(toggle instanceof HTMLElement)) {
      return;
    }

    toggle.addEventListener('click', () => {
      setSidebarState(!sidebar.classList.contains('sidebar--open'));
    });
  });

  closes.forEach((closeControl) => {
    if (!(closeControl instanceof HTMLElement)) {
      return;
    }

    closeControl.addEventListener('click', closeSidebar);
  });

  overlay?.addEventListener('click', closeSidebar);

  document.addEventListener('keyup', (event) => {
    if (event.key === 'Escape') {
      closeSidebar();
    }
  });

  const desktopMatch = window.matchMedia('(min-width: 992px)');
  const handleDesktopChange = (event) => {
    if (event.matches) {
      closeSidebar();
    }
  };

  handleDesktopChange(desktopMatch);
  desktopMatch.addEventListener?.('change', handleDesktopChange);
};

export default {
  initSidebar,
};
