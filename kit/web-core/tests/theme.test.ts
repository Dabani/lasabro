import { describe, expect, it } from 'vitest';
import { cssVariables, resolveTheme, shades } from '../src';

const luminance = (hex: string) => {
  const c = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));
  return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
};

describe('shades', () => {
  it('gives ten shades with the base colour exactly at index 6', () => {
    const s = shades('#177306');
    expect(s).toHaveLength(10);
    expect(s[6]).toBe('#177306');
  });

  it('gets darker from shade 0 to shade 9', () => {
    const s = shades('#177306').map(luminance);
    for (let i = 1; i < s.length; i++) expect(s[i]).toBeLessThan(s[i - 1]);
  });

  it('rejects anything that is not #rrggbb', () => {
    expect(() => shades('green')).toThrow();
    expect(() => shades('#fff')).toThrow();
  });
});

describe('resolveTheme', () => {
  it('defaults to the LASABRO brand preset', () => {
    const t = resolveTheme(undefined);
    expect(t.preset).toBe('lasabro');
    expect(t.tokens.primary).toBe('#177306');
    expect(t.tokens.secondary).toBe('#731706');
    expect(t.primaryShades[6]).toBe('#177306');
  });

  it('falls back to the default for an unknown preset', () => {
    expect(resolveTheme('nope').preset).toBe('lasabro');
  });

  it('cops-classic keeps the exact hand-tuned COPS ramps', () => {
    const t = resolveTheme('cops-classic');
    expect(t.primaryShades).toEqual(['#eef4fb', '#dbe7f5', '#b6cfeb', '#8fb5e0', '#6d9fd6', '#5089c8', '#3773b8', '#2d6099', '#244d7b', '#1b3a5d']);
    expect(t.secondaryShades[6]).toBe('#1c9d67');
  });

  it('overriding a colour regenerates its ramp from the new colour', () => {
    const t = resolveTheme('cops-classic', { primary: '#aa0000' });
    expect(t.primaryShades[6]).toBe('#aa0000');
    expect(t.secondaryShades[6]).toBe('#1c9d67');     // the other colour keeps its tuned ramp
  });

  it('clamps layout dimensions and refuses unsafe values', () => {
    const t = resolveTheme('lasabro', { navbarWidth: 10, headerHeight: 500, fontFamily: 'a{b}', radius: 'xl' });
    expect(t.tokens.navbarWidth).toBe(220);
    expect(t.tokens.headerHeight).toBe(80);
    expect(t.tokens.fontFamily).toContain('Verdana');
    expect(t.tokens.radius).toBe('xl');
  });

  it('exposes css variables for non-Mantine shells', () => {
    const v = cssVariables(resolveTheme('lasabro'));
    expect(v['--lasabro-primary']).toBe('#177306');
    expect(v['--lasabro-primary-6']).toBe('#177306');
    expect(v['--lasabro-navbar-width']).toBe('280px');
  });
});
