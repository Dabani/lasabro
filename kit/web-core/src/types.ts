/** One permission code, or several of which any one is enough (the same rule the API's `perm:` middleware uses). */
export type Permission = string | readonly string[];

/**
 * Named extension points of the shell. A plugin contributes components to them; the shell renders what is contributed.
 * Plugins may use their own names too (for example `hr.employee.tabs`): the type only suggests the built-in ones.
 */
export type SlotName =
  | 'header.left' | 'header.right'
  | 'sidebar.top' | 'sidebar.bottom'
  | 'page.header' | 'page.footer'
  | 'dashboard.widgets' | 'profile.tabs' | 'login.aside'
  | (string & {});

export interface RouteDefinition<C> {
  /** react-router style path, for example `/hr/employees/:id` */
  path: string;
  component: C;
  /** Absent = any signed-in user. The server enforces permissions regardless; this only hides screens. */
  permission?: Permission;
}

export interface SlotContribution<C> {
  /** Unique within the slot; also used as the React key. */
  id: string;
  component: C;
  /** Lower comes first. Default 100. */
  order?: number;
  permission?: Permission;
}

/**
 * What a web plugin (or the host edition) hands to the shell. `C` is the component type of the UI framework in use
 * (a React component, a Vue component...), so this package never imports one.
 */
export interface PluginDefinition<C = unknown> {
  /** Plugin id, `Lasabro.Hr`. Omit for code that belongs to the edition itself: it is always mounted. */
  id?: string;
  title?: string;
  routes?: RouteDefinition<C>[];
  slots?: Partial<Record<SlotName, SlotContribution<C>[]>>;
  /** Dashboard widget code (as declared by the server) => component that renders it. */
  widgets?: Record<string, C>;
}

export interface ManifestPlugin {
  id: string;
  version: string;
  tier: 'platform' | 'domain' | 'edition';
  active: boolean;
}

/** `GET /api/v1/kit/manifest`: language-neutral, so every frontend flavour reads the same document. */
export interface KitManifest {
  /** True when the server recognised a signed-in user. Plugin lists are only sent to signed-in users. */
  authenticated: boolean;
  edition: { id: string; name: string };
  plugins: ManifestPlugin[];
  theme: { preset: string; overrides: Partial<ThemeTokens> };
}

export interface ThemeTokens {
  primary: string;
  secondary: string;
  fontFamily: string;
  radius: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
  /** Id of the layout the shell should use (`sidebar` today). */
  layout: string;
  navbarWidth: number;
  headerHeight: number;
}

export interface ResolvedRoute<C> extends RouteDefinition<C> {
  /** Which plugin contributed it; undefined for the host edition. */
  plugin?: string;
}

export interface ResolvedContribution<C> extends SlotContribution<C> {
  plugin?: string;
}

export interface ResolvedKit<C> {
  routes: ResolvedRoute<C>[];
  slots: ReadonlyMap<string, ResolvedContribution<C>[]>;
  widgets: ReadonlyMap<string, C>;
  /** Ids of the plugins that were mounted (not including the host). */
  mounted: string[];
}
