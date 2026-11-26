import { MantineProvider } from '@mantine/core';
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { KitProvider, buildMantineTheme, useKit } from '@lasabro/kit-react';
import { useAuth } from '@/auth/AuthContext';
import { enterprise } from '@/plugins/enterprise';
import { plugins as webPlugins } from '@/plugins.generated';
import { cacheManifest, fetchManifest, readCachedManifest } from './manifestClient';

const definitions = [enterprise, ...webPlugins];

const RefreshContext = createContext<() => Promise<void>>(async () => {});

/**
 * Outermost shell provider: loads the server's manifest (edition, theme, active plugins) and applies it.
 * It renders with the cached manifest immediately and replaces it when the fresh one arrives; if the API has no
 * manifest endpoint (an older server) the application simply runs with the defaults.
 */
export function KitRoot({ children }: { children: ReactNode }) {
  const [manifest, setManifest] = useState(readCachedManifest);
  const [failed, setFailed] = useState(false);

  const refresh = useCallback(async () => {
    try {
      const fresh = await fetchManifest();
      cacheManifest(fresh);
      setManifest(fresh);
      setFailed(false);
    } catch {
      setFailed(true);
    }
  }, []);

  useEffect(() => { void refresh(); }, [refresh]);

  return (
    <RefreshContext.Provider value={refresh}>
      <KitProvider manifest={manifest} definitions={definitions} pluginsReady={manifest.authenticated || failed}>
        <Themed>{children}</Themed>
      </KitProvider>
    </RefreshContext.Provider>
  );
}

function Themed({ children }: { children: ReactNode }) {
  const { theme } = useKit();
  const mantineTheme = useMemo(() => buildMantineTheme(theme), [theme]);
  return <MantineProvider theme={mantineTheme} defaultColorScheme="auto">{children}</MantineProvider>;
}

/** Inside AuthProvider: when somebody signs in, fetch the manifest again so it carries their plugin list. */
export function ManifestSync() {
  const { me } = useAuth();
  const { manifest } = useKit();
  const refresh = useContext(RefreshContext);
  useEffect(() => {
    if (me && !manifest.authenticated) void refresh();
  }, [me, manifest.authenticated, refresh]);
  return null;
}
