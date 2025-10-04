import { beforeEach, test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!DOCTYPE html><body></body>', { url: 'https://example.com/', pretendToBeVisual: true });

globalThis.window = dom.window;
globalThis.document = dom.window.document;
globalThis.HTMLElement = dom.window.HTMLElement;
globalThis.HTMLButtonElement = dom.window.HTMLButtonElement;
globalThis.KeyboardEvent = dom.window.KeyboardEvent;

const { createFocusTrap } = await import('../modules/focus-trap.js');

beforeEach(() => {
  document.body.innerHTML = '';
});

test('focus trap guards focus cycling and releases tabindex', async () => {
  document.body.innerHTML = `
    <button type="button" id="opener">Open</button>
    <aside id="modal" data-focus-trap>
      <button type="button" id="close">Fermer</button>
      <a href="#" id="link">Lien</a>
      <button type="button" id="confirm">Confirmer</button>
    </aside>
  `;

  const modal = document.getElementById('modal');
  const opener = document.getElementById('opener');
  const close = document.getElementById('close');
  const confirm = document.getElementById('confirm');

  assert(modal instanceof HTMLElement);
  assert(opener instanceof HTMLElement);
  assert(close instanceof HTMLElement);
  assert(confirm instanceof HTMLElement);

  opener.focus();
  const trap = createFocusTrap(modal);
  trap.activate({ initialFocus: close });
  await new Promise((resolve) => setTimeout(resolve, 0));

  assert.equal(modal.getAttribute('tabindex'), '-1');
  assert.equal(document.activeElement, close);

  const focusableIds = Array.from(modal.querySelectorAll('button, a')).map((element) => element.id);
  assert.deepEqual(focusableIds, ['close', 'link', 'confirm']);

  confirm.focus();
  await new Promise((resolve) => setTimeout(resolve, 0));
  const forwardEvent = new window.KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
  const prevented = !confirm.dispatchEvent(forwardEvent);
  assert.equal(prevented, true);

  trap.deactivate();
  await new Promise((resolve) => setTimeout(resolve, 0));
  const activeElement = document.activeElement;
  assert(activeElement === opener || activeElement === document.body || !activeElement?.isConnected);
  assert.equal(modal.hasAttribute('tabindex'), false);
});
