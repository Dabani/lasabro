# LASABRO web kit

Two npm packages and one API document make the frontend modular and replaceable.

| Piece | What it is |
|---|---|
| `GET /api/v1/kit/manifest` | Language-neutral JSON: `{ authenticated, edition, plugins[], theme }`. Any frontend (React, Vue, Angular, Flutter) reads it. The plugin list is sent to signed-in users only |
| `@lasabro/kit` (`kit/web-core`) | Plain TypeScript, no UI framework: plugin registry, manifest parser, theme tokens, permission helper, registry generator |
| `@lasabro/kit-react` (`kit/web-react`) | React + Mantine adapter: `KitProvider`, `Slot`, `useKit`, `useWidgetComponent`, `buildMantineTheme` |

## What a web plugin contributes
```ts
import { definePlugin } from '@lasabro/kit';
import { lazy } from 'react';

export default definePlugin({
  id: 'Lasabro.Hr',                       // must match the API plugin; mounted only while it is ACTIVE
  routes: [
    { path: '/hr/employees', permission: 'employee.view', component: lazy(() => import('./pages/EmployeesPage')) },
  ],
  slots: {                                 // partials: components dropped into named extension points
    'header.right': [{ id: 'hr-clock', order: 20, permission: 'hr.attendance.clock', component: ClockButton }],
  },
  widgets: { hr_headcount: HeadcountWidget },   // dashboard widget code (declared by the server) => component
});
```
Built-in slots: `header.left`, `header.right`, `sidebar.top`, `sidebar.bottom`, `page.header`, `page.footer`, `dashboard.widgets`,
`profile.tabs`, `login.aside`. Plugins may define their own (`hr.employee.tabs`). A contribution that throws renders nothing and is logged;
it never takes the shell down.

The package.json of a web plugin declares it, so the host can find it:
```json
{ "name": "@lasabro/hr-react", "lasabro": { "plugin": "Lasabro.Hr", "frontend": "react" } }
```
`npm run plugins:sync` (the `lasabro-web-sync` command; Node 22.18 or newer) lists the installed web plugins and writes
`src/plugins.generated.ts` with static imports (what Vite needs to split code). A deactivated plugin is not mounted and its lazy chunks are never downloaded.
Two plugins claiming the same route path, widget code or slot id stop the app with a message naming both.

## Theme and layout
Tokens: `primary`, `secondary`, `fontFamily`, `radius`, `layout`, `navbarWidth`, `headerHeight`.
Presets: **`lasabro`** (default, logo green `#177306`, accent `#731706`) and **`cops-classic`** (the previous blue/green, byte-identical ramps).
The server chooses the preset and may override tokens, in this order of precedence:
1. organization settings `appearance.preset`, `appearance.primary`, `appearance.secondary`, `appearance.font_family`, `appearance.radius`,
   `appearance.layout`, `appearance.navbar_width`, `appearance.header_height` (empty or 0 = use the preset);
2. the env default `LASABRO_THEME_PRESET` of the API;
3. the built-in default (`lasabro`).
The client validates every value again (colours are `#rrggbb`, widths are clamped, font names cannot contain CSS syntax).
`cssVariables()` exposes the same tokens as `--lasabro-*` custom properties for non-Mantine shells.

Layouts are registered by id in `src/layouts/index.ts` (`sidebar` today); the token `layout` selects one, an unknown id falls back to `sidebar`.

## For an existing COPS installation
Set `LASABRO_THEME_PRESET=cops-classic` in the API `.env` to keep the previous colours. Nothing else changes visually: all slots are empty until a plugin fills them.
