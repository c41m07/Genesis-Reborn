import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!DOCTYPE html><body></body>', { url: 'https://example.com/' });

globalThis.window = dom.window;
globalThis.document = dom.window.document;
globalThis.HTMLElement = dom.window.HTMLElement;
globalThis.Node = dom.window.Node;
globalThis.CSS = {
    escape(value) {
        return String(value);
    },
};

const spriteUrl = new URL('../../svg/sprite.svg', import.meta.url).toString();

const {
    updateBuildingCard,
    updateResearchCard,
    updateShipCard,
} = await import('../app.js');

beforeEach(() => {
    document.body.innerHTML = '';
});

test('updateBuildingCard refreshes level, costs and requirements', () => {
    document.body.innerHTML = `
        <p class="metric-line">
            <span class="metric-line__label">Statut</span>
            <span class="metric-line__value metric-line__value--neutral" data-building-level="metal_mine">Non construit</span>
        </p>
        <article class="panel building-card" data-building-card="metal_mine">
            <header class="panel__header">
                <div class="panel__heading">
                    <h2>Mine de métal</h2>
                    <p class="panel__subtitle">Niveau actuel 1</p>
                </div>
            </header>
            <div class="panel__body">
                <div class="building-card__sections">
                    <div class="building-card__block">
                        <h3>Prochaine amélioration</h3>
                        <ul class="resource-list">
                            <li><svg class="icon icon-sm" aria-hidden="true"><use href="${spriteUrl}#icon-metal"></use></svg><span>60</span></li>
                            <li><svg class="icon icon-sm" aria-hidden="true"><use href="${spriteUrl}#icon-crystal"></use></svg><span>15</span></li>
                            <li><svg class="icon icon-sm" aria-hidden="true"><use href="${spriteUrl}#icon-time"></use></svg><span>10 s</span></li>
                        </ul>
                    </div>
                    <details class="building-card__details" data-building-effects>
                        <summary class="building-card__details-summary">
                            <span class="building-card__details-title">Effets</span>
                            <span class="building-card__details-chevron" aria-hidden="true"></span>
                        </summary>
                        <div class="building-card__details-content"></div>
                    </details>
                </div>
            </div>
            <footer class="panel__footer">
                <form data-async="queue">
                    <button type="submit" class="button button--primary">Améliorer</button>
                </form>
            </footer>
        </article>
    `;

    updateBuildingCard({
        key: 'metal_mine',
        level: 3,
        canUpgrade: false,
        cost: { metal: 150, crystal: 75 },
        time: 120,
        baseTime: 180,
        production: { resource: 'metal', current: 30, next: 40 },
        consumption: { energy: { current: 5, next: 7 } },
        storage: { current: {}, next: {}, delta: {} },
        requirements: {
            ok: false,
            missing: [
                { label: 'Laboratoire de recherche', current: 0, level: 2 },
            ],
        },
    });

    const card = document.querySelector('[data-building-card="metal_mine"]');
    assert(card?.classList.contains('is-locked'));
    assert(!card?.classList.contains('is-unaffordable'));

    const subtitle = card?.querySelector('.panel__subtitle');
    assert.equal(subtitle?.textContent, 'Niveau actuel 3');

    const costList = card?.querySelector('.building-card__sections .resource-list');
    assert.ok(costList?.textContent?.includes('150'));
    assert.ok(costList?.textContent?.includes('75'));
    assert.ok(costList?.textContent?.includes('2 min'));
    assert.ok(costList?.innerHTML?.includes('(base 3 min)'));

    const effectsDetails = card?.querySelector('[data-building-effects]');
    assert(effectsDetails instanceof HTMLElement);
    assert.equal(effectsDetails?.hasAttribute('open'), false);
    const effectsSummary = effectsDetails?.querySelector('.building-card__details-summary');
    assert.ok(effectsSummary?.textContent?.includes('Effets'));
    const effectsContent = effectsDetails?.querySelector('.building-card__details-content');
    assert.ok(effectsContent?.textContent?.includes('Production actuelle'));

    const requirements = card?.querySelector('.building-card__requirements');
    assert.ok(requirements);
    assert.ok(requirements?.textContent?.includes('Laboratoire de recherche'));

    const button = card?.querySelector('button[type="submit"]');
    assert.equal(button?.disabled, true);
    assert.equal(button?.textContent, 'Conditions non remplies');

    updateBuildingCard({
        key: 'metal_mine',
        level: 3,
        canUpgrade: false,
        affordable: false,
        cost: { metal: 150, crystal: 75 },
        time: 120,
        baseTime: 180,
        production: { resource: 'metal', current: 30, next: 40 },
        consumption: {},
        storage: { current: {}, next: {}, delta: {} },
        requirements: { ok: true, missing: [] },
    });

    assert(card?.classList.contains('is-unaffordable'));
    const effectsAfter = card?.querySelector('[data-building-effects]');
    assert(effectsAfter instanceof HTMLElement);
    assert.equal(effectsAfter?.hasAttribute('open'), false);
    assert.equal(button?.disabled, true);
    assert.equal(button?.textContent, 'Ressources insuffisantes');

    const levelDisplay = document.querySelector('[data-building-level="metal_mine"]');
    assert.equal(levelDisplay?.textContent, 'Niveau 3');
    assert(levelDisplay?.classList.contains('metric-line__value--positive'));
    assert(!levelDisplay?.classList.contains('metric-line__value--neutral'));
});

test('updateResearchCard syncs progress, costs and availability', () => {
    document.body.innerHTML = `
        <article class="panel tech-card is-locked" data-research-card="energy_tech">
            <header class="panel__header">
                <div class="panel__heading">
                    <h2>Technologie énergétique</h2>
                    <span class="panel__badge">Niveau 0 / ∞</span>
                </div>
            </header>
            <div class="panel__body">
                <div class="tech-card__progress">
                    <div class="progress-bar"><span class="progress-bar__value" style="width: 0%"></span></div>
                    <p class="tech-card__level">Niveau actuel 0</p>
                </div>
                <div class="tech-card__section">
                    <h3>Prochaine amélioration</h3>
                    <ul class="resource-list">
                        <li><svg class="icon icon-sm" aria-hidden="true"><use href="${spriteUrl}#icon-metal"></use></svg><span>0</span></li>
                    </ul>
                </div>
            </div>
            <footer class="panel__footer">
                <form data-async="queue">
                    <button type="submit" class="button button--primary" disabled>Pré-requis manquants</button>
                </form>
            </footer>
        </article>
    `;

    updateResearchCard({
        key: 'energy_tech',
        level: 2,
        maxLevel: 5,
        progress: 0.4,
        nextCost: { metal: 100, crystal: 50 },
        nextTime: 180,
        nextBaseTime: 240,
        requirements: { ok: true, missing: [] },
        canResearch: true,
        affordable: true,
    });

    const card = document.querySelector('[data-research-card="energy_tech"]');
    assert(card && !card.classList.contains('is-locked'));
    assert(!card?.classList.contains('is-unaffordable'));

    const badge = card?.querySelector('.panel__badge');
    assert.equal(badge?.textContent, 'Niveau 2 / 5');

    const level = card?.querySelector('.tech-card__level');
    assert.equal(level?.textContent, 'Niveau actuel 2 / 5');

    const progress = card?.querySelector('.progress-bar__value');
    const progressWidth = progress instanceof HTMLElement ? progress.style.width : null;
    assert.equal(progressWidth, '40%');

    const costList = card?.querySelector('.tech-card__section .resource-list');
    assert.ok(costList?.textContent?.includes('100'));
    assert.ok(costList?.textContent?.includes('50'));
    assert.ok(costList?.textContent?.includes('3 min'));
    assert.ok(costList?.innerHTML?.includes('(base 4 min)'));

    const requirements = card?.querySelector('.tech-card__requirements');
    assert.equal(requirements, null);

    const button = card?.querySelector('button[type="submit"]');
    assert.equal(button?.disabled, false);
    assert.equal(button?.textContent, 'Lancer la recherche');

    updateResearchCard({
        key: 'energy_tech',
        level: 2,
        maxLevel: 5,
        progress: 0.4,
        nextCost: { metal: 100, crystal: 50 },
        nextTime: 180,
        nextBaseTime: 240,
        requirements: { ok: true, missing: [] },
        canResearch: false,
        affordable: false,
    });

    assert(card?.classList.contains('is-unaffordable'));
    assert.equal(button?.disabled, true);
    assert.equal(button?.textContent, 'Ressources insuffisantes');

    updateResearchCard({
        key: 'energy_tech',
        level: 2,
        maxLevel: 5,
        progress: 0.4,
        nextCost: { metal: 100, crystal: 50 },
        nextTime: 180,
        nextBaseTime: 240,
        requirements: {
            ok: false,
            missing: [
                { label: 'Laboratoire', current: 1, level: 3 },
            ],
        },
        canResearch: false,
        affordable: true,
    });

    const requirementsAfter = card?.querySelector('.tech-card__requirements');
    assert.ok(requirementsAfter);
    assert.ok(requirementsAfter?.textContent?.includes('Laboratoire'));
    assert.equal(button?.disabled, true);
    assert.equal(button?.textContent, 'Pré-requis manquants');
    assert(!card?.classList.contains('is-unaffordable'));
});

test('updateShipCard toggles availability and requirements', () => {
    document.body.innerHTML = `
        <article class="panel ship-card is-locked" data-ship-card="fighter">
            <div class="panel__body ship-card__body">
                <div class="ship-card__content"></div>
            </div>
            <footer class="panel__footer ship-card__footer">
                <form data-async="queue">
                    <label class="ship-card__quantity"><span>Quantité</span><input type="number" name="quantity" value="1" disabled></label>
                    <button type="submit" class="button button--primary" disabled>Pré-requis manquants</button>
                </form>
            </footer>
        </article>
    `;

    updateShipCard({
        key: 'fighter',
        canBuild: true,
        requirements: { ok: true, missing: [] },
        affordable: true,
    });

    const card = document.querySelector('[data-ship-card="fighter"]');
    assert(card && !card.classList.contains('is-locked'));
    assert(!card?.classList.contains('is-unaffordable'));

    const input = card?.querySelector('input[name="quantity"]');
    assert.equal(input?.disabled, false);

    const button = card?.querySelector('button[type="submit"]');
    assert.equal(button?.disabled, false);
    assert.equal(button?.textContent, 'Construire');

    updateShipCard({
        key: 'fighter',
        canBuild: false,
        requirements: { ok: true, missing: [] },
        affordable: false,
    });

    assert(card?.classList.contains('is-unaffordable'));
    assert.equal(input?.disabled, true);
    assert.equal(button?.disabled, true);
    assert.equal(button?.textContent, 'Ressources insuffisantes');

    updateShipCard({
        key: 'fighter',
        canBuild: false,
        requirements: {
            ok: false,
            missing: [
                { label: 'Recherche X', current: 0, level: 1 },
            ],
        },
        affordable: true,
    });

    assert(card?.classList.contains('is-locked'));
    assert(!card?.classList.contains('is-unaffordable'));
    assert.equal(input?.disabled, true);
    assert.equal(button?.disabled, true);
    assert.equal(button?.textContent, 'Pré-requis manquants');

    const requirements = card?.querySelector('.ship-card__requirements');
    assert.ok(requirements);
    assert.ok(requirements?.textContent?.includes('Recherche X'));
});
