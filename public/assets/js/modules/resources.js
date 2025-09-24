import { formatNumber } from './format.js';

const resourceTicker = {
  states: new Map(),
  intervalId: null,
};

const renderResourceMeter = (key, value, perHour, capacity) => {
  const meter = document.querySelector(`.resource-meter[data-resource="${CSS.escape(key)}"]`);
  if (!(meter instanceof HTMLElement)) {
    return;
  }

  const valueElement = meter.querySelector('[data-resource-value]');
  const rateElement = meter.querySelector('[data-resource-rate]');
  const capacityElement = meter.querySelector('[data-resource-capacity-display]');
  const numericCapacity = typeof capacity === 'number' ? capacity : Number(capacity ?? 0);
  const normalizedCapacity = Math.max(
    0,
    Math.floor(Number.isFinite(numericCapacity) ? numericCapacity : 0)
  );
  const numericValue = typeof value === 'number' ? value : Number(value ?? 0);
  const normalizedValue = Math.max(0, Math.floor(Number.isFinite(numericValue) ? numericValue : 0));
  const cappedValue =
    normalizedCapacity > 0 ? Math.min(normalizedValue, normalizedCapacity) : normalizedValue;
  const numericPerHour = typeof perHour === 'number' ? perHour : Number(perHour ?? 0);
  const normalizedPerHour = Number.isFinite(numericPerHour) ? numericPerHour : 0;

  if (valueElement) {
    valueElement.textContent = formatNumber(cappedValue);
  }

  if (capacityElement) {
    capacityElement.textContent =
      normalizedCapacity > 0 ? `/ ${formatNumber(normalizedCapacity)}` : '/ —';
  }

  meter.dataset.resourceCapacity = String(normalizedCapacity);

  const isDepleted = cappedValue <= 0 && normalizedPerHour < 0;
  meter.classList.toggle('resource-meter--warning', isDepleted);

  if (rateElement) {
    const ratePrefix = key !== 'energy' && normalizedPerHour > 0 ? '+' : '';
    rateElement.textContent = `${ratePrefix}${formatNumber(Math.round(normalizedPerHour))}/h`;
    rateElement.classList.toggle('is-positive', normalizedPerHour >= 0);
    rateElement.classList.toggle('is-negative', normalizedPerHour < 0);
  }
};

const stopResourceTicker = () => {
  if (resourceTicker.intervalId !== null) {
    window.clearInterval(resourceTicker.intervalId);
    resourceTicker.intervalId = null;
  }
};

const startResourceTicker = () => {
  stopResourceTicker();

  if (resourceTicker.states.size === 0) {
    return;
  }

  resourceTicker.intervalId = window.setInterval(() => {
    const now = Date.now();
    resourceTicker.states.forEach((state, key) => {
      const elapsedSeconds = Math.max(0, Math.floor((now - state.timestamp) / 1000));
      if (elapsedSeconds === 0) {
        return;
      }

      const perHour = Number.isFinite(state.perHour) ? state.perHour : 0;
      const capacity = Number.isFinite(state.capacity) ? Math.max(0, state.capacity) : 0;
      const currentValue = Number.isFinite(state.value) ? state.value : 0;
      const increment = perHour * (elapsedSeconds / 3600);
      let nextValue = currentValue + increment;
      nextValue = Math.max(0, nextValue);
      if (capacity > 0) {
        nextValue = Math.min(nextValue, capacity);
      }

      state.value = nextValue;
      state.perHour = perHour;
      state.capacity = capacity;
      state.timestamp = now;
      renderResourceMeter(key, state.value, perHour, capacity);
    });
  }, 1000);
};

export const applyResourceSnapshot = (resources = {}) => {
  const now = Date.now();
  resourceTicker.states.clear();

  Object.entries(resources).forEach(([key, data]) => {
    const source = data && typeof data === 'object' ? data : {};
    const numericValue =
      typeof source.value === 'number' ? source.value : Number(source.value ?? 0);
    const numericPerHour =
      typeof source.perHour === 'number' ? source.perHour : Number(source.perHour ?? 0);
    const numericCapacity =
      typeof source.capacity === 'number' ? source.capacity : Number(source.capacity ?? 0);
    const normalizedCapacity = Math.max(
      0,
      Math.floor(Number.isFinite(numericCapacity) ? numericCapacity : 0)
    );
    const normalizedPerHour = Number.isFinite(numericPerHour) ? numericPerHour : 0;
    const normalizedValue = Math.max(0, Number.isFinite(numericValue) ? numericValue : 0);
    const cappedValue =
      normalizedCapacity > 0 ? Math.min(normalizedValue, normalizedCapacity) : normalizedValue;

    resourceTicker.states.set(key, {
      value: cappedValue,
      perHour: normalizedPerHour,
      capacity: normalizedCapacity,
      timestamp: now,
    });

    renderResourceMeter(key, cappedValue, normalizedPerHour, normalizedCapacity);
  });

  startResourceTicker();
};

export const bootstrapResourceTicker = () => {
  const meters = document.querySelectorAll('.resource-meter[data-resource]');
  if (meters.length === 0) {
    return;
  }

  const snapshot = {};
  meters.forEach((meter) => {
    if (!(meter instanceof HTMLElement)) {
      return;
    }

    const key = meter.getAttribute('data-resource');
    if (!key) {
      return;
    }

    const valueText = meter.querySelector('[data-resource-value]')?.textContent ?? '0';
    const rateText = meter.querySelector('[data-resource-rate]')?.textContent ?? '0';
    const numericValue = Number(valueText.replace(/[^0-9-]/g, ''));
    const numericRate = Number(rateText.replace(/[^0-9-]/g, ''));
    const capacityAttr =
      meter.dataset.resourceCapacity ?? meter.getAttribute('data-resource-capacity') ?? '0';
    let numericCapacity = Number(capacityAttr);
    if (!Number.isFinite(numericCapacity)) {
      const capacityText =
        meter.querySelector('[data-resource-capacity-display]')?.textContent ?? '0';
      const parsedCapacity = Number(capacityText.replace(/[^0-9-]/g, ''));
      numericCapacity = Number.isFinite(parsedCapacity) ? parsedCapacity : 0;
    }

    snapshot[key] = {
      value: Number.isFinite(numericValue) ? numericValue : 0,
      perHour: Number.isFinite(numericRate) ? numericRate : 0,
      capacity: Number.isFinite(numericCapacity) ? numericCapacity : 0,
    };
  });

  applyResourceSnapshot(snapshot);
};

export const initResourcePolling = () => {
  const topbar = document.querySelector('.topbar[data-resource-endpoint]');
  if (!(topbar instanceof HTMLElement)) {
    return;
  }

  const endpoint = topbar.dataset.resourceEndpoint || '';
  const pollDelay = Number(topbar.dataset.resourcePoll || 0);
  let planetId = Number(topbar.dataset.planetId || 0);

  if (!endpoint || !pollDelay || planetId <= 0) {
    return;
  }

  const fetchResources = async () => {
    planetId = Number(topbar.dataset.planetId || planetId);
    if (planetId <= 0) {
      return;
    }

    try {
      const url = new URL(endpoint, window.location.origin);
      url.searchParams.set('planet', String(planetId));
      const response = await fetch(url.toString(), {
        headers: {
          Accept: 'application/json',
        },
        credentials: 'same-origin',
      });
      const data = await response.json().catch(() => null);
      if (data?.success && data.resources) {
        applyResourceSnapshot(data.resources);
      }
    } catch (_error) {
      // Ignore polling errors silently.
    }
  };

  fetchResources();
  window.setInterval(fetchResources, pollDelay);
};

export default {
  applyResourceSnapshot,
  bootstrapResourceTicker,
  initResourcePolling,
};
