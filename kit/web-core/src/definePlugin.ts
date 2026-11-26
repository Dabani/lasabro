import type { PluginDefinition } from './types';

/** Identity helper that gives plugin authors type checking and completion. */
export function definePlugin<C = unknown>(definition: PluginDefinition<C>): PluginDefinition<C> {
  return definition;
}
