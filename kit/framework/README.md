# lasabro/framework

The plugin engine of the LASABRO startup kit for Laravel. It discovers plugins, installs, activates and deactivates them,
checks and explains their dependencies, syncs what they declare (permissions, menu, widgets, lookups) and boots only the
active ones. With no plugin activated an application behaves exactly as it did without it.

Requires PHP 8.3+, Laravel 12 or 13. MIT licence. Author: Robert MAKUTA (dabani.rm@gmail.com, https://dabani.github.io).

## Commands
| Command | Does |
|---|---|
| `lasabro:plugin:list` | plugins available here, tier, version, state, requirements |
| `lasabro:plugin:info Lasabro.Hr` | details and the **dependency notice** |
| `lasabro:plugin:install Lasabro.Hr` | run migrations of the plugin and what it requires; stays off |
| `lasabro:plugin:activate Lasabro.Hr [--with-dependencies] [--all]` | switch on; refuses with a clear message if requirements are not met |
| `lasabro:plugin:deactivate Lasabro.Hr [--cascade]` | switch off, data kept; refused while another active plugin needs it |
| `lasabro:plugin:uninstall Lasabro.Hr [--purge]` | forget it; `--purge` also deletes its tables (asks first) |
| `lasabro:plugin:discover` | rebuild the boot cache (after `composer update` or a deploy) |
| `lasabro:plugin:docs [--write]` | generate `docs/DEPENDENCIES.md` for every plugin |

Example of the notice:
`Lasabro.Payroll requires Lasabro.Hr ^1.0, Lasabro.Attendance ^1.0 and Lasabro.Rules ^1.0. Right now: Lasabro.Hr is installed but inactive.`

## How it fits together
```
composer.json (extra.lasabro)  ->  Manifest  ->  DependencyResolver  ->  PluginManager  ->  lasabro_plugins (state, history)
                                                                              |            lasabro_plugin_resources (ownership)
                                                                              +--> bootstrap/cache/lasabro.php --> providers booted
```
* `Plugin\*` is plain PHP with no Laravel dependency and is covered by unit tests (`vendor/bin/phpunit`).
* `Support\*` and the provider/commands are the Laravel adapters (database stores, `migrate` runner, console).
* Declarations (`resources/permissions.php`, `menu.php`...) are applied by a `DeclarationHandler` registered per type;
  `Lasabro.Core` registers the handlers for the application's tables. Without a handler a declaration is reported, not lost.

Writing a plugin: see [docs/PLUGIN-DEVELOPMENT.md](../../docs/PLUGIN-DEVELOPMENT.md).
