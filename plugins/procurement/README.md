# Procurement (Lasabro.Procurement)

Suppliers, purchase requisitions, requests for quotation, purchase orders, goods receipts and supplier invoices, with approval flows and controlled documents.

> **Dependency notice:** Lasabro.Procurement requires **Lasabro.Core** `^1.0`, **Lasabro.Approvals** `^1.0`, **Lasabro.Rules** `^1.0`, **Lasabro.Files** `^1.0`. It cannot be activated unless Lasabro.Core, Lasabro.Approvals, Lasabro.Rules, Lasabro.Files are installed and active.

## Install

```
composer require lasabro/procurement
php artisan migrate
php artisan lasabro:plugin:activate Lasabro.Procurement
```

Upgrading an installation that already holds this module's data (it predates the plugin): `php artisan lasabro:plugin:activate Lasabro.Procurement --adopt` records the seeders instead of running them again.

Deactivating hides the menu and unloads the routes; data are kept. `php artisan lasabro:plugin:uninstall Lasabro.Procurement --purge` is the only command that deletes data.

## Layout

`src/` models, controllers, services, provider - `routes/` - `database/migrations` and `database/seeders` - `resources/lang` and declarations - `tests/`.

Author: Robert MAKUTA - dabani.rm@gmail.com - https://dabani.github.io
