import { defaultManifest, parseManifest, type KitManifest } from '@lasabro/kit';
import { api } from '@/api/client';

const KEY = 'lasabro.manifest';

/** Last manifest seen on this device, so the theme and plugin routes are right on the first paint of the next visit. */
export function readCachedManifest(): KitManifest {
  try {
    const raw = localStorage.getItem(KEY);
    return raw ? parseManifest(JSON.parse(raw)) : defaultManifest();
  } catch {
    return defaultManifest();
  }
}

export function cacheManifest(manifest: KitManifest): void {
  try { localStorage.setItem(KEY, JSON.stringify(manifest)); } catch { /* private mode: the cache is only an optimisation */ }
}

/** `GET /kit/manifest`: edition, theme and, for a signed-in user, the plugin list. Never throws on a malformed body. */
export async function fetchManifest(): Promise<KitManifest> {
  const response = await api.get('/kit/manifest');
  return parseManifest(response.data);
}
