import {
    createIcon,
    escapeHtml,
    formatNumber,
    getResourceLabel,
    metricValueClass,
    normalizeClassNames,
} from './format.js';
import {formatDuration} from './time.js';
import {initRequirementsPanels} from './requirements.js';

let requirementsPanelSequence = 0;

const renderCostList = (cost = {}, time = 0, baseTime = null, missingResources = {}) => {
    const items = [];
    const normalizedMissing = {};

    if (missingResources && typeof missingResources === 'object') {
        Object.entries(missingResources).forEach(([resource, value]) => {
            const numeric = Number(value);
            if (Number.isFinite(numeric) && numeric > 0) {
                normalizedMissing[String(resource)] = numeric;
            }
        });
    }

    if (cost && typeof cost === 'object') {
        Object.entries(cost).forEach(([resource, amount]) => {
            const key = String(resource);
            const classes = ['resource-list__item'];
            if ((normalizedMissing[key] ?? 0) > 0) {
                classes.push('resource-list__item--missing');
            }
            items.push(`
        <li class="${classes.join(' ')}" data-resource="${escapeHtml(key)}">${createIcon(key)}<span>${formatNumber(Number(amount) || 0)}</span></li>
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

    const timeLabel = formatDuration(normalizedTime);
    const baseLabel =
        normalizedBaseTime !== normalizedTime
            ? ` <small>(base ${escapeHtml(formatDuration(normalizedBaseTime))})</small>`
            : '';

    items.push(`
    <li class="resource-list__item resource-list__item--time" data-resource="time">${createIcon('time')}<span>${escapeHtml(timeLabel)}${baseLabel}</span></li>
  `);

    return `<ul class="resource-list">${items.join('')}</ul>`;
};

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

    const entries = items
        .map((missing) => {
            const label = escapeHtml(String(missing.label ?? missing.key ?? ''));
            const current = formatNumber(Number(missing.current ?? 0));
            const required = formatNumber(Number(missing.level ?? missing.required ?? 0));

            return `
      <li class="requirements-panel__item">
        <span class="requirements-panel__name building-card__requirement-name">${label}</span>
        <span class="requirements-panel__progress building-card__requirement-progress">(${current}/${required})</span>
      </li>
    `;
        })
        .join('');

    if (!entries) {
        return '';
    }

    requirementsPanelSequence += 1;
    const panelId = `requirements-panel-dynamic-${requirementsPanelSequence}`;
    const contentId = `${panelId}-content`;

    const panelClasses = normalizeClassNames('requirements-panel', panelClass);
    const summaryClasses = normalizeClassNames('requirements-panel__summary', summaryClass);
    const contentClasses = normalizeClassNames('requirements-panel__content', contentClass);
    const listClasses = normalizeClassNames(
        'requirements-panel__list',
        'building-card__requirements',
        listClass
    );
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
    const currentEntries =
        storage && typeof storage === 'object' && storage.current && typeof storage.current === 'object'
            ? Object.entries(storage.current)
            : [];
    const nextEntries =
        storage && typeof storage === 'object' && storage.next && typeof storage.next === 'object'
            ? Object.entries(storage.next)
            : [];

    if (currentEntries.length === 0 && nextEntries.length === 0) {
        return '';
    }

    const renderList = (entries, className) =>
        entries
            .map(([resource, value]) => {
                const label = escapeHtml(getResourceLabel(resource));

                return `
      <li class="metric-line">
        <span class="metric-line__label">${label}</span>
        <span class="${className}">${formatNumber(Number(value) || 0)}</span>
      </li>
    `;
            })
            .join('');

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
        const unitSuffix =
            resource === 'energy' ? ' énergie/h' : ` ${resourceLabel.toLocaleLowerCase('fr-FR')}/h`;

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
      ${renderCostList(
        building.cost ?? {},
        building.time ?? 0,
        building.baseTime ?? null,
        building.missingResources ?? {}
    )}
    </div>
  `;

    const effectsParts = [];
    const production = building.production ?? {};
    const resourceKey = production.resource ?? '';
    if (resourceKey && resourceKey !== 'storage') {
        const resourceLabel = getResourceLabel(resourceKey);
        const unitSuffix =
            resourceKey === 'energy' ? ' énergie/h' : ` ${resourceLabel.toLocaleLowerCase('fr-FR')}/h`;
        const currentValue = Number(production.current ?? 0);
        const nextValue = Number(production.next ?? 0);
        const currentDisplay =
            currentValue > 0 ? `+${formatNumber(currentValue)}` : formatNumber(currentValue);
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
    const shouldRenderRequirements =
        requirements &&
        requirements.ok === false &&
        Array.isArray(requirements.missing) &&
        requirements.missing.length > 0;

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
    if (
        !requirements ||
        requirements.ok !== false ||
        !Array.isArray(requirements.missing) ||
        requirements.missing.length === 0
    ) {
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
    if (
        !requirements ||
        requirements.ok !== false ||
        !Array.isArray(requirements.missing) ||
        requirements.missing.length === 0
    ) {
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

const updateBuildingLevelDisplays = (building) => {
    if (!building || typeof building !== 'object') {
        return;
    }

    const key = building.key;
    if (!key) {
        return;
    }

    const level = Number.isFinite(building.level)
        ? Number(building.level)
        : Number(building.level ?? 0);
    const normalizedLevel = Number.isFinite(level) ? Math.max(0, Math.floor(level)) : 0;
    const label = normalizedLevel > 0 ? `Niveau ${formatNumber(normalizedLevel)}` : 'Non construit';
    const modifierClass =
        normalizedLevel > 0 ? 'metric-line__value--positive' : 'metric-line__value--neutral';

    const elements = document.querySelectorAll(`[data-building-level="${CSS.escape(key)}"]`);
    elements.forEach((element) => {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        element.textContent = label;
        element.classList.remove(
            'metric-line__value--positive',
            'metric-line__value--neutral',
            'metric-line__value--negative'
        );
        element.classList.add('metric-line__value', modifierClass);
    });
};

export const updateBuildingCard = (building) => {
    if (!building || typeof building !== 'object') {
        return;
    }

    const key = building.key;
    if (!key) {
        return;
    }

    const card = document.querySelector(`[data-building-card="${CSS.escape(key)}"]`);
    if (!(card instanceof HTMLElement)) {
        return;
    }

    updateBuildingLevelDisplays(building);

    const subtitle = card.querySelector('.panel__subtitle');
    const level = Number.isFinite(building.level)
        ? Number(building.level)
        : Number(building.level ?? 0);
    const normalizedLevel = Number.isFinite(level) ? Math.max(0, Math.floor(level)) : 0;
    if (subtitle) {
        subtitle.textContent =
            normalizedLevel > 0 ? `Niveau actuel ${formatNumber(normalizedLevel)}` : 'Non construit';
    }

    const sectionContainer = card.querySelector('.building-card__sections');
    if (sectionContainer) {
        sectionContainer.innerHTML = renderBuildingSections(building);
        initRequirementsPanels();
    }

    const form = card.querySelector('form[data-async]');
    const button = form?.querySelector('button[type="submit"]');
    const canUpgrade = Boolean(building.canUpgrade);
    const affordable = Boolean(building.affordable);
    const requirements = building.requirements ?? {ok: true};

    if (button) {
        button.disabled = !canUpgrade;
        if (canUpgrade) {
            button.textContent = 'Améliorer';
            button.classList.remove('button--resource-warning');
        } else if (requirements.ok && !affordable) {
            button.textContent = 'Ressources insuffisantes';
            button.classList.add('button--resource-warning');
        } else {
            button.textContent = 'Conditions non remplies';
            button.classList.remove('button--resource-warning');
        }
    }

    card.classList.toggle('is-locked', !requirements.ok);

    if (!requirements.ok) {
        card.classList.add('is-locked');
    }
};

export const updateResearchCard = (research) => {
    if (!research || typeof research !== 'object') {
        return;
    }

    const key = research.key;
    if (!key) {
        return;
    }

    const card = document.querySelector(`[data-research-card="${CSS.escape(key)}"]`);
    if (!(card instanceof HTMLElement)) {
        return;
    }

    const subtitle = card.querySelector('.panel__subtitle');
    const level = Number.isFinite(research.level)
        ? Number(research.level)
        : Number(research.level ?? 0);
    const normalizedLevel = Number.isFinite(level) ? Math.max(0, Math.floor(level)) : 0;
    if (subtitle) {
        const maxLabel =
            typeof research.maxLevel === 'number' && Number.isFinite(research.maxLevel)
                ? ` / ${formatNumber(Math.max(0, research.maxLevel))}`
                : '';
        subtitle.textContent =
            normalizedLevel > 0
                ? `Niveau actuel ${formatNumber(normalizedLevel)}${maxLabel}`
                : 'Non recherché';
    }

    const progressBar = card.querySelector('.tech-card__progress');
    if (progressBar instanceof HTMLElement) {
        const progress = Math.max(0, Math.min(1, Number(research.progress ?? 0)));
        progressBar.style.setProperty('--progress', `${Math.round(progress * 100)}%`);
    }

    const costSection = card.querySelector('.tech-card__costs');
    if (costSection) {
        costSection.innerHTML = renderCostList(
            research.nextCost ?? {},
            research.nextTime ?? 0,
            research.nextBaseTime ?? null,
            research.missingResources ?? {}
        );
    }

    const requirementsSection = card.querySelector('.tech-card__requirements');
    if (requirementsSection) {
        requirementsSection.innerHTML = renderResearchRequirements(research.requirements);
    } else {
        card.insertAdjacentHTML('beforeend', renderResearchRequirements(research.requirements));
    }
    initRequirementsPanels();

    const form = card.querySelector('form[data-async]');
    const button = form?.querySelector('button[type="submit"]');
    const canResearch = Boolean(research.canResearch);
    const affordable = Boolean(research.affordable);
    const requirements = research.requirements ?? {ok: true};

    if (button) {
        button.disabled = !canResearch;
        if (canResearch) {
            button.textContent = 'Lancer la recherche';
            button.classList.remove('button--resource-warning');
        } else if (requirements.ok && !affordable) {
            button.textContent = 'Ressources insuffisantes';
            button.classList.add('button--resource-warning');
        } else {
            button.textContent = 'Pré-requis manquants';
            button.classList.remove('button--resource-warning');
        }
    }
};

export const updateShipCard = (ship) => {
    if (!ship || typeof ship !== 'object') {
        return;
    }

    const key = ship.key;
    if (!key) {
        return;
    }

    const card = document.querySelector(`[data-ship-card="${CSS.escape(key)}"]`);
    if (!(card instanceof HTMLElement)) {
        return;
    }

    const form = card.querySelector('form[data-async]');
    const quantityInput = form?.querySelector('input[name="quantity"]');
    const button = form?.querySelector('button[type="submit"]');
    const canBuild = Boolean(ship.canBuild);
    const affordable = Boolean(ship.affordable);
    const requirements = ship.requirements ?? {ok: true};

    card.classList.toggle('is-locked', !requirements.ok);

    if (quantityInput instanceof HTMLInputElement) {
        quantityInput.disabled = !canBuild;
    }

    if (button) {
        button.disabled = !canBuild;
        if (canBuild) {
            button.textContent = 'Construire';
            button.classList.remove('button--resource-warning');
        } else if (requirements.ok && !affordable) {
            button.textContent = 'Ressources insuffisantes';
            button.classList.add('button--resource-warning');
        } else {
            button.textContent = 'Pré-requis manquants';
            button.classList.remove('button--resource-warning');
        }
    }

    const costSection = card.querySelector('.ship-card__section--costs');
    if (costSection) {
        costSection.innerHTML = renderCostList(
            ship.cost ?? {},
            ship.time ?? 0,
            ship.baseTime ?? null,
            ship.missingResources ?? {}
        );
    }

    const requirementsSection = card.querySelector('.ship-card__requirements');
    if (requirementsSection) {
        requirementsSection.innerHTML = renderShipRequirements(ship.requirements);
    } else {
        card.insertAdjacentHTML('beforeend', renderShipRequirements(ship.requirements));
    }
    initRequirementsPanels();
};

export default {
    updateBuildingCard,
    updateResearchCard,
    updateShipCard,
    renderCostList,
    renderRequirementsPanel,
};
