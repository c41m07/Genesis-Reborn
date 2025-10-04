import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(__dirname, '..');
const tokensPath = path.join(projectRoot, 'public/assets/css/tokens.css');
const reportPath = path.join(projectRoot, 'docs/ui/contrast-report.md');

const tokenPattern = /--([a-z0-9-]+):\s*([^;]+);/gi;
const rawTokens = new Map();

const source = readFileSync(tokensPath, 'utf-8');

for (const match of source.matchAll(tokenPattern)) {
  const [, name, value] = match;
  rawTokens.set(`--${name}`, value.trim());
}

const resolveTokenValue = (name, stack = new Set()) => {
  if (stack.has(name)) {
    throw new Error(`Circular token reference detected for ${name}`);
  }

  if (!rawTokens.has(name)) {
    throw new Error(`Unknown token ${name}`);
  }

  const rawValue = rawTokens.get(name);
  if (!rawValue) {
    throw new Error(`Empty token value for ${name}`);
  }

  if (!rawValue.includes('var(')) {
    return rawValue;
  }

  stack.add(name);
  const resolved = rawValue.replace(/var\((--[a-z0-9-]+)\)/gi, (_, tokenName) => resolveTokenValue(tokenName, new Set(stack)));
  stack.delete(name);

  return resolved;
};

const parseColor = (value) => {
  const trimmed = value.trim();
  if (trimmed.startsWith('#')) {
    const hex = trimmed.slice(1);
    if (hex.length === 3) {
      const [r, g, b] = hex.split('').map((ch) => parseInt(ch.repeat(2), 16));
      return { r, g, b, a: 1 };
    }
    if (hex.length === 6) {
      const r = parseInt(hex.slice(0, 2), 16);
      const g = parseInt(hex.slice(2, 4), 16);
      const b = parseInt(hex.slice(4, 6), 16);
      return { r, g, b, a: 1 };
    }
  }

  const rgbaMatch = trimmed.match(/rgba?\(([^)]+)\)/i);
  if (rgbaMatch) {
    const parts = rgbaMatch[1]
      .split(',')
      .map((part) => part.trim())
      .map((part) => (part.endsWith('%') ? (parseFloat(part) / 100) * 255 : parseFloat(part)));
    const [r, g, b, alpha = 1] = parts;
    return { r, g, b, a: alpha };
  }

  throw new Error(`Unsupported color value: ${value}`);
};

const srgbToLinear = (channel) => {
  const normalized = channel / 255;
  if (normalized <= 0.04045) {
    return normalized / 12.92;
  }

  return ((normalized + 0.055) / 1.055) ** 2.4;
};

const relativeLuminance = ({ r, g, b }) => {
  const R = srgbToLinear(r);
  const G = srgbToLinear(g);
  const B = srgbToLinear(b);

  return 0.2126 * R + 0.7152 * G + 0.0722 * B;
};

const contrastRatio = (foreground, background) => {
  const L1 = relativeLuminance(foreground);
  const L2 = relativeLuminance(background);
  const lighter = Math.max(L1, L2);
  const darker = Math.min(L1, L2);

  return (lighter + 0.05) / (darker + 0.05);
};

const blend = (foreground, background) => {
  const alpha = Math.max(0, Math.min(1, foreground.a ?? 1));
  if (alpha >= 1) {
    return { r: foreground.r, g: foreground.g, b: foreground.b, a: 1 };
  }

  const r = Math.round(foreground.r * alpha + background.r * (1 - alpha));
  const g = Math.round(foreground.g * alpha + background.g * (1 - alpha));
  const b = Math.round(foreground.b * alpha + background.b * (1 - alpha));

  return { r, g, b, a: 1 };
};

const cache = new Map();

const getColor = (tokenName, fallbackToken = '--color-bg') => {
  const cacheKey = `${tokenName}|${fallbackToken}`;
  if (cache.has(cacheKey)) {
    return cache.get(cacheKey);
  }

  const resolvedValue = resolveTokenValue(tokenName);
  const baseColor = parseColor(resolvedValue);
  let color = baseColor;

  if ((baseColor.a ?? 1) < 1) {
    const fallback = fallbackToken ? getColor(fallbackToken, null) : { r: 0, g: 0, b: 0, a: 1 };
    color = blend(baseColor, fallback);
  }

  cache.set(cacheKey, color);
  return color;
};

const formatColor = ({ r, g, b }) => `#${[r, g, b]
  .map((channel) => channel.toString(16).padStart(2, '0'))
  .join('')}`;

const combos = [
  {
    id: 'surface-body',
    label: 'Texte principal sur fond spatial',
    foreground: '--text-primary',
    background: '--color-bg',
  },
  {
    id: 'surface-card',
    label: 'Texte principal sur panneau',
    foreground: '--text-primary',
    background: '--color-surface',
  },
  {
    id: 'muted-card',
    label: 'Texte secondaire sur panneau',
    foreground: '--text-muted',
    background: '--color-surface',
  },
  {
    id: 'primary-button',
    label: 'Bouton primaire',
    foreground: '--color-primary-on',
    background: '--color-primary',
  },
  {
    id: 'secondary-button',
    label: 'Bouton secondaire',
    foreground: '--color-secondary-on',
    background: '--color-secondary',
  },
  {
    id: 'info-badge',
    label: 'Badge information',
    foreground: '--color-info-on',
    background: '--color-info',
  },
  {
    id: 'warning-badge',
    label: 'Badge avertissement',
    foreground: '--color-warning-on',
    background: '--color-warning',
  },
  {
    id: 'danger-badge',
    label: 'Badge danger',
    foreground: '--color-danger-on',
    background: '--color-danger',
  },
  {
    id: 'success-badge',
    label: 'Badge succès',
    foreground: '--color-success-on',
    background: '--color-success',
  },
];

const rows = combos.map(({ id, label, foreground, background }) => {
  const fgColor = getColor(foreground);
  const bgColor = getColor(background);
  const ratio = contrastRatio(fgColor, bgColor);

  return {
    id,
    label,
    foreground,
    background,
    ratio,
    ratioLabel: ratio.toFixed(2),
    passesAA: ratio >= 4.5,
    passesLarge: ratio >= 3,
    fgHex: formatColor(fgColor),
    bgHex: formatColor(bgColor),
  };
});

const lines = [
  '# Rapport de contraste',
  '',
  '| Variante | Avant-plan | Arrière-plan | Ratio | AA (4.5:1) | AA Large (3:1) |',
  '| --- | --- | --- | --- | --- | --- |',
  ...rows.map((row) => {
    const aa = row.passesAA ? '✅' : '⚠️';
    const large = row.passesLarge ? '✅' : '⚠️';

    return `| ${row.label} | ${row.fgHex} (${row.foreground}) | ${row.bgHex} (${row.background}) | ${row.ratioLabel} | ${aa} | ${large} |`;
  }),
  '',
  '_Généré automatiquement par `tools/generate-contrast-report.mjs`._',
];

writeFileSync(reportPath, `${lines.join('\n')}\n`, 'utf-8');

console.log(`Contrast report exported to ${path.relative(projectRoot, reportPath)}`);
