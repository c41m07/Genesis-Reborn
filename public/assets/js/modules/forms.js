import SELECTORS from './selectors.js';

export const initAutoSubmitSelects = () => {
  document.querySelectorAll(SELECTORS.autoSubmitSelect).forEach((select) => {
    if (!(select instanceof HTMLSelectElement)) {
      return;
    }

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

export default {
  initAutoSubmitSelects,
};
