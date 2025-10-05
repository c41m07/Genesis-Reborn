import { applyResourceSnapshot } from './resources.js';

const FLEET_STATUS_LABELS = {
  idle: 'Flotte opérationnelle',
  outbound: 'Mission en cours',
  returning: 'Retour en cours',
  holding: 'En attente',
  completed: 'Mission terminée',
  failed: 'Mission échouée',
};

const ACTIVE_FLEET_STATUSES = new Set(['outbound', 'returning', 'holding']);

const formatNumber = (value) => new Intl.NumberFormat('fr-FR').format(value);

const formatArrival = (value) => {
  if (!value) {
    return null;
  }

  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(date);
};

const renderPlan = (container, plan) => {
  if (!container) {
    return;
  }

  if (!plan) {
    container.innerHTML =
      '<p class="fleet-mission__placeholder">Simulez un trajet pour estimer la durée, la consommation et l’espace cargo requis. Vos résultats apparaîtront ici.</p>';

    return;
  }

  const arrival = formatArrival(plan.arrival_time || plan.arrivalAt || null);
  const cargoCapacity = formatNumber(Number(plan.cargo_capacity ?? 0));
  const cargoUsed = formatNumber(Number(plan.cargo_used ?? 0));
  const remainingCargo = formatNumber(Number(plan.remaining_cargo ?? 0));

  container.innerHTML = `
    <div class="fleet-mission__result-card">
      <h3 class="fleet-mission__result-title">Résultat de la simulation</h3>
      <ul class="metric-list fleet-mission__metrics">
        <li><span>Distance</span><strong>${formatNumber(Number(plan.distance ?? 0))} u</strong></li>
        <li><span>Vitesse effective</span><strong>${formatNumber(Number(plan.speed ?? 0))} u/h</strong></li>
        <li><span>Durée</span><strong>${formatDuration(Number(plan.travel_time ?? 0))}</strong></li>
        ${arrival ? `<li><span>Arrivée estimée</span><strong>${escapeHtml(arrival)}</strong></li>` : ''}
        <li><span>Hydrogène requis</span><strong>${formatNumber(Number(plan.fuel ?? 0))}</strong></li>
      </ul>
      <div class="fleet-mission__cargo">
        <h4>Gestion du cargo</h4>
        <ul class="metric-list fleet-mission__cargo-list">
          <li><span>Capacité totale</span><strong>${cargoCapacity}</strong></li>
          <li><span>Utilisation actuelle</span><strong>${cargoUsed}</strong></li>
          <li><span>Soute restante</span><strong>${remainingCargo}</strong></li>
        </ul>
      </div>
    </div>
  `;
};

const formatDuration = (seconds) => {
  const value = Number(seconds) || 0;
  const hours = Math.floor(value / 3600);
  const minutes = Math.floor((value % 3600) / 60);
  const secs = value % 60;

  const parts = [];
  if (hours > 0) {
    parts.push(`${hours}h`);
  }
  if (minutes > 0 || hours > 0) {
    parts.push(`${minutes}m`);
  }
  parts.push(`${secs}s`);

  return parts.join(' ');
};

const escapeHtml = (value) =>
  String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const disableFleetManageAction = (element) => {
  if (!(element instanceof HTMLElement)) {
    return;
  }

  element.dataset.disabled = 'true';
  element.setAttribute('aria-disabled', 'true');
  element.classList.add('ui-button--disabled');
  if (element instanceof HTMLAnchorElement) {
    if (!element.dataset.originalHref) {
      const href = element.getAttribute('href');
      if (href) {
        element.dataset.originalHref = href;
      }
    }
    element.removeAttribute('href');
  } else if (element instanceof HTMLButtonElement) {
    element.disabled = true;
  }
};

const enableFleetManageAction = (element) => {
  if (!(element instanceof HTMLElement)) {
    return;
  }

  delete element.dataset.disabled;
  element.removeAttribute('aria-disabled');
  element.classList.remove('ui-button--disabled');
  if (element instanceof HTMLAnchorElement) {
    const originalHref = element.dataset.originalHref;
    if (originalHref) {
      element.setAttribute('href', originalHref);
    }
  } else if (element instanceof HTMLButtonElement) {
    element.disabled = false;
  }
};

const updateFleetRowStatus = (fleetId, status) => {
  if (!fleetId || !status) {
    return;
  }

  const normalizedStatus = String(status).toLowerCase();
  const row = document.querySelector(
    `[data-fleet-row][data-fleet-id="${CSS.escape(String(fleetId))}"]`
  );
  if (!(row instanceof HTMLElement)) {
    return;
  }

  row.dataset.fleetState = normalizedStatus;

  const statusCell = row.querySelector('[data-fleet-status]');
  if (statusCell instanceof HTMLElement) {
    statusCell.dataset.fleetStatus = normalizedStatus;
    const fallbackLabel = statusCell.dataset.defaultLabel || statusCell.textContent || '';
    const label = FLEET_STATUS_LABELS[normalizedStatus] || fallbackLabel;
    statusCell.textContent = label;
  }

  const manageAction = row.querySelector('[data-fleet-action="manage"]');
  if (ACTIVE_FLEET_STATUSES.has(normalizedStatus)) {
    disableFleetManageAction(manageAction);
  } else {
    enableFleetManageAction(manageAction);
  }
};

const buildPayload = (form) => {
  const destination = {
    galaxy: Number(form.elements.namedItem('destination_galaxy')?.value ?? 1),
    system: Number(form.elements.namedItem('destination_system')?.value ?? 1),
    position: Number(form.elements.namedItem('destination_position')?.value ?? 1),
  };

  const speedInput = form.elements.namedItem('speed_factor');
  const rawSpeed = speedInput instanceof HTMLInputElement ? Number(speedInput.value || 0) : 0;
  const speedFactor = rawSpeed > 1 ? rawSpeed / 100 : rawSpeed;

  const missionInput = form.querySelector('input[name="mission"]:checked');
  const mission = missionInput instanceof HTMLInputElement ? missionInput.value : 'transport';

  const resources = {};
  form.querySelectorAll('[name^="resources"]').forEach((input) => {
    if (!(input instanceof HTMLInputElement)) {
      return;
    }
    const match = input.name.match(/resources\[(.+)]/);
    if (!match) {
      return;
    }
    resources[match[1]] = Number(input.value || 0);
  });

  return {
    destination,
    speedFactor,
    mission,
    resources,
    fleetId: Number(
      form.dataset.fleetId || form.elements.namedItem('fleet_id')?.value || 0
    ),
    originPlanetId: Number(
      form.dataset.originPlanet || form.elements.namedItem('origin_planet_id')?.value || 0
    ),
  };
};

const renderErrors = (container, errors) => {
  if (!container) {
    return;
  }

  if (!errors || errors.length === 0) {
    container.innerHTML = '';

    return;
  }

  container.innerHTML = `
    <div class="form-errors" role="alert">
      <strong>Erreurs de planification</strong>
      <ul>${errors.map((error) => `<li>${escapeHtml(error)}</li>`).join('')}</ul>
    </div>
  `;
};

const attachHandlers = (form) => {
  const errorsContainer = form.querySelector('[data-fleet-plan-errors]');
  const layout = form.closest('.fleet-mission__layout');
  const resultContainer =
    form.querySelector('[data-fleet-plan-result]')
    || layout?.querySelector('[data-fleet-plan-result]');
  const launchButton = form.querySelector('[data-action="launch"]');
  const speedInput = form.elements.namedItem('speed_factor');
  const speedDisplay = form.querySelector('[data-speed-display]');

  const updateSpeedDisplay = () => {
    if (!(speedInput instanceof HTMLInputElement) || !speedDisplay) {
      return;
    }

    speedDisplay.textContent = `${Math.round(Number(speedInput.value || 0))}%`;
  };

  const markPlanDirty = () => {
    form.dataset.planReady = 'false';
    if (launchButton instanceof HTMLButtonElement) {
      launchButton.disabled = true;
    }
  };

  updateSpeedDisplay();
  if (speedInput instanceof HTMLInputElement) {
    speedInput.addEventListener('input', updateSpeedDisplay);
  }

  if (launchButton instanceof HTMLButtonElement) {
    launchButton.disabled = form.dataset.planReady !== 'true';
  }

  const submitPlan = async () => {
    const payload = buildPayload(form);
    payload.csrf_token = form.dataset.csrfPlan;

    try {
      const response = await fetch(form.dataset.planEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await response.json();
      if (!response.ok) {
        renderErrors(errorsContainer, data.errors ?? ['Une erreur est survenue.']);
        renderPlan(resultContainer, null);
        markPlanDirty();

        return;
      }

      renderErrors(errorsContainer, data.errors ?? []);
      renderPlan(resultContainer, data.plan ?? null);
      if (launchButton instanceof HTMLButtonElement) {
        launchButton.disabled = !data.plan;
      }
      form.dataset.planReady = data.plan ? 'true' : 'false';
    } catch (error) {
      renderErrors(errorsContainer, ['Impossible de contacter le serveur.']);
      renderPlan(resultContainer, null);
      markPlanDirty();
      console.error(error);
    }
  };

  const submitLaunch = async () => {
    const payload = buildPayload(form);
    payload.csrf_token = form.dataset.csrfLaunch;

    try {
      const response = await fetch(form.dataset.launchEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await response.json();
      if (!response.ok) {
        renderErrors(errorsContainer, data.errors ?? ['Impossible de lancer la mission.']);

        return;
      }

      renderErrors(errorsContainer, []);
      renderPlan(resultContainer, null);
      if (data.resources) {
        applyResourceSnapshot(data.resources);
      }
      if (payload.fleetId && data.mission?.status) {
        updateFleetRowStatus(payload.fleetId, data.mission.status);
      }
      form.reset();
      form.querySelectorAll('[name^="resources"]').forEach((input) => {
        if (input instanceof HTMLInputElement) {
          input.value = '0';
        }
      });
      updateSpeedDisplay();
      markPlanDirty();
    } catch (error) {
      renderErrors(errorsContainer, ['Impossible de contacter le serveur.']);
      console.error(error);
    }
  };

  const handleDirtyChange = (event) => {
    const target = event.target;
    if (
      !(
        target instanceof HTMLInputElement
        || target instanceof HTMLSelectElement
        || target instanceof HTMLTextAreaElement
      )
    ) {
      return;
    }

    if (target.name === 'csrf_token') {
      return;
    }

    markPlanDirty();

  };

  form.addEventListener('input', handleDirtyChange);
  form.addEventListener('change', handleDirtyChange);

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    submitPlan();
  });

  if (launchButton instanceof HTMLButtonElement) {
    launchButton.addEventListener('click', (event) => {
      event.preventDefault();
      submitLaunch();
    });
  }
};

export const initFleetPlanner = () => {
  document.querySelectorAll('[data-fleet-planner]').forEach((element) => {
    if (element instanceof HTMLFormElement) {
      attachHandlers(element);
    }
  });
};
