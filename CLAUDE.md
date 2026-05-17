# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel 12 REST API (PHP 8.3) for generating and submitting electronic invoices (comprobantes electrónicos) to SUNAT (Peru's tax authority). Uses the [greenter](https://greenter.dev) PHP library ecosystem for UBL 2.1 XML generation, digital signing, and SUNAT SOAP communication. Multi-tenant: each user manages their own companies and invoices.

## Common Commands

| Task | Command |
|---|---|
| Full setup | `composer run setup` |
| Dev server | `composer run dev` (Laravel + queue + Vite via concurrently) |
| Run all tests | `composer run test` or `php artisan test` |
| Run single test | `php artisan test --filter=TestName` |
| Lint/format | `./vendor/bin/pint` |
| Migrations | `php artisan migrate` |
| Frontend build | `npm run build` |
| Frontend dev | `npm run dev` |

Tests use PHPUnit 11 with in-memory SQLite (`phpunit.xml` overrides DB to `sqlite/:memory:`).

## Architecture

### API Structure (all routes in `routes/api.php`)

- **Auth** (`/register`, `/login`, `/logout`, `/refresh`, `/me`) — public, Sanctum token-based
- **Companies** — `apiResource` CRUD under `auth:sanctum`, uses `{ruc}` as route key (not `{id}`)
- **Invoices** (`/send`, `/xml`, `/pdf`) — under `auth:sanctum`

### Key Layers

- **Controllers** (`app/Http/Controllers/Api/`) — `AuthController`, `RegisterController`, `CompanyController`, `InvoiceController`. Validation is inline (`$request->validate()`), no Form Request classes.
- **Service** (`app/Services/SunatService.php`) — Core integration with greenter library. Builds `See`, `Invoice`, `Client`, `Company`, `SaleDetail`, `Legend` objects. Handles SUNAT submission and HTML report generation.
- **Models** (`app/Models/`) — `User` (HasApiTokens) and `Company` (belongsTo User). Company uses `ruc` as route model binding key.
- **Custom Rule** (`app/Rules/UniqueRucRule.php`) — Validates RUC uniqueness per user (not globally).

### Greenter Library Integration

- `greenter/lite` — SUNAT communication (`See` class, `SunatEndpoints`)
- `greenter/report` — HTML/PDF report generation
- `greenter/htmltopdf` + `barryvdh/laravel-dompdf` — PDF rendering
- `luecano/numero-a-letras` — Converts amounts to Spanish words for invoice legends

### Tax Calculation (`InvoiceController::setTotales()`)

IGV type codes follow SUNAT Catalog 07: 10=Gravado, 20=Exonerado, 30=Inafecto, 40=Exportación, others=Gratuitas. Rounding uses `floor(x * 10) / 10`.

### File Storage

- Company logos: `storage/app/logos/`
- Certificates (.pfx/.pem): `storage/app/certs/`

## Conventions

- **Code style**: PSR-12 via Laravel Pint, 4-space indentation
- **Naming**: Spanish for fiscal/business fields (`razon_social`, `ruc`, `sol_user`, `tipoDoc`, `mtoIGV`); English for framework patterns and method names
- **Auth**: Sanctum Bearer tokens — created on login/register, deleted on logout, replaced on refresh
- **Database**: MySQL (`greenter` DB on Laragon), user `root`, no password
- **MCP**: Laravel Boost MCP server configured (`.mcp.json`)
