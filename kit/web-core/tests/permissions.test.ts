import { describe, expect, it } from 'vitest';
import { canAny } from '../src';

describe('canAny', () => {
  const can = (code: string) => ['a.view', 'b.manage'].includes(code);
  it('allows when nothing is required', () => {
    expect(canAny(can)).toBe(true);
    expect(canAny(can, [])).toBe(true);
  });
  it('needs the single permission', () => {
    expect(canAny(can, 'a.view')).toBe(true);
    expect(canAny(can, 'x.view')).toBe(false);
  });
  it('needs any one of several', () => {
    expect(canAny(can, ['x.view', 'b.manage'])).toBe(true);
    expect(canAny(can, ['x.view', 'y.view'])).toBe(false);
  });
});
