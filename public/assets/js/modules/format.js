const INTEGER_FORMATTER = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
const DECIMAL_FORMATTER = new Intl.NumberFormat('fr-FR', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

export const RESOURCE_LABELS = {
  metal: 'Métal',
  crystal: 'Cristal',
  hydrogen: 'Hydrogène',
  energy: 'Énergie',
  storage: 'Capacité',
};

export const getResourceLabel = (key) => {
  if (!key) {
    return '';
  }
  const normalized = String(key);
  return RESOURCE_LABELS[normalized] ?? normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

export const SPRITE_URL = new URL('../svg/sprite.svg', import.meta.url).toString();

export const escapeHtml = (value) =>
  String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const SUFFIXES = [
  { limit: 1_000_000_000, suffix: 'b' },
  { limit: 1_000_000, suffix: 'm' },
  { limit: 1_000, suffix: 'k' },
];

const isFiniteNumber = (value) => Number.isFinite(value) && !Number.isNaN(value);

export const formatNumber = (value) => {
  const numericValue = typeof value === 'number' ? value : Number(value ?? 0);
  if (!isFiniteNumber(numericValue)) {
    return INTEGER_FORMATTER.format(0);
  }

  const absValue = Math.abs(numericValue);

  for (const { limit, suffix } of SUFFIXES) {
    if (absValue >= limit) {
      const scaled = Math.trunc((absValue / limit) * 100 + 1e-9) / 100;
      const signed = numericValue < 0 ? -scaled : scaled;
      const hasFraction = Math.abs(signed - Math.round(signed)) > 1e-9;
      const formatter = hasFraction ? DECIMAL_FORMATTER : INTEGER_FORMATTER;

      return `${formatter.format(signed)}${suffix}`;
    }
  }

  const hasFraction = Math.abs(numericValue - Math.round(numericValue)) > 1e-9;
  const formatter = hasFraction ? DECIMAL_FORMATTER : INTEGER_FORMATTER;

  return formatter.format(numericValue);
};

export const normalizeClassNames = (...values) =>
  values
    .map((value) => (typeof value === 'string' ? value.trim() : ''))
    .filter((value) => value !== '')
    .join(' ');

export const metricValueClass = (value) => {
  if (value > 0) {
    return 'metric-line__value metric-line__value--positive';
  }
  if (value < 0) {
    return 'metric-line__value metric-line__value--negative';
  }

  return 'metric-line__value metric-line__value--neutral';
};

export const createIcon = (name, extraClass = '') => {
  const classes = ['icon', 'icon-sm'];
  if (typeof extraClass === 'string' && extraClass.trim() !== '') {
    classes.push(extraClass.trim());
  }

  return `
    <svg class="${classes.join(' ')}" aria-hidden="true">
      <use href="${SPRITE_URL}#icon-${escapeHtml(name)}"></use>
    </svg>
  `.trim();
};

export default {
  SPRITE_URL,
  escapeHtml,
  formatNumber,
  normalizeClassNames,
  metricValueClass,
  createIcon,
  getResourceLabel,
};
