import { ActionIcon, AppShell, Burger, Divider, Group, Menu, ScrollArea, Text, useComputedColorScheme, useMantineColorScheme } from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconLogout, IconMoon, IconSun, IconUserCircle } from '@tabler/icons-react';
import { Outlet, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth } from '@/auth/AuthContext';
import { BrandLogo } from '@/components/BrandLogo';
import { SidebarMenu } from '@/components/SidebarMenu';
import { LanguageSwitcher } from '@/components/LanguageSwitcher';
import { NotificationBell } from '@/components/NotificationBell';
import { TimerWidget } from '@/components/TimerWidget';
import { InstallPrompt } from '@/components/InstallPrompt';
import { OfflineBanner } from '@/components/OfflineBanner';
import { UserAvatar } from '@/components/UserAvatar';
import { ChatWidget } from '@/components/ChatWidget';
import { RouteBoundary } from '@/components/RouteBoundary';
import { Slot, useKit } from '@lasabro/kit-react';

export function AppLayout() {
  const { t } = useTranslation();
  const { me, logout, can } = useAuth();
  const nav = useNavigate();
  const [opened, { toggle, close }] = useDisclosure(false);
  const { setColorScheme } = useMantineColorScheme();
  const scheme = useComputedColorScheme('light');
  const { theme } = useKit();

  if (!me) return null;
  const topRole = [...me.roles].sort((a, b) => b.level - a.level)[0];

  return (
    <AppShell header={{ height: theme.tokens.headerHeight }} navbar={{ width: theme.tokens.navbarWidth, breakpoint: 'md', collapsed: { mobile: !opened } }} padding={{ base: 'sm', sm: 'md', lg: 'lg' }}>
      <AppShell.Header>
        <Group h="100%" px="md" justify="space-between" wrap="nowrap">
          <Group wrap="nowrap">
            <Burger opened={opened} onClick={toggle} hiddenFrom="md" size="sm" aria-label="Menu" />
            <BrandLogo name={me.organization.name} hasLogo={me.organization.has_logo} />
            <Slot name="header.left" can={can} />
          </Group>
          <Group gap="xs" wrap="nowrap">
            <Slot name="header.right" can={can} />
            <InstallPrompt />
            <LanguageSwitcher />
            <ActionIcon variant="subtle" size="lg" aria-label={t('common.theme')} onClick={() => setColorScheme(scheme === 'dark' ? 'light' : 'dark')}>
              {scheme === 'dark' ? <IconSun size={20} /> : <IconMoon size={20} />}
            </ActionIcon>
            <TimerWidget />
            <NotificationBell />
            <Menu position="bottom-end" width={230} shadow="md">
              <Menu.Target>
                <ActionIcon variant="transparent" size="lg" aria-label={me.name}>
                  <UserAvatar userId={me.id} hasAvatar={me.has_avatar} name={me.name} size={34} />
                </ActionIcon>
              </Menu.Target>
              <Menu.Dropdown>
                <Menu.Label>
                  <Text size="sm" fw={600} c="inherit" truncate>{me.name}</Text>
                  <Text size="xs" c="dimmed" truncate>{topRole?.name} · {me.email}</Text>
                </Menu.Label>
                <Divider />
                <Menu.Item leftSection={<IconUserCircle size={16} />} onClick={() => nav('/profile')}>{t('profile.title')}</Menu.Item>
                <Menu.Item color="red" leftSection={<IconLogout size={16} />} onClick={() => void logout()}>{t('auth.sign_out')}</Menu.Item>
              </Menu.Dropdown>
            </Menu>
          </Group>
        </Group>
      </AppShell.Header>

      <AppShell.Navbar p="sm">
        <AppShell.Section grow component={ScrollArea}><SidebarMenu onNavigate={close} /></AppShell.Section>
        <Slot name="sidebar.bottom" can={can} />
      </AppShell.Navbar>

      <AppShell.Main>
        <OfflineBanner />
        <Slot name="page.header" can={can} />
        <RouteBoundary><Outlet /></RouteBoundary>
        <Slot name="page.footer" can={can} />
        {can('chat.use') && <ChatWidget />}
      </AppShell.Main>
    </AppShell>
  );
}
