const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char] || char));

const numberFormatter = new Intl.NumberFormat('fr-FR');

const formatNumber = (value) => {
    const numericValue = typeof value === 'number' ? value : Number(value ?? 0);

    return numberFormatter.format(Number.isFinite(numericValue) ? numericValue : 0);
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

const updateResourceMeters = (resources = {}) => {
    Object.entries(resources).forEach(([key, data]) => {
        const meter = document.querySelector(`.resource-meter[data-resource="${CSS.escape(key)}"]`);
        if (!meter) {
            return;
        }

        const valueElement = meter.querySelector('[data-resource-value]');
        const rateElement = meter.querySelector('[data-resource-rate]');
        const value = data && typeof data === 'object' ? data.value ?? 0 : 0;
        const perHour = data && typeof data === 'object' ? data.perHour ?? 0 : 0;

        if (valueElement) {
            valueElement.textContent = formatNumber(value);
        }

        if (rateElement) {
            const ratePrefix = key !== 'energy' && perHour > 0 ? '+' : '';
            rateElement.textContent = `${ratePrefix}${formatNumber(perHour)}/h`;
            rateElement.classList.toggle('is-positive', perHour >= 0);
            rateElement.classList.toggle('is-negative', perHour < 0);
        }
    });
};

const renderQueue = (queue, target) => {
    const container = document.querySelector(`[data-queue="${CSS.escape(target)}"]`);
    if (!container) {
        return;
    }

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
                updateResourceMeters(data.resources);
            }
            if (form.dataset.queueTarget && data.queue) {
                renderQueue(data.queue, form.dataset.queueTarget);
            }

            return;
        }

        showFlashMessage('success', data.message ?? 'Action effectuée.');
        if (data.resources) {
            updateResourceMeters(data.resources);
        }
        if (form.dataset.queueTarget && data.queue) {
            renderQueue(data.queue, form.dataset.queueTarget);
        }
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
                updateResourceMeters(data.resources);
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

const initTechTree = () => {
    const dataElement = document.getElementById('tech-tree-data');
    const detailContainer = document.getElementById('tech-tree-detail');
    const nodeLinks = Array.from(document.querySelectorAll('.tech-node-link[data-tech-target]'));

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

    const initial = detailContainer.getAttribute('data-initial') || '';
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

        detailContainer.innerHTML = `
            <article class="tech-detail">
                ${image}
                <div class="tech-detail__header">
                    <span class="tech-detail__badge">${escapeHtml(node.category)}</span>
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

    const activateNode = (nodeId) => {
        nodeLinks.forEach((link) => {
            link.classList.toggle('is-active', link.dataset.techTarget === nodeId);
        });
        renderNode(nodeId);
    };

    nodeLinks.forEach((link) => {
        link.classList.toggle('tech-node-link--ready', link.dataset.techReady === '1');
        link.addEventListener('click', () => {
            const target = link.dataset.techTarget || '';
            if (target !== '') {
                activateNode(target);
            }
        });
    });

    if (initial && nodes[initial]) {
        activateNode(initial);
        return;
    }

    const firstTarget = nodeLinks[0]?.dataset.techTarget;
    if (firstTarget) {
        activateNode(firstTarget);
    }
};

const ready = () => {
    initSidebar();
    initAutoSubmitSelects();
    initAsyncForms();
    initResourcePolling();
    initTechTree();
};

document.addEventListener('DOMContentLoaded', ready, { once: true });
