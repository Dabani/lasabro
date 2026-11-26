import { Center, Loader } from '@mantine/core';
import type { ReactElement } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { useAuth } from './auth/AuthContext';
import { layouts } from './layouts';
import type { Permission } from '@lasabro/kit';
import { useKit } from '@lasabro/kit-react';
import { RequirePermission } from './components/RequirePermission';
import { RouteBoundary } from './components/RouteBoundary';
import { page } from './lib/lazyPage';
import { ActivatePage, ForgotPasswordPage, ResetPasswordPage } from './pages/PasswordPages';

const pg = page;   // route-level code splitting: each screen is its own chunk, fetched on first visit
const LoginPage = pg(() => import('./pages/LoginPage'), 'LoginPage');
const PortalApp = pg(() => import('./portal/PortalApp'), 'PortalApp');
const NotFoundPage = pg(() => import('./pages/ErrorPages'), 'NotFoundPage');
const MyPayslipsPage = pg(() => import('./pages/MyPayslipsPage'), 'MyPayslipsPage');
const MeetingRsvpPage = pg(() => import('./pages/MeetingRsvpPage'), 'MeetingRsvpPage');
const MeetingGuestJoinPage = pg(() => import('./pages/MeetingGuestJoinPage'), 'MeetingGuestJoinPage');
const EventRegisterPage = pg(() => import('./pages/EventRegisterPage'), 'EventRegisterPage');
const EventTicketPage = pg(() => import('./pages/EventTicketPage'), 'EventTicketPage');

export default function App() {
  const { me, loading } = useAuth();
  const { pathname } = useLocation();
  const { resolved, theme, pluginsReady } = useKit();

  if (pathname === '/portal' || pathname.startsWith('/portal/')) return <RouteBoundary><PortalApp /></RouteBoundary>;

  if (loading) return <Center h="100dvh"><Loader /></Center>;

  // Links from e-mails must open whether or not somebody is already signed in on this browser (the page itself asks them to
  // sign out). Previously a signed-in administrator testing an invitation landed on "page not found".
  if (pathname === '/activate' || pathname === '/reset-password' || pathname.startsWith('/meeting-rsvp/') || pathname.startsWith('/meeting-online/') || pathname.startsWith('/event-register/') || pathname.startsWith('/event-ticket/')) {
    return (
      <RouteBoundary><Routes>
        <Route path="/activate" element={<ActivatePage />} />
        <Route path="/reset-password" element={<ResetPasswordPage />} />
        <Route path="/meeting-rsvp/:token" element={<MeetingRsvpPage />} />
        <Route path="/meeting-online/:token" element={<MeetingGuestJoinPage />} />
        <Route path="/event-register/:token" element={<EventRegisterPage />} />
        <Route path="/event-ticket/:ticket" element={<EventTicketPage />} />
      </Routes></RouteBoundary>
    );
  }

  if (!me) {
    return (
      <RouteBoundary><Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes></RouteBoundary>
    );
  }

  if (!pluginsReady) return <Center h="100dvh"><Loader /></Center>;   // the signed-in user's plugin list is still loading

  const guard = (perm: Permission | undefined, el: ReactElement) =>
    perm === undefined ? el : <RequirePermission permission={perm as string | string[]}>{el}</RequirePermission>;
  const Layout = layouts[theme.tokens.layout] ?? layouts.sidebar;

  return (
    <Routes>
      <Route element={<Layout />}>
        <Route index element={<Navigate to="/dashboard" replace />} />
        {resolved.routes.map((r) => <Route key={r.path} path={r.path} element={guard(r.permission, <r.component />)} />)}
        <Route path="/login" element={<Navigate to="/dashboard" replace />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  );
}
