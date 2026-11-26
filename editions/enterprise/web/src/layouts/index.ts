import type { ComponentType } from 'react';
import { AppLayout } from './AppLayout';

/**
 * Layouts the shell can use, by id. The active one is chosen by the theme token `layout`; an unknown id falls back to `sidebar`.
 * Register another layout here (or from a web plugin) and select it with the `appearance.layout` setting.
 */
export const layouts: Record<string, ComponentType> = {
  sidebar: AppLayout,
};
