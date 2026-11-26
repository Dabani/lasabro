# Leave (Lasabro.Leave)

Leave policies, statutory minimums, balances and ledger, requests with approval, accrual and year-end jobs, and the leave lookup used by attendance.

> **Dependency notice:** Lasabro.Leave requires **Lasabro.Core** `^1.0`, **Lasabro.Approvals** `^1.0`, **Lasabro.Rules** `^1.0`, **Lasabro.Files** `^1.0`, **Lasabro.Hr** `^1.0`, **Lasabro.Attendance** `^1.0`. It cannot be activated unless Lasabro.Core, Lasabro.Approvals, Lasabro.Rules, Lasabro.Files, Lasabro.Hr, Lasabro.Attendance are installed and active.

## Install

```
composer require lasabro/leave
php artisan migrate
php artisan lasabro:plugin:activate Lasabro.Leave
```

Upgrading an installation that already holds this module's data (it predates the plugin): `php artisan lasabro:plugin:activate Lasabro.Leave --adopt` records the seeders instead of running them again.

Deactivating hides the menu and unloads the routes; data are kept. `php artisan lasabro:plugin:uninstall Lasabro.Leave --purge` is the only command that deletes data.

## Layout

`src/` models, controllers, services, provider - `routes/` - `database/migrations` and `database/seeders` - `resources/lang` and declarations - `tests/`.

Author: Robert MAKUTA - dabani.rm@gmail.com - https://dabani.github.io
