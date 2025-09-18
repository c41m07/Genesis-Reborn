const ready = () => {
    const sidebar = document.querySelector('[data-sidebar]');
    if (!sidebar) {
        return;
    }

    const overlay = document.querySelector('[data-sidebar-overlay]');
    const toggles = document.querySelectorAll('[data-sidebar-toggle]');
    const closes = document.querySelectorAll('[data-sidebar-close]');
    const body = document.body;

    const setSidebarState = (isOpen) => {
        sidebar.classList.toggle('sidebar--open', isOpen);
        if (overlay) {
            overlay.classList.toggle('is-visible', isOpen);
        }
        body.classList.toggle('no-scroll', isOpen);
        toggles.forEach((toggle) => {
            toggle.setAttribute('aria-expanded', String(isOpen));
        });
    };

    const closeSidebar = () => setSidebarState(false);

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const isOpen = sidebar.classList.contains('sidebar--open');
            setSidebarState(!isOpen);
        });
    });

    closes.forEach((closeControl) => {
        closeControl.addEventListener('click', () => {
            closeSidebar();
        });
    });

    if (overlay) {
        overlay.addEventListener('click', () => {
            closeSidebar();
        });
    }

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
    if (typeof desktopMatch.addEventListener === 'function') {
        desktopMatch.addEventListener('change', handleDesktopChange);
    } else if (typeof desktopMatch.addListener === 'function') {
        desktopMatch.addListener(handleDesktopChange);
    }

    document.querySelectorAll('select[data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => {
            if (select.form) {
                select.form.submit();
            }
        });
    });

    const techTreeDataElement = document.getElementById('tech-tree-data');
    if (techTreeDataElement) {
        let nodes = {};
        try {
            nodes = JSON.parse(techTreeDataElement.textContent || '{}');
        } catch (error) {
            nodes = {};
        }

        const detailContainer = document.getElementById('tech-tree-detail');
        const nodeLinks = document.querySelectorAll('.tech-node-link[data-tech-target]');
        const initial = detailContainer ? detailContainer.getAttribute('data-initial') : null;
        const baseUrl = detailContainer ? (detailContainer.getAttribute('data-base-url') || '') : '';

        const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        })[char]);

        const resolveImage = (path) => {
            if (!path) {
                return '';
            }
            if (/^https?:/i.test(path)) {
                return path;
            }

            const normalizedBase = baseUrl.replace(/\/$/, '');
            const normalizedPath = String(path).replace(/^\//, '');

            return `${normalizedBase}/${normalizedPath}`;
        };

        const renderNode = (nodeId) => {
            if (!detailContainer) {
                return;
            }

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
                ? `<img class="tech-detail__image" src="${escapeHtml(imagePath)}" alt="">`
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
            link.addEventListener('click', () => {
                activateNode(link.dataset.techTarget || '');
            });
        });

        if (initial && nodes[initial]) {
            activateNode(initial);
        } else if (nodeLinks.length > 0) {
            activateNode(nodeLinks[0].dataset.techTarget || '');
        }
    }
};

document.addEventListener('DOMContentLoaded', ready);
