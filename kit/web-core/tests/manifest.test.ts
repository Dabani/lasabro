import { describe, expect, it } from 'vitest';
import { defaultManifest, parseManifest } from '../src';

describe('parseManifest', () => {
  it('returns the default for anything that is not an object (old API, error page, null)', () => {
    for (const bad of [null, undefined, 'x', 42, [], '<html>']) expect(parseManifest(bad)).toEqual(defaultManifest());
  });

  it('reads a complete manifest, with or without the { data } envelope', () => {
    const doc = {
      authenticated: true,
      edition: { id: 'sports', name: 'LASABRO Sports' },
      plugins: [{ id: 'Lasabro.Hr', version: '1.2.0', tier: 'domain', active: true }, { id: 'Lasabro.Core', version: '1.0.0', tier: 'platform', active: true }],
      theme: { preset: 'cops-classic', overrides: { primary: '#112233' } },
    };
    const a = parseManifest(doc);
    expect(a.authenticated).toBe(true);
    expect(a.edition).toEqual({ id: 'sports', name: 'LASABRO Sports' });
    expect(a.plugins).toHaveLength(2);
    expect(a.theme).toEqual({ preset: 'cops-classic', overrides: { primary: '#112233' } });
    expect(parseManifest({ data: doc })).toEqual(a);
  });

  it('drops malformed plugins and unknown tiers, and treats missing active as inactive', () => {
    const m = parseManifest({ plugins: [{ id: 'bad id' }, { id: 'Lasabro.Hr' }, { id: 'Lasabro.Rules', tier: 'magic', active: true }, 7, null] });
    expect(m.plugins).toEqual([
      { id: 'Lasabro.Hr', version: '0.0.0', tier: 'domain', active: false },
      { id: 'Lasabro.Rules', version: '0.0.0', tier: 'domain', active: true },
    ]);
  });

  it('ignores invalid theme overrides instead of failing', () => {
    const m = parseManifest({ theme: { preset: 'lasabro', overrides: { primary: 'red', radius: 'huge', navbarWidth: 9999, fontFamily: 'x;}body{display:none' } } });
    expect(m.theme.overrides).toEqual({ navbarWidth: 360 });
  });
});
