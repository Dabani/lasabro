// @vitest-environment jsdom
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { definePlugin, defaultManifest, resolveTheme, type KitManifest } from '@lasabro/kit';
import { KitProvider, Slot, buildMantineTheme, useKit, useWidgetComponent, type KitComponent } from '../src';

afterEach(cleanup);

const active = (...ids: string[]): KitManifest => ({
  ...defaultManifest(),
  plugins: ids.map((id) => ({ id, version: '1.0.0', tier: 'domain' as const, active: true })),
});

const Clock: KitComponent = () => <span>clock</span>;
const Bell: KitComponent = ({ label }: { label?: string }) => <span>bell {label}</span>;
const Secret: KitComponent = () => <span>secret</span>;
const Broken: KitComponent = () => { throw new Error('boom'); };

const hr = definePlugin<KitComponent>({
  id: 'Lasabro.Hr',
  slots: { 'header.right': [
    { id: 'bell', component: Bell, order: 20 },
    { id: 'clock', component: Clock, order: 10 },
    { id: 'secret', component: Secret, order: 30, permission: 'hr.secret' },
    { id: 'broken', component: Broken, order: 40 },
  ] },
  widgets: { hr_headcount: Clock },
});

describe('Slot', () => {
  it('renders contributions in order, passes props and hides those needing a permission the user lacks', () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    const { container } = render(
      <KitProvider manifest={active('Lasabro.Hr')} definitions={[hr]}><Slot name="header.right" can={() => false} props={{ label: 'x' }} /></KitProvider>,
    );
    expect(container.textContent).toBe('clockbell x');
  });

  it('shows a permissioned contribution when the user has the permission', () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    render(<KitProvider manifest={active('Lasabro.Hr')} definitions={[hr]}><Slot name="header.right" can={(c) => c === 'hr.secret'} /></KitProvider>);
    expect(screen.getByText('secret')).toBeTruthy();
  });

  it('a contribution that throws renders nothing and does not break its siblings', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});
    render(<KitProvider manifest={active('Lasabro.Hr')} definitions={[hr]}><Slot name="header.right" can={() => true} /></KitProvider>);
    expect(screen.getByText('clock')).toBeTruthy();
    expect(spy.mock.calls.some((c) => String(c[0]).includes('slot contribution "broken" failed'))).toBe(true);
  });

  it('renders nothing for an empty slot or when the plugin is not active', () => {
    const { container } = render(<KitProvider manifest={defaultManifest()} definitions={[hr]}><Slot name="header.right" can={() => true} /></KitProvider>);
    expect(container.textContent).toBe('');
  });
});

describe('KitProvider', () => {
  it('exposes the resolved theme and widgets', () => {
    function Probe() {
      const kit = useKit();
      const W = useWidgetComponent('hr_headcount');
      return <p>{kit.theme.preset}:{W ? 'widget' : 'none'}</p>;
    }
    render(<KitProvider manifest={active('Lasabro.Hr')} definitions={[hr]}><Probe /></KitProvider>);
    expect(screen.getByText('lasabro:widget')).toBeTruthy();
  });

  it('useKit outside the provider is an explicit error', () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    expect(() => render(<Slot name="x" />)).toThrow('outside <KitProvider>');
  });
});

describe('buildMantineTheme', () => {
  it('cops-classic reproduces the theme COPS shipped with', () => {
    const t = buildMantineTheme(resolveTheme('cops-classic')) as Record<string, any>;
    expect(t.primaryColor).toBe('brand');
    expect(t.primaryShade).toEqual({ light: 6, dark: 5 });
    expect(t.colors.brand).toEqual(['#eef4fb', '#dbe7f5', '#b6cfeb', '#8fb5e0', '#6d9fd6', '#5089c8', '#3773b8', '#2d6099', '#244d7b', '#1b3a5d']);
    expect(t.colors.secondary[6]).toBe('#1c9d67');
    expect(t.fontFamily).toBe('Verdana, Geneva, Tahoma, sans-serif');
    expect(t.headings).toEqual({ fontFamily: 'Verdana, Geneva, Tahoma, sans-serif', fontWeight: '700' });
    expect(t.defaultRadius).toBe('md');
    expect(t.cursorType).toBe('pointer');
    expect(t.components).toEqual({
      Button: { defaultProps: { fw: 600 } },
      Card: { defaultProps: { withBorder: true, radius: 'md' } },
      Paper: { defaultProps: { radius: 'md' } },
      TextInput: { defaultProps: { size: 'sm' } },
    });
  });

  it('the default LASABRO preset puts the logo green on shade 6', () => {
    const t = buildMantineTheme(resolveTheme('lasabro')) as Record<string, any>;
    expect(t.colors.brand[6]).toBe('#177306');
  });
});

describe('pluginsReady', () => {
  it('defaults to ready and can be held back until the plugin list arrives', () => {
    function Probe() { return <p>{String(useKit().pluginsReady)}</p>; }
    const { rerender } = render(<KitProvider manifest={defaultManifest()} definitions={[]}><Probe /></KitProvider>);
    expect(screen.getByText('true')).toBeTruthy();
    rerender(<KitProvider manifest={defaultManifest()} definitions={[]} pluginsReady={false}><Probe /></KitProvider>);
    expect(screen.getByText('false')).toBeTruthy();
  });
});
