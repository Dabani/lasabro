<p align="center"><img src="brand/logo-horizontal-light.svg" alt="LASABRO Enterprise" width="420"></p>

# LASABRO

An open-source, modular, multilingual (English, French, Kinyarwanda, Swahili) application platform built on
**Laravel** and **React + TypeScript**. Business modules are **plugins** (`Lasabro.Hr`, `Lasabro.Procurement`, ...) that can be
installed, activated, deactivated and moved between **editions**. This repository contains the startup kit, the plugins
and the first edition, **LASABRO Enterprise** (formerly COPS, the Company Operations Platform). Future editions (Sports, Medical,
Education, Pharmacy...) reuse the same kit and the plugins that fit their sector.

> **Status:** the code of the two original COPS repositories is imported unchanged under `editions/enterprise`.
> The kit and the plugin extraction are delivered bundle by bundle; see [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Layout
```
brand/                logos, icons, colours
kit/                  startup kit (framework, contracts, web core, starter)      <- bundles 02-03, 10
plugins/              one folder per plugin (Lasabro.Name)                      <- bundles 04-09
editions/enterprise/  api/ (Laravel 13, PHP 8.4)   web/ (React 19, Mantine 9, Vite PWA)
baseline/             fingerprint of the pre-refactor behaviour (refactoring safety net)
docs/                 architecture, guides
```

## Run LASABRO Enterprise today
```
# API
cd editions/enterprise/api && composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan serve
# Web (second terminal)
cd editions/enterprise/web && npm ci && npm run dev
```
Requirements: PHP 8.4, Composer 2, MySQL 8.4, Node 24. Detailed notes: `editions/enterprise/api/README.md` and `editions/enterprise/web/README.md`.

## Contributing
Read [CONTRIBUTING.md](CONTRIBUTING.md) and [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). Report vulnerabilities privately: [SECURITY.md](SECURITY.md).

## Author and licence
Created by Robert MAKUTA (dabani.rm@gmail.com, https://dabani.github.io). Released under the [MIT licence](LICENSE).
