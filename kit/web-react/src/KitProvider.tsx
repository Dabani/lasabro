import { createContext, useContext, useMemo, type ComponentType, type ReactNode } from 'react';
import { resolvePlugins, resolveTheme, type KitManifest, type PluginDefinition, type ResolvedKit, type ResolvedTheme } from '@lasabro/kit';

// Components of plugins receive arbitrary props from the slot or route that renders them.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type KitComponent = ComponentType<any>;

export interface KitContextValue {
  manifest: KitManifest;
  theme: ResolvedTheme;
  resolved: ResolvedKit<KitComponent>;
  /** False while the signed-in user's plugin list has not arrived yet: routes of plugins would wrongly look missing. */
  pluginsReady: boolean;
}

const KitContext = createContext<KitContextValue | null>(null);

/**
 * Holds what the server says is installed (the manifest) and what the web plugins contribute. Everything below it reads
 * routes, slots, widgets and theme from here. A conflict between plugins (two claiming one route) throws at this point
 * with a message naming both, which is a configuration error rather than something a user should ever see.
 */
export function KitProvider({ manifest, definitions, pluginsReady = true, children }: {
  manifest: KitManifest;
  definitions: readonly PluginDefinition<KitComponent>[];
  pluginsReady?: boolean;
  children: ReactNode;
}) {
  const value = useMemo<KitContextValue>(() => ({
    manifest,
    theme: resolveTheme(manifest.theme.preset, manifest.theme.overrides),
    resolved: resolvePlugins(definitions, manifest),
    pluginsReady,
  }), [manifest, definitions, pluginsReady]);
  return <KitContext.Provider value={value}>{children}</KitContext.Provider>;
}

export function useKit(): KitContextValue {
  const value = useContext(KitContext);
  if (!value) throw new Error('useKit() was called outside <KitProvider>.');
  return value;
}

/** The component a plugin registered for a dashboard widget code, if any. */
export function useWidgetComponent(code: string): KitComponent | undefined {
  return useKit().resolved.widgets.get(code);
}
