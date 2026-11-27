# Projects (Lasabro.Projects)

Projects, tasks and Kanban boards, milestones, team and client visibility, and task review with approval.

> **Dependency notice:** Lasabro.Projects requires **Lasabro.Core** `^1.0`, **Lasabro.Approvals** `^1.0`, **Lasabro.Clients** `^1.0`, **Lasabro.Files** `^1.0`. It cannot be activated unless Lasabro.Core, Lasabro.Approvals, Lasabro.Clients, Lasabro.Files are installed and active.

## Install

```
composer require lasabro/projects
php artisan migrate
php artisan lasabro:plugin:activate Lasabro.Projects
```

Upgrading an installation that already holds this module's data (it predates the plugin): `php artisan lasabro:plugin:activate Lasabro.Projects --adopt` records the seeders instead of running them again.

Deactivating hides the menu and unloads the routes; data are kept. `php artisan lasabro:plugin:uninstall Lasabro.Projects --purge` is the only command that deletes data.

## Layout

`src/` models, controllers, services, provider - `routes/` - `database/migrations` and `database/seeders` - `resources/lang` and declarations - `tests/`.

Author: Robert MAKUTA - dabani.rm@gmail.com - https://dabani.github.io
