import countdown from './modules/countdown.js';
import { initSidebar } from './modules/sidebar.js';
import { initAutoSubmitSelects } from './modules/forms.js';
import { initAsyncForms } from './modules/async-forms.js';
import {
  applyResourceSnapshot,
  bootstrapResourceTicker,
  initResourcePolling,
} from './modules/resources.js';
import { initRequirementsPanels } from './modules/requirements.js';
import { initTechTree } from './modules/tech-tree.js';
import { renderQueue } from './modules/queue.js';
import { updateBuildingCard, updateResearchCard, updateShipCard } from './modules/cards.js';
import { initFleetPlanner } from './modules/fleet-planner.js';

const ready = () => {
  initSidebar();
  initAutoSubmitSelects();
  initAsyncForms();
  bootstrapResourceTicker();
  initResourcePolling();
  initRequirementsPanels();
  initTechTree();
  initFleetPlanner();
};

document.addEventListener('DOMContentLoaded', ready, { once: true });

if (typeof window !== 'undefined') {
  window.QueueCountdown = window.QueueCountdown || {};
  Object.assign(window.QueueCountdown, {
    init: countdown.init,
    destroy: countdown.destroy,
    refresh: countdown.refresh,
  });
}

export {
  applyResourceSnapshot,
  renderQueue,
  updateBuildingCard,
  updateResearchCard,
  updateShipCard,
};
