# Turning a module into a plugin (the extraction tool)

`kit/tools/lasabro_extract.py` moves a module of the Enterprise API into `plugins/<slug>/`, rewrites every reference and proves
the result still resolves. It is the same procedure for every module, so each plugin is a reviewable, repeatable commit.

```
git status                                   # must be clean: the extraction is one commit you can undo with `git reset --hard`
python kit/tools/lasabro_extract.py --root . --plan kit/tools/plans/<module>.plan.json
cd editions/enterprise/api && composer update && php artisan migrate && php artisan test
php artisan lasabro:plugin:activate Lasabro.<Name> --adopt       # existing data: do not re-run the seeders
./vendor/bin/pint                                                # code style (import order etc.)
git add -A && git commit -m "Extract Lasabro.<Name>"
```

## The plan file
```json
{
  "id": "Lasabro.Procurement", "slug": "procurement", "namespace": "Procurement", "title": "Procurement",
  "description": "...", "requires": { "Lasabro.Core": "^1.0" }, "suggests": {},
  "declares": ["seeders", "menu"], "menu": ["procurement", "procurement.*"],
  "classes": [ { "from": "app/Models", "include": ["Supplier*"], "to": "src/Models", "ns": "Models" } ],
  "provider": { "from": "app/Providers/ProcurementServiceProvider.php" },
  "routes": { "files": ["routes/procurement.php"] },
  "migrations": ["2026_11_20_000001_*.php"], "lang": ["*/procurement.php"],
  "lang_blocks": { "notify.php": ["po_issued"] },
  "tests": ["tests/Unit/ProcurementRulesTest.php"],
  "host_remove_lines": { "routes/api.php": ["require __DIR__ \\. '/procurement\\.php';"] }
}
```
* `classes`: files of a folder matched by name patterns (`include`, optional `exclude`), placed under `to`, namespace suffix `ns` below `Lasabro\<namespace>`.
* `routes.files` keep their order and their statements; the tool checks they are byte-for-byte the same apart from imports.
* `lang_blocks` moves named blocks out of a *shared* translation group into the plugin's file of the same group (Laravel merges them).
* `declares`: `seeders` (run once on first activation, skipped with `--adopt`), `menu`, `widgets` (hidden while the plugin is inactive).

## What the tool guarantees (and what it does not)
Guaranteed by the run: every reference to project code resolves (`kit/tools/refcheck.php` before and after), no syntax errors (`php -l` on every PHP file),
the class count is unchanged, route statements are identical, the tests that belong to the module moved with it, history is kept (`git mv`).
**Not** proven by the tool: runtime behaviour. Run `php artisan test`, then the fingerprint (`baseline/`), then try the screens.

Run `kit/tools/refcheck.php` yourself any time: `php kit/tools/refcheck.php editions/enterprise/api --extra=plugins --extra=kit/framework/src`.

## Facts to know
* A plugin still uses classes of the application (`App\Models\User`, `App\Services\Approvals\ApprovalEngine`...). The tool prints that list and stores it in
  `baseline/extractions/<slug>.json`. They are the future `Lasabro.Core`/`Approvals`/`Rules`/`Files` plugins; until they are extracted the host
  declares them in `App\Providers\LasabroHostServiceProvider::PROVIDED`, so the dependency notice and the activation checks already work.
* `php artisan migrate` creates the tables of every installed plugin package (active or not); activation switches routes, menu and handlers on.
* Queue: drain it (`php artisan queue:work --stop-when-empty`) before deploying an extraction, because queued jobs store class names.
* Upgrading a live installation: `composer update`, `php artisan migrate` (already-run migrations keep their names and are skipped), then
  `php artisan lasabro:plugin:activate --all --adopt`. Until you activate the plugin its routes are not loaded.
