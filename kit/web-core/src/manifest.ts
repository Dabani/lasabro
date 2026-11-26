import { DEFAULT_PRESET, sanitizeOverrides } from './theme';
import type { KitManifest, ManifestPlugin } from './types';

export function defaultManifest(): KitManifest {
  return { authenticated: false, edition: { id: 'enterprise', name: 'LASABRO Enterprise' }, plugins: [], theme: { preset: DEFAULT_PRESET, overrides: {} } };
}

const TIERS = ['platform', 'domain', 'edition'] as const;
const isObject = (v: unknown): v is Record<string, unknown> => typeof v === 'object' && v !== null && !Array.isArray(v);

/**
 * Reads a manifest received from the server. It never throws: anything missing or malformed falls back to the default for
 * that part, so an older API (no manifest endpoint) or a bad response still gives a working application.
 */
export function parseManifest(raw: unknown): KitManifest {
  const fallback = defaultManifest();
  const root = isObject(raw) && isObject(raw.data) ? raw.data : raw;   // accept the API's { data: ... } envelope
  if (!isObject(root)) return fallback;

  const edition = isObject(root.edition) && typeof root.edition.id === 'string'
    ? { id: root.edition.id, name: typeof root.edition.name === 'string' ? root.edition.name : fallback.edition.name }
    : fallback.edition;

  const plugins: ManifestPlugin[] = [];
  if (Array.isArray(root.plugins)) {
    for (const p of root.plugins) {
      if (!isObject(p) || typeof p.id !== 'string' || !/^[A-Z][A-Za-z0-9]*\.[A-Z][A-Za-z0-9]*$/.test(p.id)) continue;
      plugins.push({
        id: p.id,
        version: typeof p.version === 'string' ? p.version : '0.0.0',
        tier: TIERS.includes(p.tier as never) ? (p.tier as ManifestPlugin['tier']) : 'domain',
        active: p.active === true,
      });
    }
  }

  const theme = isObject(root.theme)
    ? { preset: typeof root.theme.preset === 'string' ? root.theme.preset : fallback.theme.preset, overrides: sanitizeOverrides(root.theme.overrides) }
    : fallback.theme;

  return { authenticated: root.authenticated === true, edition, plugins, theme };
}
