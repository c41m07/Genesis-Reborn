const ensureFlashContainer = () => {
  let container = document.querySelector('.flashes');
  if (!container) {
    const mainContent = document.querySelector('.workspace__content');
    container = document.createElement('div');
    container.className = 'flashes';
    if (mainContent) {
      mainContent.prepend(container);
    }
  }

  return container;
};

export const showFlashMessage = (type, message) => {
  if (!message) {
    return;
  }

  const container = ensureFlashContainer();
  const flash = document.createElement('div');
  flash.className = `flash flash--${type}`;
  flash.textContent = message;
  container.appendChild(flash);

  window.setTimeout(() => {
    flash.classList.add('is-hidden');
    window.setTimeout(() => {
      flash.remove();
    }, 400);
  }, 5000);
};

export default {
  showFlashMessage,
};
