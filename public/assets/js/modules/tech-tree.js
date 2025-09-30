import { escapeHtml } from './format.js';

export const initTechTree = () => {
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
  const planetId = (detailContainer.getAttribute('data-planet-id') || '').trim();
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
      detailContainer.innerHTML =
        '<p class="tech-detail__placeholder">Aucune donnée à afficher.</p>';
      return;
    }

    let requirementsHtml = '<p class="tech-detail__empty">Aucun prérequis</p>';
    if (Array.isArray(node.requires) && node.requires.length > 0) {
      const items = node.requires
        .map((requirement) => {
          const statusClass = requirement.met ? 'is-met' : 'is-missing';
          const current = escapeHtml(requirement.current ?? 0);
          const required = escapeHtml(requirement.required ?? 0);
          const label = escapeHtml(requirement.label ?? requirement.key ?? '');
            const key = String(requirement.key ?? '').trim();
            let requirementUrl = '';

            if (key !== '' && planetId !== '') {
                if (requirement.type === 'building') {
                    requirementUrl =
                        `${normalizedBase}/colony?planet=${encodeURIComponent(planetId)}#building-${encodeURIComponent(key)}`;
                } else if (requirement.type === 'research') {
                    requirementUrl =
                        `${normalizedBase}/research?planet=${encodeURIComponent(planetId)}#research-${encodeURIComponent(key)}`;
                }
            }

            const labelContent = requirementUrl
                ? `<a class="tech-requirement__name tech-requirement__link" href="${escapeHtml(requirementUrl)}">${label}</a>`
                : `<span class="tech-requirement__name">${label}</span>`;

          return (
            `<li class="tech-requirement ${statusClass}">` +
            labelContent +
                `<span class="tech-requirement__progress">${current} / ${required}</span>` +
            '</li>'
          );
        })
        .join('');
      requirementsHtml = `<ul class="tech-requirements">${items}</ul>`;
    }

    const levelInfo =
      typeof node.level === 'number'
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

export default {
  initTechTree,
};
