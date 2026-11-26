import type { ThemeTokens } from './types';

export const DEFAULT_PRESET = 'lasabro';

const FONT = 'Verdana, Geneva, Tahoma, sans-serif';

/** Explicit colour ramps (shade 0 lightest ... 9 darkest, shade 6 is the base colour) for presets that must stay pixel-identical. */
export interface PresetRamps { primary?: readonly string[]; secondary?: readonly string[] }

export const PRESETS: Record<string, { tokens: ThemeTokens; ramps?: PresetRamps }> = {
  /** LASABRO brand: green from the logo, with the logo's red-brown as secondary. */
  lasabro: {
    tokens: { primary: '#177306', secondary: '#731706', fontFamily: FONT, radius: 'md', layout: 'sidebar', navbarWidth: 280, headerHeight: 60 },
  },
  /** The look COPS had before LASABRO: lets an existing installation keep its colours with one setting. */
  'cops-classic': {
    tokens: { primary: '#3773b8', secondary: '#1c9d67', fontFamily: FONT, radius: 'md', layout: 'sidebar', navbarWidth: 280, headerHeight: 60 },
    ramps: {
      primary: ['#eef4fb', '#dbe7f5', '#b6cfeb', '#8fb5e0', '#6d9fd6', '#5089c8', '#3773b8', '#2d6099', '#244d7b', '#1b3a5d'],
      secondary: ['#e8f7f0', '#cdeedf', '#9bddc0', '#66cba0', '#3dbb88', '#25ab77', '#1c9d67', '#168054', '#116441', '#0b482e'],
    },
  },
};

const HEX = /^#[0-9a-f]{6}$/i;
const RADII: readonly ThemeTokens['radius'][] = ['xs', 'sm', 'md', 'lg', 'xl'];

const clamp = (n: number, min: number, max: number) => Math.min(max, Math.max(min, Math.round(n)));

function rgb(hex: string): [number, number, number] {
  return [parseInt(hex.slice(1, 3), 16), parseInt(hex.slice(3, 5), 16), parseInt(hex.slice(5, 7), 16)];
}

function mix(hex: string, target: [number, number, number], amount: number): string {
  const [r, g, b] = rgb(hex);
  const part = (from: number, to: number) => Math.round(from + (to - from) * amount).toString(16).padStart(2, '0');
  return `#${part(r, target[0])}${part(g, target[1])}${part(b, target[2])}`;
}

/**
 * Ten shades of a base colour for UI libraries that want a ramp: shades 0-5 mix the base towards white, shade 6 IS the base
 * (so a brand colour appears exactly as supplied on buttons), shades 7-9 mix towards black.
 */
export function shades(base: string): string[] {
  if (!HEX.test(base)) throw new Error(`"${base}" is not a #rrggbb colour.`);
  const lower = base.toLowerCase();
  return [
    ...[0.92, 0.82, 0.68, 0.5, 0.32, 0.15].map((t) => mix(lower, [255, 255, 255], t)),
    lower,
    ...[0.15, 0.3, 0.45].map((t) => mix(lower, [0, 0, 0], t)),
  ];
}

/** Keeps only valid override values; anything else is ignored. Tokens come from the server and end up in styles. */
export function sanitizeOverrides(raw: unknown): Partial<ThemeTokens> {
  if (typeof raw !== 'object' || raw === null) return {};
  const o = raw as Record<string, unknown>;
  const out: Partial<ThemeTokens> = {};
  if (typeof o.primary === 'string' && HEX.test(o.primary)) out.primary = o.primary.toLowerCase();
  if (typeof o.secondary === 'string' && HEX.test(o.secondary)) out.secondary = o.secondary.toLowerCase();
  if (typeof o.fontFamily === 'string' && /^[A-Za-z0-9 ,'"-]{1,200}$/.test(o.fontFamily)) out.fontFamily = o.fontFamily;
  if (typeof o.radius === 'string' && RADII.includes(o.radius as never)) out.radius = o.radius as ThemeTokens['radius'];
  if (typeof o.layout === 'string' && /^[a-z][a-z0-9-]{0,30}$/.test(o.layout)) out.layout = o.layout;
  if (typeof o.navbarWidth === 'number' && Number.isFinite(o.navbarWidth)) out.navbarWidth = clamp(o.navbarWidth, 220, 360);
  if (typeof o.headerHeight === 'number' && Number.isFinite(o.headerHeight)) out.headerHeight = clamp(o.headerHeight, 48, 80);
  return out;
}

export interface ResolvedTheme {
  preset: string;
  tokens: ThemeTokens;
  primaryShades: string[];
  secondaryShades: string[];
}

/** Preset + validated overrides => final tokens and colour ramps. An unknown preset falls back to the default one. */
export function resolveTheme(preset: string | undefined, overrides: unknown = {}): ResolvedTheme {
  const name = preset !== undefined && preset in PRESETS ? preset : DEFAULT_PRESET;
  const base = PRESETS[name];
  const clean = sanitizeOverrides(overrides);
  const tokens: ThemeTokens = { ...base.tokens, ...clean };
  // A preset's hand-tuned ramp only applies while its colour is not overridden.
  const primaryShades = base.ramps?.primary && tokens.primary === base.tokens.primary ? [...base.ramps.primary] : shades(tokens.primary);
  const secondaryShades = base.ramps?.secondary && tokens.secondary === base.tokens.secondary ? [...base.ramps.secondary] : shades(tokens.secondary);
  return { preset: name, tokens, primaryShades, secondaryShades };
}

/** CSS custom properties for any UI technology (the Mantine adapter does not need them; Vue/Angular shells and plain CSS do). */
export function cssVariables(theme: ResolvedTheme): Record<string, string> {
  const vars: Record<string, string> = {
    '--lasabro-font-family': theme.tokens.fontFamily,
    '--lasabro-radius': theme.tokens.radius,
    '--lasabro-navbar-width': `${theme.tokens.navbarWidth}px`,
    '--lasabro-header-height': `${theme.tokens.headerHeight}px`,
  };
  theme.primaryShades.forEach((c, i) => { vars[`--lasabro-primary-${i}`] = c; });
  theme.secondaryShades.forEach((c, i) => { vars[`--lasabro-secondary-${i}`] = c; });
  vars['--lasabro-primary'] = theme.tokens.primary;
  vars['--lasabro-secondary'] = theme.tokens.secondary;
  return vars;
}
