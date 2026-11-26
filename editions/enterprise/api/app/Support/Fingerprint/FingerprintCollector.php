<?php

namespace App\Support\Fingerprint;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Captures a deterministic, id-free description of what this installation exposes:
 * HTTP routes and their middleware, permissions, role grants, menu, dashboard widgets, lookups,
 * database structure, console commands, scheduled tasks and the extension registries.
 *
 * It is the safety net of the LASABRO refactor: capture it before moving code, capture it again
 * after, and `lasabro:fingerprint --compare` proves that nothing a user or an integration can
 * see has changed. Run it against a freshly migrated and seeded scratch database
 * (`php artisan migrate:fresh --seed`), never against production data.
 */
final class FingerprintCollector
{
    /** Console commands defined by this codebase (framework and vendor commands are not part of the fingerprint). */
    private const COMMAND_NAMESPACES = ['App\\', 'Lasabro\\'];

    /** Tables whose structure is framework bookkeeping rather than product schema. */
    private const IGNORED_TABLES = ['migrations'];

    /** @return array{meta: array<string, mixed>, sections: array<string, list<string>>} */
    public function collect(): array
    {
        return [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'driver' => DB::connection()->getDriverName(),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
            ],
            'sections' => [
                'routes' => $this->routes(),
                'route_actions' => $this->routeActions(),
                'permissions' => $this->permissions(),
                'roles' => $this->roles(),
                'menu' => $this->menu(),
                'widgets' => $this->widgets(),
                'lookups' => $this->lookups(),
                'schema' => $this->schema(),
                'commands' => $this->commands(),
                'schedule' => $this->schedule(),
                'registries' => $this->registries(),
            ],
        ];
    }

    /** One line per method + URI with its middleware (permissions included). Controller names are deliberately left out. */
    private function routes(): array
    {
        $lines = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $lines[] = sprintf('%s %s | %s', $method, $route->uri(), $this->middleware($route));
            }
        }

        return $this->normalise($lines);
    }

    /** Informational: which controller method serves each route (changes when code moves to a plugin). */
    private function routeActions(): array
    {
        $lines = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $lines[] = sprintf('%s %s => %s', $method, $route->uri(), $this->shortAction($route->getActionName()));
            }
        }

        return $this->normalise($lines);
    }

    private function middleware($route): string
    {
        $parts = [];
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                $parts[] = 'closure';

                continue;
            }
            [$name, $args] = array_pad(explode(':', $middleware, 2), 2, null);
            // Class-based middleware may move between namespaces; compare by short name.
            $name = str_contains($name, '\\') ? class_basename($name) : $name;
            $parts[] = $args === null ? $name : $name.':'.$args;
        }
        sort($parts);

        return implode(',', $parts);
    }

    private function shortAction(string $action): string
    {
        if ($action === 'Closure') {
            return 'Closure';
        }
        [$class, $method] = array_pad(explode('@', $action, 2), 2, '__invoke');

        return class_basename($class).'@'.$method;
    }

    private function permissions(): array
    {
        if (! Schema::hasTable('permissions')) {
            return ['!missing table permissions'];
        }
        $rows = DB::table('permissions as p')
            ->leftJoin('lookup_values as g', 'g.id', '=', 'p.group_id')
            ->orderBy('p.code')
            ->get(['p.code', 'p.name', 'g.code as grp']);

        return $this->normalise($rows->map(fn ($r) => "{$r->code} | {$r->grp} | {$r->name}")->all());
    }

    /** Role grants per organization (organizations are ordered by creation; ids never appear). */
    private function roles(): array
    {
        if (! Schema::hasTable('roles')) {
            return ['!missing table roles'];
        }
        $lines = [];
        $orgs = DB::table('organizations')->orderBy('created_at')->orderBy('id')->pluck('id')->values();
        foreach ($orgs as $i => $orgId) {
            $roles = DB::table('roles')->where('organization_id', $orgId)->orderBy('code')->get(['id', 'code', 'level', 'is_system']);
            foreach ($roles as $role) {
                $codes = DB::table('role_permissions as rp')
                    ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                    ->where('rp.role_id', $role->id)->orderBy('p.code')->pluck('p.code')->all();
                $lines[] = sprintf('org#%d role:%s level:%s system:%s => %s', $i + 1, $role->code, $role->level, (int) $role->is_system, implode(',', $codes));
            }
        }

        return $this->normalise($lines);
    }

    private function menu(): array
    {
        if (! Schema::hasTable('menu_items')) {
            return ['!missing table menu_items'];
        }
        $rows = DB::table('menu_items as m')
            ->leftJoin('menu_items as parent', 'parent.id', '=', 'm.parent_id')
            ->leftJoin('permissions as p', 'p.id', '=', 'm.permission_id')
            ->orderBy('m.code')
            ->get(['m.code', 'parent.code as parent', 'm.icon', 'm.route', 'p.code as perm', 'm.sort_order', 'm.label_key', 'm.is_active']);

        return $this->normalise($rows->map(fn ($r) => sprintf(
            '%s | parent:%s | icon:%s | route:%s | perm:%s | order:%s | label:%s | active:%d',
            $r->code, $r->parent, $r->icon, $r->route, $r->perm, $r->sort_order, $r->label_key, (int) $r->is_active
        ))->all());
    }

    private function widgets(): array
    {
        if (! Schema::hasTable('dashboard_widgets')) {
            return ['!missing table dashboard_widgets'];
        }
        $rows = DB::table('dashboard_widgets as w')
            ->leftJoin('lookup_values as t', 't.id', '=', 'w.widget_type_id')
            ->leftJoin('permissions as p', 'p.id', '=', 'w.permission_id')
            ->orderBy('w.code')
            ->get(['w.code', 't.code as type', 'p.code as perm', 'w.default_width', 'w.icon', 'w.is_active']);

        return $this->normalise($rows->map(fn ($r) => sprintf(
            '%s | type:%s | perm:%s | width:%s | icon:%s | active:%d',
            $r->code, $r->type, $r->perm, $r->default_width, $r->icon, (int) $r->is_active
        ))->all());
    }

    /** Global (non-tenant) lookup values per lookup type. */
    private function lookups(): array
    {
        if (! Schema::hasTable('lookup_types')) {
            return ['!missing table lookup_types'];
        }
        $lines = [];
        foreach (DB::table('lookup_types')->orderBy('code')->get(['id', 'code']) as $type) {
            $values = DB::table('lookup_values')->where('lookup_type_id', $type->id)->whereNull('organization_id')
                ->orderBy('code')->pluck('code')->all();
            $lines[] = $type->code.' => '.implode(',', $values);
        }

        return $this->normalise($lines);
    }

    /** Tables, columns, indexes (by columns) and foreign keys. Compare only between the same database driver. */
    private function schema(): array
    {
        $lines = [];
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if (in_array($name, self::IGNORED_TABLES, true)) {
                continue;
            }
            $lines[] = "table {$name}";
            foreach (Schema::getColumns($name) as $c) {
                $lines[] = sprintf('%s.%s | %s | %s | default:%s', $name, $c['name'], $c['type'], $c['nullable'] ? 'null' : 'not null', var_export($c['default'] ?? null, true));
            }
            foreach (Schema::getIndexes($name) as $i) {
                $lines[] = sprintf('%s#index | %s | %s', $name, implode(',', $i['columns']), $i['primary'] ? 'primary' : ($i['unique'] ? 'unique' : 'index'));
            }
            foreach (Schema::getForeignKeys($name) as $f) {
                $lines[] = sprintf('%s#fk | %s -> %s(%s) | delete:%s update:%s', $name, implode(',', $f['columns']), $f['foreign_table'], implode(',', $f['foreign_columns']), $f['on_delete'], $f['on_update']);
            }
        }

        return $this->normalise($lines);
    }

    private function commands(): array
    {
        $lines = [];
        foreach (Artisan::all() as $name => $command) {
            foreach (self::COMMAND_NAMESPACES as $ns) {
                if (str_starts_with($command::class, $ns)) {
                    $lines[] = $name;
                    break;
                }
            }
        }

        return $this->normalise($lines);
    }

    private function schedule(): array
    {
        $lines = [];
        foreach (app(Schedule::class)->events() as $event) {
            $what = $event->command ? preg_replace("/^.*artisan'?\\s+/", '', $event->command) : ($event->description ?: 'closure');
            $lines[] = $event->expression.' | '.$what;
        }

        return $this->normalise($lines);
    }

    /** Extension registries filled by service providers: approval handlers/permissions and code prefixes. */
    private function registries(): array
    {
        $lines = [];
        foreach (array_keys((array) config('cops.approval_handlers', [])) as $type) {
            $lines[] = "approval_handler {$type}";
        }
        foreach ((array) config('cops.approval_permissions', []) as $type => $permission) {
            $lines[] = "approval_permission {$type} => {$permission}";
        }
        foreach ((array) config('cops.code_prefixes', []) as $entity => $prefix) {
            $lines[] = "code_prefix {$entity} => {$prefix}";
        }

        return $this->normalise($lines);
    }

    /** @param list<string> $lines */
    private function normalise(array $lines): array
    {
        $lines = array_values(array_unique($lines));
        sort($lines);

        return $lines;
    }
}
