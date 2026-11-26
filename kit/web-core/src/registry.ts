import type { KitManifest, PluginDefinition, ResolvedContribution, ResolvedKit, ResolvedRoute } from './types';

const DEFAULT_ORDER = 100;

/**
 * Decides what the shell mounts. A definition without `id` belongs to the host edition and is always mounted. A definition
 * with an id is mounted only when the server's manifest lists that plugin as active, so a deactivated plugin contributes no
 * route, slot or widget (and its lazily loaded code is never downloaded).
 *
 * Two mounted definitions must not claim the same route path or widget code: that is a configuration error, reported with
 * both owners instead of one silently shadowing the other.
 */
export function resolvePlugins<C>(definitions: readonly PluginDefinition<C>[], manifest: KitManifest): ResolvedKit<C> {
  const active = new Set(manifest.plugins.filter((p) => p.active).map((p) => p.id));
  const mounted: string[] = [];
  const routes: ResolvedRoute<C>[] = [];
  const owners = new Map<string, string>();
  const slots = new Map<string, ResolvedContribution<C>[]>();
  const widgets = new Map<string, C>();
  const widgetOwners = new Map<string, string>();

  for (const def of definitions) {
    if (def.id !== undefined && !active.has(def.id)) continue;
    const owner = def.id ?? 'the host edition';
    if (def.id !== undefined) mounted.push(def.id);

    for (const route of def.routes ?? []) {
      const previous = owners.get(route.path);
      if (previous !== undefined) throw new Error(`Route "${route.path}" is declared by both ${previous} and ${owner}.`);
      owners.set(route.path, owner);
      routes.push({ ...route, plugin: def.id });
    }
    for (const [slot, contributions] of Object.entries(def.slots ?? {})) {
      const list = slots.get(slot) ?? [];
      for (const c of contributions ?? []) {
        if (list.some((x) => x.id === c.id)) throw new Error(`Slot "${slot}" already has a contribution with id "${c.id}" (${owner}).`);
        list.push({ ...c, plugin: def.id });
      }
      slots.set(slot, list);
    }
    for (const [code, component] of Object.entries(def.widgets ?? {})) {
      const previous = widgetOwners.get(code);
      if (previous !== undefined) throw new Error(`Widget "${code}" is provided by both ${previous} and ${owner}.`);
      widgetOwners.set(code, owner);
      widgets.set(code, component);
    }
  }

  for (const list of slots.values()) {
    list.sort((a, b) => (a.order ?? DEFAULT_ORDER) - (b.order ?? DEFAULT_ORDER) || a.id.localeCompare(b.id));
  }

  return { routes, slots, widgets, mounted };
}
