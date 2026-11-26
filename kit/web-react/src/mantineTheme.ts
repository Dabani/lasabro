import { createTheme, type MantineColorsTuple, type MantineThemeOverride } from '@mantine/core';
import type { ResolvedTheme } from '@lasabro/kit';

const tuple = (shades: string[]) => shades as unknown as MantineColorsTuple;

/** Translates LASABRO theme tokens into a Mantine theme. Component defaults are the ones COPS always used. */
export function buildMantineTheme(theme: ResolvedTheme): MantineThemeOverride {
  const { tokens } = theme;
  return createTheme({
    primaryColor: 'brand',
    primaryShade: { light: 6, dark: 5 },
    colors: { brand: tuple(theme.primaryShades), secondary: tuple(theme.secondaryShades) },
    fontFamily: tokens.fontFamily,
    headings: { fontFamily: tokens.fontFamily, fontWeight: '700' },
    defaultRadius: tokens.radius,
    cursorType: 'pointer',
    components: {
      Button: { defaultProps: { fw: 600 } },
      Card: { defaultProps: { withBorder: true, radius: tokens.radius } },
      Paper: { defaultProps: { radius: tokens.radius } },
      TextInput: { defaultProps: { size: 'sm' } },
    },
  });
}
