import { applyResourceSnapshot } from './resources.js';
import { renderQueue } from './queue.js';
import { updateBuildingCard, updateResearchCard, updateShipCard } from './cards.js';
import { showFlashMessage } from './flashes.js';

const updateOverviewFromResponse = (data) => {
  if (!data || typeof data !== 'object') {
    return;
  }

  if (data.building && typeof data.building === 'object') {
    updateBuildingCard(data.building);
  }

  if (data.research && typeof data.research === 'object') {
    updateResearchCard(data.research);
  }

  if (data.ship && typeof data.ship === 'object') {
    updateShipCard(data.ship);
  }
};

const dispatchQueueUpdated = () => {
  document.dispatchEvent(new Event('queue:updated'));
};

export const submitAsyncForm = async (form) => {
  if (!(form instanceof HTMLFormElement)) {
    return;
  }

  const action = form.getAttribute('action') || window.location.href;
  const method = (form.getAttribute('method') || 'POST').toUpperCase();
  const formData = new FormData(form);
  const submitButton = form.querySelector('[type="submit"]');
  const buttonWasDisabled = submitButton?.disabled ?? false;
  if (submitButton) {
    submitButton.disabled = true;
  }

  try {
    const response = await fetch(action, {
      method,
      body: formData,
      headers: {
        Accept: 'application/json',
      },
      credentials: 'same-origin',
    });
    const data = await response.json().catch(() => null);

    if (!data) {
      throw new Error('Invalid JSON response');
    }

    const queueTarget = form.dataset.queueTarget || '';

    if (!response.ok || data.success === false) {
      showFlashMessage('danger', data.message ?? 'Action impossible.');
      if (data.resources) {
        applyResourceSnapshot(data.resources);
      }
      if (queueTarget && data.queue) {
        renderQueue(data.queue, queueTarget);
        dispatchQueueUpdated();
      }
      updateOverviewFromResponse(data);

      return;
    }

    showFlashMessage('success', data.message ?? 'Action effectuée.');
    if (data.resources) {
      applyResourceSnapshot(data.resources);
    }
    if (queueTarget && data.queue) {
      renderQueue(data.queue, queueTarget);
      dispatchQueueUpdated();
    }
    updateOverviewFromResponse(data);
    if (typeof data.planetId === 'number') {
      const topbar = document.querySelector('.topbar[data-resource-endpoint]');
      if (topbar) {
        topbar.dataset.planetId = String(data.planetId);
      }
    }
  } catch (_error) {
    showFlashMessage('danger', 'Impossible de traiter votre demande pour le moment.');
  } finally {
    if (submitButton && !buttonWasDisabled) {
      submitButton.disabled = false;
    }
  }
};

export const initAsyncForms = () => {
  const forms = document.querySelectorAll('form[data-async]');
  forms.forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      submitAsyncForm(form);
    });
  });
};

export default {
  initAsyncForms,
  submitAsyncForm,
};
