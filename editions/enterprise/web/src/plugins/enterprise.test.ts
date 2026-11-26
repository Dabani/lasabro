import { describe, expect, it } from 'vitest';
import { defaultManifest, resolvePlugins } from '@lasabro/kit';
import { enterprise } from './enterprise';
import snapshot from './enterprise.routes.snapshot.json';

/**
 * Guard for the refactor into plugins: the screens a signed-in user can reach (path, required permission, component) were
 * captured from the original hand-written router. However the routes are later split among plugins, the union must stay equal.
 */
describe('host edition routes', () => {
  const resolved = resolvePlugins([enterprise], defaultManifest());

  it('matches the snapshot taken from the original router (paths and permissions)', () => {
    const actual = resolved.routes.map((r) => ({ path: r.path, permission: r.permission === undefined ? null : [r.permission].flat() }));
    expect(actual).toEqual(snapshot.map((s) => ({ path: s.path, permission: s.permission })));
  });

  it('has no duplicate paths', () => {
    const paths = resolved.routes.map((r) => r.path);
    expect(new Set(paths).size).toBe(paths.length);
  });

  it('every screen except the profile page requires a permission', () => {
    const open = resolved.routes.filter((r) => r.permission === undefined).map((r) => r.path);
    expect(open).toEqual(['/profile']);
  });
});
