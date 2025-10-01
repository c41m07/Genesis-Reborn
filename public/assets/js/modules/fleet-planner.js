const formatNumber = (value) => new Intl.NumberFormat('fr-FR').format(value);

const renderPlan = (container, plan) => {
  if (!container) {
    return;
  }

  if (!plan) {
    container.innerHTML = '';

    return;
  }

  const arrival = plan.arrival_time || plan.arrivalAt || null;
  container.innerHTML = `
        <h3>Résultat de la simulation</h3>
        <ul class="metric-list">
            <li><span>Distance</span><strong>${formatNumber(Number(plan.distance))} u</strong></li>
            <li><span>Vitesse effective</span><strong>${formatNumber(Number(plan.speed))} u/h</strong></li>
            <li><span>Durée</span><strong>${formatDuration(plan.travel_time)}</strong></li>
            ${arrival ? `<li><span>Arrivée estimée</span><strong>${escapeHtml(arrival)}</strong></li>` : ''}
            <li><span>Consommation d’hydrogène</span><strong>${formatNumber(Number(plan.fuel))}</strong></li>
        </ul>
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

const buildPayload = (form) => {
  const destination = {
    galaxy: Number(form.elements.namedItem('destination_galaxy')?.value ?? 1),
    system: Number(form.elements.namedItem('destination_system')?.value ?? 1),
    position: Number(form.elements.namedItem('destination_position')?.value ?? 1),
  };

  const composition = {};
  form.querySelectorAll('[name^="composition"]').forEach((input) => {
    if (!(input instanceof HTMLInputElement)) {
      return;
    }
    const match = input.name.match(/composition\[(.+)]/);
    if (!match) {
      return;
    }
    composition[match[1]] = Number(input.value || 0);
  });

  const speedInput = form.elements.namedItem('speed_factor');
  const speedFactor = speedInput instanceof HTMLInputElement ? Number(speedInput.value || 0) : 0;

  return {
    destination,
    composition,
    speedFactor,
    mission: 'transport',
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

  container.innerHTML = `<ul class="form-errors">${errors.map((error) => `<li>${escapeHtml(error)}</li>`).join('')}</ul>`;
};

const attachHandlers = (form) => {
  const errorsContainer = form.querySelector('[data-fleet-plan-errors]');
  const resultContainer = form.querySelector('[data-fleet-plan-result]');
  const launchButton = form.querySelector('[data-action="launch"]');

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

        return;
      }

      renderErrors(errorsContainer, data.errors ?? []);
      renderPlan(resultContainer, data.plan ?? null);
    } catch (error) {
      renderErrors(errorsContainer, ['Impossible de contacter le serveur.']);
      renderPlan(resultContainer, null);
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
      form.reset();
    } catch (error) {
      renderErrors(errorsContainer, ['Impossible de contacter le serveur.']);
      console.error(error);
    }
  };

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
