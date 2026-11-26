import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { Notifications } from '@mantine/notifications';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import '@mantine/core/styles.css';
import '@mantine/notifications/styles.css';
import '@mantine/charts/styles.css';
import '@mantine/dropzone/styles.css';
import './styles.css';
import { i18nReady } from './i18n';
import { KitRoot, ManifestSync } from './kit/KitRoot';
import { AuthProvider } from './auth/AuthContext';
import App from './App';
import { UpdatePrompt } from './components/UpdatePrompt';

const queryClient = new QueryClient({ defaultOptions: {
  queries: { refetchOnWindowFocus: false, retry: 1 },
  // Writes fail immediately when offline (with a toast) instead of silently replaying later.
  mutations: { networkMode: 'always' },
} });

// The app renders once the active language (and the English fallback) has been downloaded.
void i18nReady.then(() => ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <KitRoot>
      <Notifications position="top-right" />
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <AuthProvider>
            <ManifestSync />
            <App />
            <UpdatePrompt />
          </AuthProvider>
        </BrowserRouter>
      </QueryClientProvider>
    </KitRoot>
  </React.StrictMode>,
));
