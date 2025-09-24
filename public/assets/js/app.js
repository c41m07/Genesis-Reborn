const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char] || char));

const integerNumberFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
const decimalNumberFormatter = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatNumber = (value) => {
    const numericValue = typeof value === 'number' ? value : Number(value ?? 0);
    if (!Number.isFinite(numericValue)) {
        return integerNumberFormatter.format(0);
    }

    const absValue = Math.abs(numericValue);
    const suffixes = [
        { limit: 1_000_000_000, suffix: 'b' },
        { limit: 1_000_000, suffix: 'm' },
        { limit: 1_000, suffix: 'k' },
    ];

    for (const { limit, suffix } of suffixes) {
        if (absValue >= limit) {
            const scaled = Math.trunc((absValue / limit) * 100 + 1e-9) / 100;
            const signedScaled = numericValue < 0 ? -scaled : scaled;
            const hasFraction = Math.abs(signedScaled - Math.round(signedScaled)) > 1e-9;
            const formatter = hasFraction ? decimalNumberFormatter : integerNumberFormatter;

            return `${formatter.format(signedScaled)}${suffix}`;
        }
    }

    const hasFraction = Math.abs(numericValue - Math.round(numericValue)) > 1e-9;
    const formatter = hasFraction ? decimalNumberFormatter : integerNumberFormatter;

    return formatter.format(numericValue);
};

const formatSeconds = (value) => {
    const seconds = Math.max(0, Math.floor(Number(value ?? 0)));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    const parts = [];
    if (hours > 0) {
        parts.push(`${hours} h`);
    }
    if (minutes > 0) {
        parts.push(`${minutes} min`);
    }
    if (parts.length === 0 || remainingSeconds > 0) {
        parts.push(`${remainingSeconds} s`);
    }

    return parts.join(' ');
};

const SPRITE_URL = new URL('../svg/sprite.svg', import.meta.url).toString();

const RESOURCE_LABELS = {
    metal: 'Métal',
    crystal: 'Cristal',
    hydrogen: 'Hydrogène',
    energy: 'Énergie',
    storage: 'Capacité',
};

const getResourceLabel = (key) => {
    if (!key) {
        return '';
    }

    return RESOURCE_LABELS[key] ?? key.charAt(0).toUpperCase() + key.slice(1);
};

const createIcon = (name, extraClass = '') => {
    const classes = ['icon', 'icon-sm'];
    if (typeof extraClass === 'string' && extraClass.trim() !== '') {
        classes.push(extraClass.trim());
    }

    return `
        <svg class="${classes.join(' ')}" aria-hidden="true">
            <use href="${SPRITE_URL}#icon-${escapeHtml(name)}"></use>
        </svg>
    `.trim();
};

const normalizeClassNames = (...values) => values
    .map((value) => (typeof value === 'string' ? value.trim() : ''))
    .filter((value) => value !== '')
    .join(' ');

const metricValueClass = (value) => {
    if (value > 0) {
        return 'metric-line__value metric-line__value--positive';
    }
    if (value < 0) {
        return 'metric-line__value metric-line__value--negative';
    }

    return 'metric-line__value metric-line__value--neutral';
};

const renderCostList = (cost = {}, time = 0, baseTime = null) => {
    const items = [];

    if (cost && typeof cost === 'object') {
        Object.entries(cost).forEach(([resource, amount]) => {
            items.push(`
                <li>${createIcon(resource)}<span>${formatNumber(Number(amount) || 0)}</span></li>
            `);
        });
    }

    const numericTime = Number(time ?? 0);
    const normalizedTime = Number.isFinite(numericTime) ? Math.max(0, Math.floor(numericTime)) : 0;
    let normalizedBaseTime = normalizedTime;
    if (baseTime !== null && baseTime !== undefined) {
        const numericBaseTime = Number(baseTime);
        if (Number.isFinite(numericBaseTime)) {
            normalizedBaseTime = Math.max(0, Math.floor(numericBaseTime));
        }
    }

    const timeLabel = escapeHtml(formatSeconds(normalizedTime));
    const baseLabel = normalizedBaseTime !== normalizedTime
        ? ` <small>(base ${escapeHtml(formatSeconds(normalizedBaseTime))})</small>`
        : '';

    items.push(`
        <li>${createIcon('time')}<span>${timeLabel}${baseLabel}</span></li>
    `);

    return `<ul class="resource-list">${items.join('')}</ul>`;
};

let requirementsPanelSequence = 0;

const renderRequirementsPanel = ({
    title = 'Pré-requis',
    icon = '',
    iconClass = 'requirements-panel__glyph',
    items = [],
    panelClass = '',
    summaryClass = '',
    contentClass = '',
    listClass = '',
} = {}) => {
    if (!Array.isArray(items) || items.length === 0) {
        return '';
    }

    const entries = items.map((missing) => {
        const label = escapeHtml(String(missing.label ?? missing.key ?? ''));
        const current = formatNumber(Number(missing.current ?? 0));
        const required = formatNumber(Number(missing.level ?? missing.required ?? 0));

        return `
            <li class="requirements-panel__item">
                <span class="requirements-panel__name building-card__requirement-name">${label}</span>
                <span class="requirements-panel__progress building-card__requirement-progress">(${current}/${required})</span>
            </li>
        `;
    }).join('');

    if (!entries) {
        return '';
    }

    requirementsPanelSequence += 1;
    const panelId = `requirements-panel-dynamic-${requirementsPanelSequence}`;
    const contentId = `${panelId}-content`;

    const panelClasses = normalizeClassNames('requirements-panel', panelClass);
    const summaryClasses = normalizeClassNames('requirements-panel__summary', summaryClass);
    const contentClasses = normalizeClassNames('requirements-panel__content', contentClass);
    const listClasses = normalizeClassNames('requirements-panel__list', 'building-card__requirements', listClass);
    const iconHtml = icon
        ? `<span class="requirements-panel__icon">${createIcon(icon, iconClass)}</span>`
        : '';

    return `
        <details class="${panelClasses}" data-requirements-panel>
            <summary class="${summaryClasses}" id="${panelId}"
                data-requirements-summary aria-controls="${contentId}" aria-expanded="false">
                ${iconHtml}
                <span class="requirements-panel__title">${escapeHtml(title)}</span>
                <span class="requirements-panel__chevron" aria-hidden="true"></span>
            </summary>
            <div class="${contentClasses}" id="${contentId}"
                data-requirements-content role="region" aria-labelledby="${panelId}" aria-hidden="true">
                <ul class="${listClasses}">
                    ${entries}
                </ul>
            </div>
        </details>
    `.trim();
};

const renderStorageSection = (storage = {}) => {
    const currentEntries = storage && typeof storage === 'object' && storage.current && typeof storage.current === 'object'
        ? Object.entries(storage.current)
        : [];
    const nextEntries = storage && typeof storage === 'object' && storage.next && typeof storage.next === 'object'
        ? Object.entries(storage.next)
        : [];

    if (currentEntries.length === 0 && nextEntries.length === 0) {
        return '';
    }

    const renderList = (entries, className) => entries.map(([resource, value]) => {
        const label = getResourceLabel(resource);

        return `
            <li class="metric-line">
                <span class="metric-line__label">${escapeHtml(label)}</span>
                <span class="${className}">${formatNumber(Number(value) || 0)}</span>
            </li>
        `;
    }).join('');

    const storageLabel = getResourceLabel('storage');

    return `
        <div class="metric-section">
            <p class="metric-section__title">${escapeHtml(storageLabel)} actuelle</p>
            <ul class="metric-section__list">${renderList(currentEntries, 'metric-line__value metric-line__value--neutral')}</ul>
            <p class="metric-section__title">${escapeHtml(storageLabel)} prochain niveau</p>
            <ul class="metric-section__list">${renderList(nextEntries, 'metric-line__value metric-line__value--positive')}</ul>
        </div>
    `;
};

const renderConsumptionSection = (consumption = {}) => {
    if (!consumption || typeof consumption !== 'object') {
        return '';
    }

    const lines = [];

    Object.entries(consumption).forEach(([resource, values]) => {
        if (!values || typeof values !== 'object') {
            return;
        }

        const currentValue = Number(values.current ?? 0);
        const nextValue = Number(values.next ?? 0);
        if (currentValue === 0 && nextValue === 0) {
            return;
        }

        const resourceLabel = getResourceLabel(resource);
        const labelSuffix = resource === 'energy' ? '' : ` (${escapeHtml(resourceLabel)})`;
        const unitSuffix = resource === 'energy'
            ? ' énergie/h'
            : ` ${resourceLabel.toLocaleLowerCase('fr-FR')}/h`;

        const displayCurrent = currentValue > 0 ? -currentValue : currentValue;
        const displayNext = nextValue > 0 ? -nextValue : nextValue;

        lines.push(`
            <p class="metric-line">
                <span class="metric-line__label">Consommation actuelle${labelSuffix}</span>
                <span class="${metricValueClass(displayCurrent)}">${escapeHtml(formatNumber(displayCurrent))}${escapeHtml(unitSuffix)}</span>
            </p>
        `);
        lines.push(`
            <p class="metric-line">
                <span class="metric-line__label">Consommation prochain niveau${labelSuffix}</span>
                <span class="${metricValueClass(displayNext)}">${escapeHtml(formatNumber(displayNext))}${escapeHtml(unitSuffix)}</span>
            </p>
        `);
    });

    return lines.join('');
};

const renderBuildingSections = (building = {}) => {
    const costHtml = `
        <div class="building-card__block">
            <h3>Prochaine amélioration</h3>
            ${renderCostList(building.cost ?? {}, building.time ?? 0, building.baseTime ?? null)}
        </div>
    `;

    const effectsParts = [];
    const production = building.production ?? {};
    const resourceKey = production.resource ?? '';
    if (resourceKey && resourceKey !== 'storage') {
        const unitSuffix = resourceKey === 'energy'
            ? ' énergie/h'
            : ` ${getResourceLabel(resourceKey).toLocaleLowerCase('fr-FR')}/h`;
        const currentValue = Number(production.current ?? 0);
        const nextValue = Number(production.next ?? 0);
        const currentDisplay = currentValue > 0 ? `+${formatNumber(currentValue)}` : formatNumber(currentValue);
        const nextDisplay = nextValue > 0 ? `+${formatNumber(nextValue)}` : formatNumber(nextValue);

        effectsParts.push(`
            <p class="metric-line">
                <span class="metric-line__label">Production actuelle</span>
                <span class="${metricValueClass(currentValue)}">${escapeHtml(currentDisplay)}${escapeHtml(unitSuffix)}</span>
            </p>
        `);
        effectsParts.push(`
            <p class="metric-line">
                <span class="metric-line__label">Production prochain niveau</span>
                <span class="${metricValueClass(nextValue)}">${escapeHtml(nextDisplay)}${escapeHtml(unitSuffix)}</span>
            </p>
        `);
    }

    const storageSection = renderStorageSection(building.storage ?? {});
    if (storageSection) {
        effectsParts.push(storageSection);
    }

    const consumptionSection = renderConsumptionSection(building.consumption ?? {});
    if (consumptionSection) {
        effectsParts.push(consumptionSection);
    }

    const effectsHtml = `
        <details class="building-card__details" data-building-effects>
            <summary class="building-card__details-summary">
                <span class="building-card__details-title">Effets</span>
                <span class="building-card__details-chevron" aria-hidden="true"></span>
            </summary>
            <div class="building-card__details-content">
                ${effectsParts.join('')}
            </div>
        </details>
    `;

    const requirements = building.requirements ?? null;
    const shouldRenderRequirements = requirements
        && requirements.ok === false
        && Array.isArray(requirements.missing)
        && requirements.missing.length > 0;

    const requirementsHtml = shouldRenderRequirements
        ? `
            <div class="building-card__block building-card__block--requirements">
                ${renderRequirementsPanel({
                    title: 'Pré-requis',
                    icon: 'buildings',
                    items: requirements.missing,
                })}
            </div>
        `
        : '';

    return `${costHtml}${effectsHtml}${requirementsHtml}`;
};

const renderResearchRequirements = (requirements = null) => {
    if (!requirements || requirements.ok !== false || !Array.isArray(requirements.missing) || requirements.missing.length === 0) {
        return '';
    }

    return `
        <div class="tech-card__section tech-card__requirements">
            ${renderRequirementsPanel({
                title: 'Pré-requis',
                icon: 'research',
                items: requirements.missing,
            })}
        </div>
    `;
};

const renderShipRequirements = (requirements = null) => {
    if (!requirements || requirements.ok !== false || !Array.isArray(requirements.missing) || requirements.missing.length === 0) {
        return '';
    }

    return `
        <div class="ship-card__section ship-card__requirements">
            ${renderRequirementsPanel({
                title: 'Pré-requis',
                icon: 'shipyard',
                items: requirements.missing,
            })}
        </div>
    `;
};

const clampPercentage = (value) => Math.max(0, Math.min(100, value));

const resourceTicker = {
    states: new Map(),
    intervalId: null,
};

const renderResourceMeter = (key, value, perHour, capacity) => {
    const meter = document.querySelector(`.resource-meter[data-resource="${CSS.escape(key)}"]`);
    if (!meter) {
        return;
    }

    const valueElement = meter.querySelector('[data-resource-value]');
    const rateElement = meter.querySelector('[data-resource-rate]');
    const capacityElement = meter.querySelector('[data-resource-capacity-display]');
    const numericCapacity = typeof capacity === 'number' ? capacity : Number(capacity ?? 0);
    const normalizedCapacity = Math.max(0, Math.floor(Number.isFinite(numericCapacity) ? numericCapacity : 0));
    const numericValue = typeof value === 'number' ? value : Number(value ?? 0);
    const normalizedValue = Math.max(0, Math.floor(Number.isFinite(numericValue) ? numericValue : 0));
    const cappedValue = normalizedCapacity > 0 ? Math.min(normalizedValue, normalizedCapacity) : normalizedValue;
    const numericPerHour = typeof perHour === 'number' ? perHour : Number(perHour ?? 0);
    const normalizedPerHour = Number.isFinite(numericPerHour) ? numericPerHour : 0;

    if (valueElement) {
        valueElement.textContent = formatNumber(cappedValue);
    }

    if (capacityElement) {
        capacityElement.textContent = normalizedCapacity > 0
            ? `/ ${formatNumber(normalizedCapacity)}`
            : '/ —';
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

const applyResourceSnapshot = (resources = {}) => {
    const now = Date.now();
    resourceTicker.states.clear();

    Object.entries(resources).forEach(([key, data]) => {
        const source = data && typeof data === 'object' ? data : {};
        const numericValue = typeof source.value === 'number' ? source.value : Number(source.value ?? 0);
        const numericPerHour = typeof source.perHour === 'number' ? source.perHour : Number(source.perHour ?? 0);
        const numericCapacity = typeof source.capacity === 'number' ? source.capacity : Number(source.capacity ?? 0);
        const normalizedCapacity = Math.max(0, Math.floor(Number.isFinite(numericCapacity) ? numericCapacity : 0));
        const normalizedPerHour = Number.isFinite(numericPerHour) ? numericPerHour : 0;
        const normalizedValue = Math.max(0, Number.isFinite(numericValue) ? numericValue : 0);
        const cappedValue = normalizedCapacity > 0 ? Math.min(normalizedValue, normalizedCapacity) : normalizedValue;

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

const bootstrapResourceTicker = () => {
    const meters = document.querySelectorAll('.resource-meter[data-resource]');
    if (meters.length === 0) {
        return;
    }

    const snapshot = {};
    meters.forEach((meter) => {
        const key = meter.getAttribute('data-resource');
        if (!key) {
            return;
        }

        const valueText = meter.querySelector('[data-resource-value]')?.textContent ?? '0';
        const rateText = meter.querySelector('[data-resource-rate]')?.textContent ?? '0';
        const numericValue = Number(valueText.replace(/[^0-9-]/g, ''));
        const numericRate = Number(rateText.replace(/[^0-9-]/g, ''));
        const capacityAttr = meter.dataset.resourceCapacity ?? meter.getAttribute('data-resource-capacity') ?? '0';
        let numericCapacity = Number(capacityAttr);
        if (!Number.isFinite(numericCapacity)) {
            const capacityText = meter.querySelector('[data-resource-capacity-display]')?.textContent ?? '0';
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

const updateQueueMetrics = (queue, container) => {
    if (!container) {
        return;
    }

    const wrapper = container.closest('[data-queue-wrapper]');
    if (!wrapper) {
        return;
    }

    const countElement = wrapper.querySelector('[data-queue-count]');
    const limitElement = wrapper.querySelector('[data-queue-limit]');
    const rawCount = typeof queue?.count === 'number'
        ? queue.count
        : (Array.isArray(queue?.jobs) ? queue.jobs.length : Number(container.dataset.queueCount ?? 0));
    const normalizedCount = Number.isFinite(rawCount) ? Math.max(0, Math.floor(rawCount)) : 0;
    const rawLimit = typeof queue?.limit === 'number'
        ? queue.limit
        : Number(wrapper.dataset.queueLimit ?? container.dataset.queueLimit ?? 0);
    const normalizedLimit = Number.isFinite(rawLimit) ? Math.max(0, Math.floor(rawLimit)) : 0;

    wrapper.dataset.queueLimit = String(normalizedLimit);
    container.dataset.queueLimit = String(normalizedLimit);

    if (countElement) {
        countElement.textContent = formatNumber(normalizedCount);
    }

    if (limitElement) {
        limitElement.textContent = normalizedLimit > 0 ? formatNumber(normalizedLimit) : '—';
    }
};

const renderQueue = (queue, target) => {
    const container = document.querySelector(`[data-queue="${CSS.escape(target)}"]`);
    if (!container) {
        return;
    }

    updateQueueMetrics(queue, container);

    const emptyMessage = container.dataset.empty || 'Aucune action en cours.';
    if (!queue || !Array.isArray(queue.jobs) || queue.jobs.length === 0) {
        container.innerHTML = `<p class="empty-state">${escapeHtml(emptyMessage)}</p>`;
        return;
    }

    const items = queue.jobs.map((job) => {
        const label = escapeHtml(job.label ?? job.building ?? job.research ?? job.ship ?? '');
        const remaining = formatSeconds(job.remaining ?? 0);
        const endsAt = job.endsAt ? new Date(job.endsAt) : null;
        const timeHtml = endsAt && !Number.isNaN(endsAt.getTime())
            ? `<time datetime="${endsAt.toISOString()}">${endsAt.toLocaleString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            })}</time>`
            : '';

        let detail = '';
        if (target === 'shipyard') {
            const quantity = formatNumber(job.quantity ?? 0);
            detail = `${quantity} unité(s)`;
        } else {
            const level = formatNumber(job.targetLevel ?? 0);
            detail = `Niveau ${level}`;
        }

        return `
            <li class="queue-list__item">
                <div><strong>${label}</strong><span>${detail}</span></div>
                <div class="queue-list__timing">
                    <span>Termine dans ${escapeHtml(remaining)}</span>
                    ${timeHtml}
                </div>
            </li>
        `;
    }).join('');

    container.innerHTML = `<ul class="queue-list">${items}</ul>`;
};

const updateBuildingLevelDisplays = (building) => {
    if (!building || typeof building !== 'object') {
        return;
    }

    const key = building.key;
    if (!key) {
        return;
    }

    const level = Number.isFinite(building.level) ? Number(building.level) : Number(building.level ?? 0);
    const normalizedLevel = Number.isFinite(level) ? Math.max(0, Math.floor(level)) : 0;
    const label = normalizedLevel > 0
        ? `Niveau ${formatNumber(normalizedLevel)}`
        : 'Non construit';
    const modifierClass = normalizedLevel > 0
        ? 'metric-line__value--positive'
        : 'metric-line__value--neutral';

    const elements = document.querySelectorAll(`[data-building-level="${CSS.escape(key)}"]`);
    elements.forEach((element) => {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        element.textContent = label;
        element.classList.remove('metric-line__value--positive', 'metric-line__value--neutral', 'metric-line__value--negative');
        element.classList.add('metric-line__value', modifierClass);
    });
};

const updateBuildingCard = (building) => {
    if (!building || typeof building !== 'object') {
        return;
    }

    const key = building.key;
    if (!key) {
        return;
    }

    const card = document.querySelector(`[data-building-card="${CSS.escape(key)}"]`);
    if (!card) {
        return;
    }

    const canUpgrade = Boolean(building.canUpgrade);
    const requirementsOk = Boolean(building.requirements?.ok);
    const isAffordable = building.affordable === undefined ? true : Boolean(building.affordable);
    const isUnaffordable = requirementsOk && !isAffordable;

    card.classList.toggle('is-locked', !canUpgrade);
    card.classList.toggle('is-unaffordable', isUnaffordable);

    const subtitle = card.querySelector('.panel__subtitle');
    if (subtitle) {
        subtitle.textContent = `Niveau actuel ${formatNumber(Number(building.level ?? 0))}`;
    }

    const sections = card.querySelector('.building-card__sections');
    if (sections) {
        sections.innerHTML = renderBuildingSections(building);
        initRequirementsPanels();
    }

    const button = card.querySelector('form[data-async] button[type="submit"]');
    if (button) {
        button.disabled = !canUpgrade;
        if (canUpgrade) {
            button.textContent = 'Améliorer';
        } else if (isUnaffordable) {
            button.textContent = 'Ressources insuffisantes';
        } else {
            button.textContent = 'Conditions non remplies';
        }
    }

    updateBuildingLevelDisplays(building);
};

const updateResearchCard = (research) => {
    if (!research || typeof research !== 'object') {
        return;
    }

    const key = research.key;
    if (!key) {
        return;
    }

    const card = document.querySelector(`[data-research-card="${CSS.escape(key)}"]`);
    if (!card) {
        return;
    }

    const canResearch = Boolean(research.canResearch);
    const requirementsOk = Boolean(research.requirements?.ok);
    const isAffordable = research.affordable === undefined ? true : Boolean(research.affordable);
    const isUnaffordable = requirementsOk && !isAffordable;

    card.classList.toggle('is-locked', !canResearch);
    card.classList.toggle('is-unaffordable', isUnaffordable);

    const badge = card.querySelector('.panel__badge');
    if (badge) {
        const level = formatNumber(Number(research.level ?? 0));
        const maxLevel = Number(research.maxLevel ?? 0);
        const maxLabel = maxLevel > 0 ? formatNumber(maxLevel) : '∞';
        badge.textContent = `Niveau ${level} / ${maxLabel}`;
    }

    const levelText = card.querySelector('.tech-card__level');
    if (levelText) {
        const level = formatNumber(Number(research.level ?? 0));
        const maxLevel = Number(research.maxLevel ?? 0);
        levelText.textContent = `Niveau actuel ${level}${maxLevel > 0 ? ` / ${formatNumber(maxLevel)}` : ''}`;
    }

    const progressBar = card.querySelector('.progress-bar__value');
    if (progressBar instanceof HTMLElement) {
        const rawProgress = Number(research.progress ?? 0);
        const percentage = clampPercentage(Math.round(rawProgress * 100));
        progressBar.style.width = `${percentage}%`;
    }

    const costSection = card.querySelector('.tech-card__section');
    if (costSection) {
        costSection.innerHTML = `<h3>Prochaine amélioration</h3>${renderCostList(
            research.nextCost ?? {},
            research.nextTime ?? 0,
            research.nextBaseTime ?? null,
        )}`;
    }

    const requirementsHtml = renderResearchRequirements(research.requirements ?? null);
    const existingRequirements = card.querySelector('.tech-card__requirements');
    if (requirementsHtml) {
        if (existingRequirements) {
            existingRequirements.outerHTML = requirementsHtml;
        } else if (costSection) {
            costSection.insertAdjacentHTML('afterend', requirementsHtml);
        }
        initRequirementsPanels();
    } else if (existingRequirements) {
        existingRequirements.remove();
    }

    const button = card.querySelector('form[data-async] button[type="submit"]');
    if (button) {
        button.disabled = !canResearch;
        if (canResearch) {
            button.textContent = 'Lancer la recherche';
        } else if (isUnaffordable) {
            button.textContent = 'Ressources insuffisantes';
        } else {
            button.textContent = 'Pré-requis manquants';
        }
    }
};

const updateShipCard = (ship) => {
    if (!ship || typeof ship !== 'object') {
        return;
    }

    const key = ship.key;
    if (!key) {
        return;
    }

    const card = document.querySelector(`[data-ship-card="${CSS.escape(key)}"]`);
    if (!card) {
        return;
    }

    const canBuild = Boolean(ship.canBuild);
    const requirementsOk = Boolean(ship.requirements?.ok);
    const isAffordable = ship.affordable === undefined ? true : Boolean(ship.affordable);
    const isUnaffordable = requirementsOk && !isAffordable;

    card.classList.toggle('is-locked', !canBuild);
    card.classList.toggle('is-unaffordable', isUnaffordable);

    const requirementsHtml = renderShipRequirements(ship.requirements ?? null);
    const existingRequirements = card.querySelector('.ship-card__requirements');
    const content = card.querySelector('.ship-card__content');
    if (requirementsHtml) {
        if (existingRequirements) {
            existingRequirements.outerHTML = requirementsHtml;
        } else if (content) {
            content.insertAdjacentHTML('beforeend', requirementsHtml);
        }
        initRequirementsPanels();
    } else if (existingRequirements) {
        existingRequirements.remove();
    }

    const quantityInput = card.querySelector('input[name="quantity"]');
    if (quantityInput) {
        quantityInput.disabled = !canBuild;
    }

    const button = card.querySelector('form[data-async] button[type="submit"]');
    if (button) {
        button.disabled = !canBuild;
        if (canBuild) {
            button.textContent = 'Construire';
        } else if (isUnaffordable) {
            button.textContent = 'Ressources insuffisantes';
        } else {
            button.textContent = 'Pré-requis manquants';
        }
    }
};

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

const showFlashMessage = (type, message) => {
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

const submitAsyncForm = async (form) => {
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

        if (!response.ok || data.success === false) {
            showFlashMessage('danger', data.message ?? 'Action impossible.');
            if (data.resources) {
                applyResourceSnapshot(data.resources);
            }
            if (form.dataset.queueTarget && data.queue) {
                renderQueue(data.queue, form.dataset.queueTarget);
            }

            updateOverviewFromResponse(data);

            return;
        }

        showFlashMessage('success', data.message ?? 'Action effectuée.');
        if (data.resources) {
            applyResourceSnapshot(data.resources);
        }
        if (form.dataset.queueTarget && data.queue) {
            renderQueue(data.queue, form.dataset.queueTarget);
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

const initAsyncForms = () => {
    const forms = document.querySelectorAll('form[data-async]');
    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitAsyncForm(form);
        });
    });
};

const initResourcePolling = () => {
    const topbar = document.querySelector('.topbar[data-resource-endpoint]');
    if (!topbar) {
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

const initSidebar = () => {
    const sidebar = document.querySelector('[data-sidebar]');
    if (!sidebar) {
        return;
    }

    const overlay = document.querySelector('[data-sidebar-overlay]');
    const toggles = Array.from(document.querySelectorAll('[data-sidebar-toggle]'));
    const closes = Array.from(document.querySelectorAll('[data-sidebar-close]'));
    const body = document.body;

    const setSidebarState = (isOpen) => {
        sidebar.classList.toggle('sidebar--open', isOpen);
        overlay?.classList.toggle('is-visible', isOpen);
        body.classList.toggle('no-scroll', isOpen);
        toggles.forEach((toggle) => {
            toggle.setAttribute('aria-expanded', String(isOpen));
        });
    };

    const closeSidebar = () => setSidebarState(false);

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            setSidebarState(!sidebar.classList.contains('sidebar--open'));
        });
    });

    closes.forEach((closeControl) => {
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

const initAutoSubmitSelects = () => {
    document.querySelectorAll('select[data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => {
            if (!select.form) {
                return;
            }

            if (typeof select.form.requestSubmit === 'function') {
                select.form.requestSubmit();
            } else {
                select.form.submit();
            }
        });
    });
};

const initRequirementsPanels = () => {
    const panels = document.querySelectorAll('[data-requirements-panel]');
    if (panels.length === 0) {
        return;
    }

    let autoId = 0;
    const ensureId = (element, suffix = '') => {
        if (element.id) {
            return element.id;
        }

        autoId += 1;
        const id = `requirements-panel-${autoId}${suffix}`;
        element.id = id;

        return id;
    };

    panels.forEach((panel) => {
        if (!(panel instanceof HTMLElement)) {
            return;
        }

        if (panel.dataset.requirementsReady === '1') {
            return;
        }

        const summary = panel.querySelector('[data-requirements-summary]');
        const content = panel.querySelector('[data-requirements-content]');
        if (!summary || !content) {
            return;
        }

        const summaryId = ensureId(summary);
        const contentId = content.id || `${summaryId}-content`;
        if (!content.id) {
            content.id = contentId;
        }

        summary.setAttribute('aria-controls', content.id);
        content.setAttribute('aria-labelledby', summaryId);

        const syncState = () => {
            const isOpen = panel.hasAttribute('open');
            summary.setAttribute('aria-expanded', String(isOpen));
            content.setAttribute('aria-hidden', String(!isOpen));
        };

        syncState();
        panel.addEventListener('toggle', syncState);
        panel.dataset.requirementsReady = '1';
    });
};

const initTechTree = () => {
    const dataElement = document.getElementById('tech-tree-data');
    const detailContainer = document.getElementById('tech-tree-detail');
    const nodeLinks = Array.from(document.querySelectorAll('.tech-node-link[data-tech-target]'));
    const linkContext = new Map();

    if (!dataElement || !detailContainer || nodeLinks.length === 0) {
        return;
    }

    let nodes = {};
    try {
        nodes = JSON.parse(dataElement.textContent || '{}');
    } catch (_error) {
        nodes = {};
    }

    if (nodes === null || typeof nodes !== 'object') {
        nodes = {};
    }

    const baseUrl = detailContainer.getAttribute('data-base-url') || '';
    const normalizedBase = baseUrl.replace(/\/$/, '');

    const resolveImage = (path) => {
        if (!path) {
            return '';
        }
        if (/^https?:/i.test(path)) {
            return path;
        }

        const normalizedPath = String(path).replace(/^\//, '');

        return `${normalizedBase}/${normalizedPath}`;
    };

    const renderNode = (nodeId) => {
        const node = nodes[nodeId];
        if (!node) {
            detailContainer.innerHTML = '<p class="tech-detail__placeholder">Aucune donnée à afficher.</p>';
            return;
        }

        let requirementsHtml = '<p class="tech-detail__empty">Aucun prérequis</p>';
        if (Array.isArray(node.requires) && node.requires.length > 0) {
            const items = node.requires.map((requirement) => {
                const statusClass = requirement.met ? 'is-met' : 'is-missing';
                const current = escapeHtml(requirement.current ?? 0);
                const required = escapeHtml(requirement.required ?? 0);
                const label = escapeHtml(requirement.label ?? requirement.key ?? '');

                return `<li class="tech-requirement ${statusClass}">`
                    + `<span class="tech-requirement__name">${label}</span>`
                    + `<span class="tech-requirement__progress">${current} / ${required}</span>`
                    + '</li>';
            }).join('');
            requirementsHtml = `<ul class="tech-requirements">${items}</ul>`;
        }

        const levelInfo = typeof node.level === 'number'
            ? `<p class="tech-detail__level">Niveau actuel : ${escapeHtml(node.level)}</p>`
            : '';
        const description = node.description
            ? `<p class="tech-detail__description">${escapeHtml(node.description)}</p>`
            : '';
        const imagePath = resolveImage(node.image || '');
        const image = imagePath
            ? `<img class="tech-detail__image" src="${escapeHtml(imagePath)}" alt="" loading="lazy" decoding="async">`
            : '';
        const badgeParts = [];
        const groupLabel = typeof node.group === 'string' ? node.group.trim() : '';
        const categoryLabel = typeof node.category === 'string' ? node.category.trim() : '';
        if (groupLabel !== '') {
            badgeParts.push(groupLabel);
        }
        if (categoryLabel !== '' && categoryLabel !== groupLabel) {
            badgeParts.push(categoryLabel);
        }
        const badgeLabel = badgeParts.join(' • ');
        const badge = badgeLabel
            ? `<span class="tech-detail__badge">${escapeHtml(badgeLabel)}</span>`
            : '';

        detailContainer.innerHTML = `
            <article class="tech-detail">
                ${image}
                <div class="tech-detail__header">
                    ${badge}
                    <h2>${escapeHtml(node.label)}</h2>
                    ${levelInfo}
                </div>
                ${description}
                <section class="tech-detail__requirements">
                    <h3>Pré-requis</h3>
                    ${requirementsHtml}
                </section>
            </article>
        `;
    };

    const openDetail = (detail) => {
        if (!detail || typeof detail !== 'object') {
            return;
        }

        const element = detail;
        if (typeof element.tagName !== 'string' || element.tagName.toLowerCase() !== 'details') {
            return;
        }

        if (!element.open) {
            element.open = true;
        }

        const parentContainer = element.parentElement;
        if (!parentContainer) {
            return;
        }

        const parentDetail = parentContainer.closest('details');
        if (parentDetail && parentDetail !== element) {
            openDetail(parentDetail);
        }
    };

    const activateNode = (nodeId) => {
        linkContext.forEach(({ link }, key) => {
            if (link instanceof HTMLElement) {
                link.classList.toggle('is-active', key === nodeId);
            }
        });

        const context = linkContext.get(nodeId);
        if (context) {
            openDetail(context.categoryDetail);
            openDetail(context.groupDetail);
        }

        renderNode(nodeId);
    };

    nodeLinks.forEach((link) => {
        if (!(link instanceof HTMLElement)) {
            return;
        }

        const targetId = link.dataset.techTarget || '';
        if (targetId === '') {
            return;
        }

        const categoryDetail = link.closest('details[data-tech-category]');
        const groupDetail = link.closest('details[data-tech-group]');
        linkContext.set(targetId, {
            link,
            categoryDetail,
            groupDetail,
        });

        link.classList.toggle('tech-node-link--ready', link.dataset.techReady === '1');
        link.addEventListener('click', () => {
            const target = link.dataset.techTarget || '';
            if (target !== '') {
                activateNode(target);
            }
        });
    });

};

const ready = () => {
    initSidebar();
    initAutoSubmitSelects();
    initAsyncForms();
    bootstrapResourceTicker();
    initResourcePolling();
    initRequirementsPanels();
    initTechTree();
};

document.addEventListener('DOMContentLoaded', ready, { once: true });

export {
    applyResourceSnapshot,
    renderQueue,
    updateBuildingCard,
    updateResearchCard,
    updateShipCard,
};
