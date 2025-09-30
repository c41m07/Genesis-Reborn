export const initRequirementsPanels = () => {
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

export default {
    initRequirementsPanels,
};
