# Writing a LASABRO plugin

A plugin is a Composer package of type `lasabro-plugin` with a manifest in `extra.lasabro`. Nomenclature: **`Lasabro.PluginName`**
(display id and manifest id), `lasabro/plugin-name` (Composer), `Lasabro\PluginName` (PHP namespace), `@lasabro/plugin-name-react` (npm).

## Folder
```
plugins/hr/
├─ composer.json
├─ src/HrServiceProvider.php
├─ routes/api.php            authenticated, tenant-scoped (middleware from config/lasabro.php)
├─ routes/public.php         open; declare throttling inside
├─ database/migrations/
├─ resources/lang/<locale>/  merged into the application's translation groups (keys do not change)
├─ resources/permissions.php menu.php widgets.php lookups.php     declarations, one file per declared type
├─ tests/
└─ docs/
```

## composer.json
```json
{
  "name": "lasabro/hr",
  "type": "lasabro-plugin",
  "license": "MIT",
  "require": { "php": "^8.3", "lasabro/framework": "^1.0" },
  "autoload": { "psr-4": { "Lasabro\\Hr\\": "src/" } },
  "extra": { "lasabro": {
    "id": "Lasabro.Hr",
    "title": "Human Resources",
    "tier": "domain",
    "provider": "Lasabro\\Hr\\HrServiceProvider",
    "requires": { "Lasabro.Core": "^1.0", "Lasabro.Approvals": "^1.0" },
    "suggests": { "Lasabro.Leave": "leave balances in attendance" },
    "frontends": { "react": "@lasabro/hr-react" },
    "declares": ["permissions", "menu"]
  } }
}
```
* `requires` is hard: the plugin cannot be activated, and the dependency cannot be deactivated, while this is not satisfied.
  The engine states it in plain words (`Lasabro.Hr requires Lasabro.Core ^1.0 and Lasabro.Approvals ^1.0`). Put the same sentence in your README
  (or run `lasabro:plugin:docs --write`).
* `suggests` is soft. Integrate through a contract (an interface in your plugin with a null default implementation) so the plugin works alone and
  gains features when the other one is active.
* Development checkouts (`dev-main`) satisfy every constraint.

## Provider
```php
namespace Lasabro\Hr;

use Lasabro\Framework\PluginServiceProvider;

class HrServiceProvider extends PluginServiceProvider
{
    protected array $policies = [Employee::class => EmployeePolicy::class];

    protected function registerPlugin(): void { /* bind services and contracts */ }
    protected function bootPlugin(): void { /* schedule tasks, observers, listeners */ }
}
```
Migrations, routes, translations and policies that exist are loaded automatically.

## Rules a plugin follows
1. Own your controllers, models, services, policies, migrations, permissions, translations and tests.
2. Never reference another plugin's classes unless it is listed in `requires`; otherwise go through a contract.
3. Tables keep `organization_id` and the tenant scope; no ENUM or JSON columns; lookups instead.
4. Permission codes are stable forever: renaming one breaks every role that has it.
5. Deactivation hides, it never deletes. Only `lasabro:plugin:uninstall --purge` removes data.
