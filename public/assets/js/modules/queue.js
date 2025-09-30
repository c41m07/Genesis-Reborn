import {formatDuration, toSeconds} from './time.js';
import {escapeHtml, formatNumber} from './format.js';
import SELECTORS from './selectors.js';

const safeEscape = (value) => {
    if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
        return CSS.escape(value);
    }

    return String(value).replace(/"/g, '\\"');
};

const normalizeQueueCount = (queue, container) => {
    if (typeof queue?.count === 'number') {
        return Math.max(0, Math.floor(queue.count));
    }

    if (Array.isArray(queue?.jobs)) {
        return queue.jobs.length;
    }

    const fallback = container?.dataset?.queueCount ?? container?.getAttribute?.('data-queue-count');

    return Math.max(0, Math.floor(Number(fallback) || 0));
};

const normalizeQueueLimit = (queue, container, wrapper) => {
    if (typeof queue?.limit === 'number') {
        return Math.max(0, Math.floor(queue.limit));
    }

    const raw = wrapper?.dataset?.queueLimit ?? container?.dataset?.queueLimit;

    return Math.max(0, Math.floor(Number(raw) || 0));
};

export const updateQueueMetrics = (queue, container) => {
    if (!(container instanceof HTMLElement)) {
        return;
    }

    const wrapper = container.closest(SELECTORS.queueWrapper);
    if (!(wrapper instanceof HTMLElement)) {
        return;
    }

    const count = normalizeQueueCount(queue, container);
    const limit = normalizeQueueLimit(queue, container, wrapper);

    const countElement = wrapper.querySelector(SELECTORS.queueCount);
    if (countElement) {
        countElement.textContent = formatNumber(count);
    }

    const limitElement = wrapper.querySelector(SELECTORS.queueLimit);
    if (limitElement) {
        limitElement.textContent = limit > 0 ? formatNumber(limit) : '—';
    }

    wrapper.dataset.queueCount = String(count);
    wrapper.dataset.queueLimit = String(limit);
    container.dataset.queueCount = String(count);
    container.dataset.queueLimit = String(limit);
};

const renderQueueItem = (job, queueKey, serverNow) => {
    const label = escapeHtml(job.label ?? job.building ?? job.research ?? job.ship ?? '');
    const remaining = formatDuration(job.remaining ?? 0);
    const endDate = typeof job.endsAt === 'string' && job.endsAt !== '' ? new Date(job.endsAt) : null;
    const endTimestamp =
        endDate instanceof Date && !Number.isNaN(endDate.getTime())
            ? Math.floor(endDate.getTime() / 1000)
            : Math.floor(serverNow + toSeconds(job.remaining ?? 0, 0));

    let detail = '';
    if (queueKey === 'shipyard') {
        detail = `${formatNumber(job.quantity ?? 0)} unité(s)`;
    } else {
        detail = `Niveau ${formatNumber(job.targetLevel ?? 0)}`;
    }

    const timeHtml =
        endDate instanceof Date && !Number.isNaN(endDate.getTime())
            ? `<time datetime="${escapeHtml(endDate.toISOString())}">${endDate.toLocaleString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            })}</time>`
            : '';

    return `
    <li class="queue-list__item" data-endtime="${endTimestamp}">
      <div><strong>${label}</strong><span>${escapeHtml(detail)}</span></div>
      <div class="queue-list__timing">
        <span class="countdown">${escapeHtml(remaining)}</span>
        ${timeHtml}
      </div>
    </li>
  `;
};

export const renderQueue = (queue, target) => {
    if (!target) {
        return;
    }

    const selector = `[data-queue="${safeEscape(target)}"]`;
    const container = document.querySelector(selector);
    if (!(container instanceof HTMLElement)) {
        return;
    }

    updateQueueMetrics(queue, container);

    const emptyMessage = container.dataset.empty || 'Aucune action en cours.';
    if (!queue || !Array.isArray(queue.jobs) || queue.jobs.length === 0) {
        container.innerHTML = `<p class="empty-state">${escapeHtml(emptyMessage)}</p>`;

        return;
    }

    const serverNow =
        typeof queue.serverNow === 'number' && Number.isFinite(queue.serverNow)
            ? Math.floor(queue.serverNow)
            : Math.floor(Date.now() / 1000);
    const items = queue.jobs.map((job) => renderQueueItem(job, target, serverNow)).join('');
    container.innerHTML = `<ul class="queue-list">${items}</ul>`;
    if (typeof queue.serverNow === 'number' && Number.isFinite(queue.serverNow)) {
        container.dataset.serverNow = String(Math.floor(queue.serverNow));
    } else if (!container.dataset.serverNow) {
        container.dataset.serverNow = String(Math.floor(Date.now() / 1000));
    }
};

export default {
    renderQueue,
    updateQueueMetrics,
};
