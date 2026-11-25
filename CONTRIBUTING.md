# Contributing to LASABRO

Code, translations, documentation, bug reports and real-world feedback are all welcome.

## Workflow
1. Open or pick an issue (`good first issue`, `help wanted`) so the idea is discussed before the work.
2. Fork, then branch: `feature/short-name`, `fix/short-name`, `docs/short-name`.
3. Keep the change focused; one concern per pull request.
4. Run the checks for the part you touched (below), then open the pull request. State **what changed, why, test evidence,
   migrations, and screenshots (phone and desktop) for visual changes**.

## Rules every change must respect
* **Authorize on the server.** Hiding a button is not a control; every route declares its permission and data scope.
* **Tenant data carries `organization_id`** and uses the organization scope.
* **No database ENUM or JSON columns** for business structures: use lookup tables.
* **Audit** sensitive reads, exports, approvals and state changes.
* **Translate every user-facing string** in `en`, `fr`, `rw`, `sw` (server and web).
* **Money, time, files** go through the shared services (`MoneyService`, UTC storage, Controlled Files).
* **Web:** responsive from 360 px, keyboard accessible, lazy-loaded screens, first-load JavaScript within 400 KB gzipped.
* **Comments explain why, not history.** No "added in bundle 7" notes; the git log keeps history.
* **Plugins** (once extracted) own their controllers, models, migrations, services, policies and permissions, declare their
  dependencies in the manifest, and never reach into another plugin except through its documented contracts.

## Checks
```
# API   (editions/enterprise/api)
php artisan test        ./vendor/bin/pint --test
# Web   (editions/enterprise/web)
npm run typecheck && npm test && npm run build:check
# Behaviour surface (after a seeded fresh migration)
php artisan lasabro:fingerprint --compare=../../../baseline/cops-pre-lasabro.json
```

## Commit and PR conventions
Imperative, present tense subject under 72 characters ("Add leave balance policy"). Reference the issue (`Closes #12`).
By contributing you agree your work is released under the repository's MIT licence.
