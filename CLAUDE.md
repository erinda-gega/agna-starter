# CLAUDE.md

Guidance for Claude Code when working in this repository.

## Project Overview

**agna-orders** — an internal, read-only order-tracking tool for a fictional
Albanian FMCG distributor (AGNA), built for the Tirana warehouse distribution
team to replace an old Excel workflow. It covers: looking up orders, checking
delivery status, and pulling invoices. Per the README, it was "put together
fast" and never revisited — a planned admin panel + accounting-export
integration has been "next quarter" indefinitely and does **not** exist.

This is effectively a stock Laravel starter skeleton with a small domain
layer added on top — treat it as a demo/training codebase, not a mature
production app.

## Tech Stack

- **PHP** ^8.2, **Laravel** ^12.0 (`laravel/laravel` skeleton)
- **Testing:** PHPUnit ^11.5 (no Pest)
- **DB (local/test):** SQLite by default (`DB_CONNECTION=sqlite`); MySQL is
  what the README describes for the "real" shared dev box. MySQL/MariaDB/
  Postgres/SQL Server connections are all pre-defined in `config/database.php`
  but unused by default.
- **Frontend:** plain server-rendered **Blade** only — no Vue/React/Inertia,
  no `package.json`, no Vite/Mix build. One hand-written `public/css/app.css`
  and a single inline `onchange` JS handler; that's the entire frontend.
- **No auth scaffolding**: no Breeze/Jetstream/Sanctum/Passport. `User` model
  is stock `Authenticatable` with no domain fields. No login/register routes,
  no `auth`/`guest` middleware anywhere.
- **Dev tooling:** Pint (style), Pail (log tailing), Sail (Docker), Tinker,
  Collision, Faker (factories/seeders), Mockery (tests).

## Directory Structure — where things live

```
app/
  Http/Controllers/     # Only 2: OrderController, InvoiceController (+ base Controller)
  Models/                # Partner, PointOfSale, Brand, Product, Order, OrderLine, Delivery, Invoice, User
  Services/              # DeliveryScheduler, DiscountService, InvoiceTotals — static-method utility classes
  Support/Demo.php       # Shared constants (Albanian cities, brand/partner names, POS types, route
                          #  codes) used by BOTH factories and seeders to keep fixture data consistent
  Providers/AppServiceProvider.php  # Empty boilerplate — nothing registered
routes/
  web.php                # The only routes (3 total, see below); no api.php, no channels.php
  console.php            # Just the default `inspire` command
database/
  migrations/            # Domain tables dated 2026_08_23_*
  factories/             # One per model, used mainly by tests
  seeders/                # PartnerSeeder, PointOfSaleSeeder, BrandSeeder, ProductSeeder, OrderSeeder
resources/views/
  layouts/app.blade.php  # Single master layout (@yield title/content)
  orders/index.blade.php
  invoices/show.blade.php
public/css/app.css       # One plain 64-line stylesheet, no preprocessor
tests/Feature/           # OrdersTest.php, PartnersTest.php — the ONLY tests; no tests/Unit
docs/exercises/          # Training briefs (not app docs) — reference known issues like the discount bug below
```

**There is no `app/Http/Middleware` directory** — default Laravel middleware
lives wherever Laravel 11+ style `bootstrap/app.php` puts it; nothing custom
is registered.

## Architecture / Conventions (as actually practiced)

- **Standard MVC monolith**, server-rendered Blade — no API layer, no SPA,
  no JSON responses anywhere.
- **Thin controllers, fat static services.** Business logic lives in
  `App\Services\*` as classes with only `public static` methods — not bound
  in the container, not instantiated, no interfaces/contracts, no repository
  pattern. Call them directly as `DiscountService::rateFor(...)`.
- **No form requests, no `$request->validate()`** in the existing
  controllers — `OrderController` reads input via `$request->integer()` /
  `->string()` fluent accessors instead.
- **Money is always integer cents**, never floats: `unit_price_cents`,
  `discount_cents_per_unit`, `total_cents`. Preserve this convention in any
  new money-related columns/logic.
- **Enums via plain string columns**: `partners.tier` (`a`/`b`/`c`),
  `orders.status` (`open` → `delivered` → `invoiced`, default `open`).
- Standard Eloquent naming: snake_case plural tables, `id` PKs,
  `{singular}_id` FKs via `constrained()->cascadeOnDelete()`.
- Routes are named with dot notation (`orders.index`, `invoices.show`); no
  route groups/prefixes/middleware declared explicitly beyond the implicit
  `web` group.
- Tests use PHPUnit's `test_`-prefixed snake_case method names (not
  `#[Test]` attributes) for discovery, plus `RefreshDatabase` and model
  factories — no Pest, no custom test helper traits.

## Data Model

```
Partner (tier: a/b/c, city)
  └─ hasMany PointOfSale
  └─ hasMany Order
PointOfSale (belongsTo Partner)
  └─ hasMany Order
Brand
  └─ hasMany Product (sku unique, unit_price_cents)
Order (status: open/delivered/invoiced, ordered_at)
  ├─ belongsTo Partner, PointOfSale
  ├─ hasMany OrderLine
  ├─ hasOne Delivery
  └─ hasOne Invoice
OrderLine (qty, unit_price_cents snapshot, discount_cents_per_unit)
  └─ belongsTo Order, Product
Delivery (scheduled_for date, delivered_at nullable, route_code)
Invoice (number unique "INV-######", issued_at, total_cents)
```

- `orders.status` drives a pipeline: `delivery`/`invoice` rows only get
  created once an order progresses past `open`.
- `OrderSeeder` generates ~1,500 orders over a 6-month window with a
  time-based probability model so recent orders skew "open" and older ones
  resolve further along the pipeline; it also seeds ~30 deliberately "stuck"
  orders (overdue, undelivered) to simulate operational backlog. It calls
  `Model::create()` directly rather than using factories, for tight control
  over pipeline consistency.
- Demo/fixture data is intentionally Albanian-flavored (via
  `App\Support\Demo` constants) — e.g. brands like "Dukagjini Foods",
  products like "Fresh Milk 1L", SKUs like `DUK-001`.

## Routes (all of them)

| Method | URI | Action | Name |
|---|---|---|---|
| GET | `/` | redirect → `/orders` | — |
| GET | `/orders` | `OrderController@index` (filterable by `partner_id`, `status`; paginated) | `orders.index` |
| GET | `/invoices/{invoice}` | `InvoiceController@show` (implicit route-model binding) | `invoices.show` |

Both are plain read-only GET endpoints returning Blade views.

## Running the App / Tests

```bash
composer install
cp .env.example .env && php artisan key:generate   # if not already set up
php artisan migrate --seed                         # seeders run in dependency order via DatabaseSeeder
php artisan serve
```

- README states the "real" setup expects a **MySQL** connection (credentials
  via a shared dev box, undocumented in-repo) and that point-of-sale data
  arrives via an external nightly import job — if POS data looks stale,
  that's the first thing to suspect (not this app's code).
- **Tests:** `composer test` (runs `artisan config:clear` then
  `php artisan test`), or `vendor/bin/phpunit` directly. Tests run against
  in-memory SQLite regardless of your `.env` DB driver (set in `phpunit.xml`).
- Only `tests/Feature` exists (`OrdersTest.php`, `PartnersTest.php`), both
  exercising `/orders` (listing, filters, pagination, partner dropdown data).
  There is no `tests/Unit` suite — no direct tests of `DiscountService`,
  `DeliveryScheduler`, or `InvoiceTotals` exist yet.

## Gotchas / Known Issues

- **`DiscountService::rateFor()` off-by-one bug — fixed.** The docblock says
  12% applies "from 1,000 units up"; the code used to check
  `if ($units > 1000)`, giving an order of *exactly* 1,000 units 7% instead
  of 12%. Now uses `>= 1000` to match the docblock. (Also the subject of a
  training exercise under `docs/exercises/` — that doc describes the old
  buggy behavior.)
- **`DeliveryScheduler::nextSlot()` only skips Saturday, not Sunday** — a
  delivery slot landing on Sunday is not pushed forward. Confirm this is
  intended before relying on it.
- **No validation layer** on the one controller that accepts input
  (`OrderController`) — filters are read via fluent `Request` accessors, not
  `FormRequest`/`validate()`. Don't assume standard Laravel validation
  patterns are in use.
- **No auth** at all — don't add auth-gated features without first adding an
  auth stack; nothing currently protects any route.
- Services are static-only; there's no DI/container binding to mock in tests
  — call/stub them directly if writing unit tests.
- `docs/exercises/` contains training briefs, not authoritative project
  documentation — useful for spotting known issues but not a source of truth
  for intended behavior.
- The README's claim of "read-only pages" and "no admin panel" should be
  re-verified against `routes/web.php` if this file is regenerated later,
  since new routes/controllers could have been added since.
