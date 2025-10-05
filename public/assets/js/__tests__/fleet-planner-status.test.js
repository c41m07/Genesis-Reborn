import { afterEach, beforeEach, test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!DOCTYPE html><body></body>', { url: 'https://example.test/' });

globalThis.window = dom.window;
globalThis.document = dom.window.document;
globalThis.HTMLElement = dom.window.HTMLElement;
globalThis.HTMLAnchorElement = dom.window.HTMLAnchorElement;
globalThis.HTMLButtonElement = dom.window.HTMLButtonElement;
globalThis.HTMLFormElement = dom.window.HTMLFormElement;
globalThis.HTMLInputElement = dom.window.HTMLInputElement;
globalThis.HTMLSelectElement = dom.window.HTMLSelectElement;
globalThis.HTMLTextAreaElement = dom.window.HTMLTextAreaElement;
globalThis.Node = dom.window.Node;

if (!globalThis.CSS) {
  globalThis.CSS = { escape: (value) => String(value) };
} else if (typeof globalThis.CSS.escape !== 'function') {
  globalThis.CSS.escape = (value) => String(value);
}

const { initFleetPlanner } = await import('../modules/fleet-planner.js');

let originalFetch;

beforeEach(() => {
  originalFetch = globalThis.fetch;
  document.body.innerHTML = `
    <div>
      <table class="fleet-table">
        <tbody>
          <tr data-fleet-row data-fleet-id="42" data-fleet-state="idle">
            <td><strong>Flotte Alpha</strong></td>
            <td class="fleet-table__metric">10</td>
            <td class="fleet-table__status" data-fleet-status="idle" data-default-label="Flotte opérationnelle">
              Flotte opérationnelle
            </td>
            <td class="fleet-table__actions">
              <a class="ui-button ui-button--neutral ui-button--sm" data-fleet-action="manage" href="/fleet?fleet=42">
                <span class="ui-button__label">Gérer</span>
              </a>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="fleet-mission__layout">
        <form
          data-fleet-planner
          data-plan-endpoint="/plan"
          data-launch-endpoint="/launch"
          data-origin-planet="1"
          data-fleet-id="42"
          data-csrf-plan="plan-token"
          data-csrf-launch="launch-token"
          data-plan-ready="true"
        >
          <div data-fleet-plan-errors></div>
          <div data-fleet-plan-result></div>
          <input type="hidden" name="origin_planet_id" value="1" />
          <input type="hidden" name="fleet_id" value="42" />
          <input type="number" name="destination_galaxy" value="1" />
          <input type="number" name="destination_system" value="1" />
          <input type="number" name="destination_position" value="1" />
          <input type="range" name="speed_factor" value="100" />
          <label>
            <input type="radio" name="mission" value="transport" checked />
          </label>
          <input type="number" name="resources[metal]" value="0" />
          <input type="number" name="resources[crystal]" value="0" />
          <input type="number" name="resources[hydrogen]" value="0" />
          <button type="button" data-action="launch">Lancer</button>
        </form>
      </div>
    </div>
  `;
});

afterEach(() => {
  document.body.innerHTML = '';
  globalThis.fetch = originalFetch;
});

test('updates fleet row status and disables manage action after launch', async () => {
  let receivedPayload;
  globalThis.fetch = async (_url, options = {}) => {
    receivedPayload = JSON.parse(options.body ?? '{}');
    return {
      ok: true,
      json: async () => ({
        success: true,
        mission: { status: 'outbound' },
        resources: null,
      }),
    };
  };

  initFleetPlanner();

  const launchButton = document.querySelector('[data-action="launch"]');
  launchButton?.dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true }));

  await new Promise((resolve) => dom.window.setTimeout(resolve, 0));

  assert.deepEqual(receivedPayload, {
    destination: { galaxy: 1, system: 1, position: 1 },
    speedFactor: 1,
    mission: 'transport',
    resources: { metal: 0, crystal: 0, hydrogen: 0 },
    fleetId: 42,
    originPlanetId: 1,
    csrf_token: 'launch-token',
  });

  const statusCell = document.querySelector('[data-fleet-row][data-fleet-id="42"] [data-fleet-status]');
  assert.equal(statusCell?.textContent?.trim(), 'Mission en cours');
  assert.equal(statusCell?.dataset.fleetStatus, 'outbound');

  const manageAction = document.querySelector('[data-fleet-row][data-fleet-id="42"] [data-fleet-action="manage"]');
  assert.equal(manageAction?.getAttribute('aria-disabled'), 'true');
  assert.equal(manageAction?.dataset.disabled, 'true');
  assert.equal(manageAction?.getAttribute('href'), null);
});
