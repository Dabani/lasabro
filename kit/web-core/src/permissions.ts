import type { Permission } from './types';

/** True when no permission is required or the user holds at least one of the listed ones. */
export function canAny(can: (code: string) => boolean, permission?: Permission): boolean {
  if (permission === undefined) return true;
  const list = typeof permission === 'string' ? [permission] : permission;
  return list.length === 0 || list.some((code) => can(code));
}
