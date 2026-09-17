# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A small Laravel 12 app ("agna-orders") for AGNA, a fictional FMCG distributor operating out of Tirana, Albania. It's read-only: an orders list and an invoice view, no admin/write UI. `docs/exercises/` holds workshop briefs used to teach Claude Code workflows on this codebase — they aren't part of the app itself.

## Commands

```
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve
```

Note: `.env.example` and `phpunit.xml` both default to SQLite; the README's mention of a MySQL dev box does not reflect the current config in this checkout.

Tests:

```
php artisan test                                  # full suite
php artisan test tests/Feature/OrdersTest.php      # single file
php artisan test --filter=test_orders_index_can_filter_by_status
```

Code style: `vendor/bin/pint` (Laravel Pint).

## Architecture

**Domain chain:** `Partner` → `PointOfSale` → `Order` → `OrderLine` → `Product` → `Brand`. An `Order` also has one `Delivery` and one `Invoice`.

**Controllers are thin and read-only.** `OrderController::index` builds a filtered/paginated Eloquent query straight from the request (`partner_id`, `status`) and hands it to a Blade view; `InvoiceController::show` eager-loads and renders. There's no API layer — just the two routes in `routes/web.php` and server-rendered views in `resources/views` (no JS build step, no `package.json`).

**Business logic lives in `app/Services` as stateless static methods**, not injected services: `DiscountService::rateFor()` (volume discount tiers), `DeliveryScheduler::nextSlot()` (next weekday after order date), `InvoiceTotals::total()` (line totals minus per-line discount, then partner discount rate). Read `InvoiceTotals` alongside `DiscountService` — the invoice total depends on the discount tier.

**Money is stored as integer cents** — columns ending in `_cents` (`unit_price_cents`, `discount_cents_per_unit`, `total_cents`). Never introduce float currency math.

**`app/Support/Demo.php`** centralizes fixture data (Albanian cities, fictional brand/partner names, route codes) so factories and seeders produce a consistent demo dataset from one source.

## Testing

Only `tests/Feature` exists (no `tests/Unit`) — tests hit routes via `$this->get(...)` against a real (SQLite in-memory) database with `RefreshDatabase`, not unit-isolated service tests.
