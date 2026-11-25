import { Group, Image, Text, useComputedColorScheme } from '@mantine/core';

interface Props { name: string; hasLogo?: boolean; size?: number; hideName?: boolean }

/**
 * Organization logo. An uploaded logo (Administration > Organization) always wins; without one the
 * LASABRO mark is shown, in white on dark themes and in colour on light ones.
 */
export function BrandLogo({ name, hasLogo, size = 32, hideName }: Props) {
  const scheme = useComputedColorScheme('light');
  const mark = scheme === 'dark' ? '/brand/icon-white.svg' : '/brand/icon.svg';
  return (
    <Group gap="xs" wrap="nowrap">
      <Image src={hasLogo ? '/api/v1/public/logo' : mark} w={size} h={size} fit="contain" alt={name} radius="sm" />
      {!hideName && <Text fw={700} size="sm" lh={1.2} lineClamp={2}>{name}</Text>}
    </Group>
  );
}
