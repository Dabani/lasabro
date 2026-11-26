import { describe, expect, it } from 'vitest';
import { definePlugin, defaultManifest, resolvePlugins, type KitManifest } from '../src';

type C = string;   // any component type will do: the core never calls it

const manifest = (active: string[], inactive: string[] = []): KitManifest => ({
  ...defaultManifest(),
  plugins: [
    ...active.map((id) => ({ id, version: '1.0.0', tier: 'domain' as const, active: true })),
    ...inactive.map((id) => ({ id, version: '1.0.0', tier: 'domain' as const, active: false })),
  ],
});

const host = definePlugin<C>({ routes: [{ path: '/dashboard', component: 'Dashboard', permission: 'dashboard.view' }] });
const hr = definePlugin<C>({
  id: 'Lasabro.Hr',
  routes: [{ path: '/hr/employees', component: 'Employees', permission: 'employee.view' }],
  slots: { 'header.right': [{ id: 'hr-clock', component: 'ClockButton', order: 20 }] },
  widgets: { hr_headcount: 'HeadcountWidget' },
});
const payroll = definePlugin<C>({ id: 'Lasabro.Payroll', routes: [{ path: '/payroll', component: 'Payroll' }] });

describe('resolvePlugins', () => {
  it('always mounts the host edition (no id) even with an empty manifest', () => {
    const r = resolvePlugins([host], defaultManifest());
    expect(r.routes.map((x) => x.path)).toEqual(['/dashboard']);
    expect(r.mounted).toEqual([]);
  });

  it('mounts a plugin only when the manifest lists it as active', () => {
    expect(resolvePlugins([host, hr, payroll], manifest(['Lasabro.Hr'], ['Lasabro.Payroll'])).routes.map((x) => x.path))
      .toEqual(['/dashboard', '/hr/employees']);
    expect(resolvePlugins([host, hr, payroll], manifest([])).routes.map((x) => x.path)).toEqual(['/dashboard']);
  });

  it('a deactivated plugin contributes no route, slot or widget', () => {
    const r = resolvePlugins([hr], manifest([], ['Lasabro.Hr']));
    expect(r.routes).toEqual([]);
    expect(r.slots.get('header.right')).toBeUndefined();
    expect(r.widgets.size).toBe(0);
  });

  it('collects slot contributions ordered by order then id, and widgets by code', () => {
    const extra = definePlugin<C>({ id: 'Lasabro.Meetings', slots: { 'header.right': [
      { id: 'a-first', component: 'A', order: 5 }, { id: 'z-last', component: 'Z' }, { id: 'b-default', component: 'B' },
    ] } });
    const r = resolvePlugins([hr, extra], manifest(['Lasabro.Hr', 'Lasabro.Meetings']));
    expect(r.slots.get('header.right')!.map((x) => x.id)).toEqual(['a-first', 'hr-clock', 'b-default', 'z-last']);
    expect(r.widgets.get('hr_headcount')).toBe('HeadcountWidget');
    expect(r.mounted).toEqual(['Lasabro.Hr', 'Lasabro.Meetings']);
  });

  it('remembers which plugin contributed each route', () => {
    const r = resolvePlugins([host, hr], manifest(['Lasabro.Hr']));
    expect(r.routes.find((x) => x.path === '/hr/employees')!.plugin).toBe('Lasabro.Hr');
    expect(r.routes.find((x) => x.path === '/dashboard')!.plugin).toBeUndefined();
  });

  it('refuses two plugins claiming the same route and names both', () => {
    const clash = definePlugin<C>({ id: 'Lasabro.Other', routes: [{ path: '/hr/employees', component: 'X' }] });
    expect(() => resolvePlugins([hr, clash], manifest(['Lasabro.Hr', 'Lasabro.Other'])))
      .toThrow('Route "/hr/employees" is declared by both Lasabro.Hr and Lasabro.Other.');
  });

  it('refuses a duplicate widget code or slot contribution id', () => {
    const w = definePlugin<C>({ id: 'Lasabro.Other', widgets: { hr_headcount: 'X' } });
    expect(() => resolvePlugins([hr, w], manifest(['Lasabro.Hr', 'Lasabro.Other']))).toThrow('Widget "hr_headcount"');
    const s = definePlugin<C>({ id: 'Lasabro.Other', slots: { 'header.right': [{ id: 'hr-clock', component: 'X' }] } });
    expect(() => resolvePlugins([hr, s], manifest(['Lasabro.Hr', 'Lasabro.Other']))).toThrow('already has a contribution');
  });

  it('a clash with a deactivated plugin is not an error', () => {
    const clash = definePlugin<C>({ id: 'Lasabro.Other', routes: [{ path: '/hr/employees', component: 'X' }] });
    expect(() => resolvePlugins([hr, clash], manifest(['Lasabro.Hr'], ['Lasabro.Other']))).not.toThrow();
  });
});
