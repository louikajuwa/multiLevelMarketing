# Code Architecture & Debugging Guide

## Overview

**Layering:** Request → Route → Controller → Service → Model → Database

Business logic lives in the **Service layer** (`app/Services/`), not in controllers. Controllers orchestrate and handle HTTP concerns; services implement domain rules.

**Key files for debugging:**
- Commission math: `app/Services/CommissionService.php`
- Discount/pricing: `app/Services/PricingService.php` + `app/Services/PartnerService.php`
- Partner genealogy: `app/Services/PartnerService.php` (materialized path logic)
- Order placement & stock: `app/Services/OrderService.php`
- Audit trail: `app/Models/Concerns/Auditable.php` + `app/Services/AuditService.php`
- Real-time events: `app/Services/RealtimeService.php` + `socket-server/index.js`

---

## Route → Controller → View → Model/Service Map

| Route(s) | Controller | Method | View | Models/Services |
|----------|-----------|--------|------|-----------------|
| `GET/POST login`, `POST logout` | `Auth\AuthController` | `showLogin`, `login`, `logout` | `auth.login` | `User` |
| `GET/POST forgot-password`, `POST reset-password/{token}` | `Auth\PasswordController` | `showForgotPassword`, `sendReset`, `showReset`, `reset` | `auth.forgot-password`, `auth.reset-password` | `User` |
| `GET/PUT profile`, `PUT profile/password` | `Auth\ProfileController` + `PasswordController` | `edit`, `update`, `changePassword` | `profile.edit` | `User` |
| `GET dashboard` | `DashboardController` | `index` | `dashboard` | `Order`, `Partner`, `Customer`, `Commission`, `Product` |
| `GET/POST customers`, `PUT customers/{customer}`, `DELETE customers/{customer}` | `CustomerController` | `index`, `store`, `update`, `destroy` | `customers.index` | `Customer`, `Partner`, `User` |
| `GET customers/partner-options` | `CustomerController` | `partnerOptions` | — (JSON) | `Partner` (searchable options) |
| `GET/POST orders`, `GET orders/{order}`, `PUT orders/{order}/status` | `OrderController` | `index`, `store`, `show`, `updateStatus` | `orders.index` | `Order`, `OrderItem`, `Payment`, `Customer`, `Partner`, `Product`, `OrderService`, `PricingService`, `CommissionService` |
| `GET orders/customer-options`, `GET orders/product-options` | `OrderController` | `customerOptions`, `productOptions` | — (JSON) | `Customer` (scoped via `visibleTo`), `Product` (`active`+`inStock`) |
| `GET commissions`, `POST commissions/adjust` | `CommissionController` | `index`, `adjust` | `commissions.index` | `Commission`, `Partner`, `Order`, `CommissionService` |
| `GET commissions/partner-options` | `CommissionController` | `partnerOptions` | — (JSON) | `Partner` (searchable options) |
| `GET tree` / `GET tree/nodes?q=&parent=&page=` | `TreeController` | `index`, `nodes` | `tree.index` | `Partner`, `Customer`, `MlmTreeService` (paginated: roots / children / flat search) |
| `GET reports`, `GET reports/export` | `ReportController` | `index`, `export` | `reports.index` | `Order`, `Commission`, `Partner`, `Customer`, `Product`, `ReportService`, `GenericExport` |
| `GET/POST admin/partners`, `PUT/DELETE admin/partners/{partner}` | `Admin\PartnerController` | `index`, `store`, `update`, `destroy` | `admin.partners.index` | `Partner`, `User`, `PartnerLevel`, `PartnerService` |
| `GET admin/partners/sponsor-options` | `Admin\PartnerController` | `sponsorOptions` | — (JSON) | `Partner` (searchable options) |
| `GET/POST admin/partner-levels`, `PUT/DELETE admin/partner-levels/{level}` | `Admin\PartnerLevelController` | `index`, `store`, `update`, `destroy` | `admin.partner-levels.index` | `PartnerLevel` |
| `GET/POST admin/products`, `PUT/DELETE admin/products/{product}` | `Admin\ProductController` | `index`, `store`, `update`, `destroy` | `admin.products.index` | `Product`, `Category`, `Brand`, `ProductImage` |
| `GET admin/products/category-options`, `GET admin/products/brand-options` | `Admin\ProductController` | `categoryOptions`, `brandOptions` | — (JSON) | `Category`, `Brand` (searchable options) |
| `GET/POST admin/brands`, `PUT/DELETE admin/brands/{brand}` | `Admin\BrandController` | `index`, `store`, `update`, `destroy` | `admin.brands.index` | `Brand` |
| `GET/POST admin/categories`, `PUT/DELETE admin/categories/{category}` | `Admin\CategoryController` | `index`, `store`, `update`, `destroy` | `admin.categories.index` | `Category` |
| `GET admin/categories/parent-options` | `Admin\CategoryController` | `parentOptions` | — (JSON) | `Category` (searchable options) |
| `GET admin/settings`, `PUT admin/settings` | `SettingController` | `index`, `update` | `admin.settings.index` | `Setting`, `SettingService` |
| `GET admin/audit` | `AuditLogController` | `index` | `admin.audit.index` | `AuditLog` |
| `GET notifications`, `POST notifications/{id}/mark-read` | `NotificationController` | `index`, `markRead` | — (JSON/AJAX) | `Notification` (Laravel's) |
| `GET realtime/token` | `RealtimeController` | `token` | — (JSON) | `User`, `RealtimeService` |

---

## Frontend: SearchableSelect widget

**File:** `public/assets/js/core/searchable-select.js` (registered in `resources/views/layouts/app.blade.php`, after `table.js`).

Replaces every large `<select>`. jQuery class; config `{ input, hidden, url, map, perPage=10, placeholder, allowClear }`.

- Debounced (250ms) AJAX to an options `url` with `?search=&per_page=&page=`; renders `res.data.map(cfg.map)`; a "Load more…" row appends the next page when `res.meta.current_page < res.meta.last_page`.
- Selecting an option writes the id into the **hidden `<input name=…>`** (so plain `form.serialize()` still submits it) and **fires `change`** on it — which is how it also drives a `DataTable` filter (point the filter at the hidden input's id).
- Methods: `setValue(id,label)` (used on edit forms), `clear()`, `reset()`.

**Wiring per blade:** a visible text input + a hidden input; init in `@push('scripts')`. Examples: `customers/index.blade.php` (partner), `orders/index.blade.php` (customer + one instance per product line, created in `addLine()`), `commissions/index.blade.php` (partner), `admin/partners/index.blade.php` (sponsor), `admin/products/index.blade.php` (category/brand — form **and** filter bar), `admin/categories/index.blade.php` (parent).

## Options endpoints (feed the widget)

Thin controller methods, each returns `$this->paginate($query->paginate($this->optionLimit($request), [...]))`. `Controller::optionLimit()` (base `app/Http/Controllers/Controller.php`) defaults `per_page` to 10, hard-caps 100. Response shape is the standard `{ data, meta }` envelope (`ApiResponse::paginate`).

| Route name | Method | Guard | Notes |
|---|---|---|---|
| `customers.partner-options` | `CustomerController@partnerOptions` | `customers.create` | staff-only picker |
| `orders.customer-options` | `OrderController@customerOptions` | `orders.create` | scoped via `Customer::visibleTo($user)` |
| `orders.product-options` | `OrderController@productOptions` | `orders.create` | `active()->inStock()` |
| `commissions.partner-options` | `CommissionController@partnerOptions` | `commissions.adjust` | |
| `admin.partners.sponsor-options` | `Admin\PartnerController@sponsorOptions` | `partners.create` | |
| `admin.products.category-options` | `Admin\ProductController@categoryOptions` | `products.create` | `Category::active()` |
| `admin.products.brand-options` | `Admin\ProductController@brandOptions` | `products.create` | `Brand::active()` |
| `admin.categories.parent-options` | `Admin\CategoryController@parentOptions` | `categories.create` | |

> **Route ordering gotcha:** `orders/customer-options` + `orders/product-options` are declared **before** `orders/{order}` in `routes/web.php`, else `{order}` would swallow them.

---

## Models & Relationships

### `User`
- `hasOne Partner` — if user is a partner
- `hasOne Customer` — if user is a customer
- Uses Spatie's `HasRoles` trait
- `password` cast as `hashed` (bcrypt)
- Methods: `isStaff()` (checks if `super_admin` or `admin`)

### `Partner` (core MLM entity)
- `belongsTo User` (`user_id`)
- `belongsTo PartnerLevel` as `level` (`partner_level_id`)
- `belongsTo Partner` as `sponsor` (`sponsor_id`) — upline recruiter
- `belongsTo Partner` as `parent` (`parent_id`) — genealogy parent (often same as sponsor)
- `hasMany Partner` as `children` — sub-partners (reverse of parent)
- `hasMany Customer` — customers assigned to this partner
- `hasMany Order` — orders placed for this partner's customers
- `hasMany Commission` — commissions earned
- Properties:
  - `path` (string) — materialized path, e.g., `/1/3/7/`; used to find ancestors/descendants
  - `depth` (int) — nesting level from root
  - `direct_referral_count` (int) — immediate children
  - `total_downline_count` (int) — all descendants
  - `discount_override_percent` (nullable int) — custom discount, overrides level default
  - `status` (enum) — `active`, `inactive`, `suspended`
- Scopes: `descendantsOf($parentId)` — returns all descendants of a partner
- Uses `Auditable` trait

### `PartnerLevel`
- `hasMany Partner`
- Properties:
  - `level` (int) — 1–4
  - `discount_percent` (int) — default discount for customers
  - `commission_percent` (int) — commission earned on orders
  - `sponsor_bonus_percent` (int) — bonus to upline sponsor
- Uses `Auditable` trait

### `Customer`
- `belongsTo User` (`user_id`)
- `belongsTo Partner` (`partner_id`) — the partner managing this customer
- `hasMany Order` — orders placed by this customer
- Properties:
  - `discount_percent` (int) — customer-level discount override
  - `status` (enum) — `active`, `inactive`
- Scopes: **`visibleTo(User $user)`** — staff → all; partner → own (`where partner_id`); non-staff without a partner → **none** (`whereRaw('1=0')`, fail closed). The single reusable customer-ownership filter — use it instead of hand-rolling `where('partner_id', …)`.
- Uses `Auditable` trait

### `Order`
- `belongsTo Customer` (`customer_id`)
- `belongsTo Partner` (`partner_id`) — partner who facilitated the order
- `hasMany OrderItem`
- `hasMany Payment`
- `hasMany Commission` — generated when order is Paid
- Properties:
  - `order_number` (string), `placed_at` (datetime)
  - `discount_total`, `grand_total` (decimal) — grand_total is the commission/report base
  - `status` (enum, cast to `OrderStatus`) — `pending`, `paid`, `processing`, `shipped`, `completed`, `cancelled` (`OrderStatus::values()`)
- Uses `Auditable` trait

### `OrderItem`
- `belongsTo Order` (`order_id`)
- `belongsTo Product` (`product_id`)
- Properties:
  - `product_name`, `product_sku`, `unit_retail_price`, `unit_discount`, `unit_final_price` (all ints, cents)
  - `quantity` (int)
  - `line_total` (int cents) — quantity × unit_final_price

### `Payment`
- `belongsTo Order` (`order_id`)
- Properties:
  - `amount` (int cents)
  - `status` (enum) — `pending`, `paid`, `failed`, `refunded`
  - `method` (string) — payment method (in dev: "manual")
- Uses `Auditable` trait

### `Commission`
- `belongsTo Order` (`order_id`)
- `belongsTo Partner` (`partner_id`) — who earned it
- `belongsTo User` as `creator` (`creator_id`, nullable) — admin who created (if manual adjustment)
- Properties:
  - `type` (enum, cast to `CommissionType`) — `direct`, `sponsor_bonus`, `adjustment`
  - `amount` (int cents)
  - `status` (enum, cast to `CommissionStatus`) — `pending`, `paid`, `reversed`
- Uses `Auditable` trait

### `Product`
- `belongsTo Category` (`category_id`)
- `belongsTo Brand` (`brand_id`)
- `hasMany ProductImage`
- `hasOne ProductImage` as `primaryImage` (first image)
- `hasMany OrderItem` — orders containing this product
- Properties:
  - `name`, `sku`, `description`
  - `retail_price`, `cost_price` (decimal)
  - `partner_discount` (nullable) — volume/special discount cap for partners
  - `stock` (int)
  - `is_active` (bool)
- Scopes: `active()` (`is_active = true`), `inStock()` (`stock > 0`)
- Uses `Auditable` trait

### `Category`, `Brand`, `ProductImage`
- Standard catalog models; self-explanatory

### `Setting`
- Key/value store for app-wide configuration
- `key`, `value` (text/JSON)
- Examples: `default_commission_percent`, `default_sponsor_bonus_percent`, `max_partner_depth`

### `AuditLog`, `ActivityLog`
- `AuditLog` — captures create/update/delete changes to auditable models
  - `model`, `action`, `old_values`, `new_values`, `user_id`, `ip_address`, `timestamp`
- `ActivityLog` — optional activity tracking (currently unused)

---

## Core Services (Business Logic)

### `PartnerService`
**File:** `app/Services/PartnerService.php`

**Key methods:**

| Method | Purpose |
|--------|---------|
| `register(User $user, Partner $sponsor, PartnerLevel $level)` | Create a new partner under a sponsor. Validates max depth. Computes path/depth. Increments counts. Transactional, row-locks sponsor. |
| `effectiveDiscountPercent(Partner $partner)` | Returns partner's discount %: override if set, else level default. |
| `ancestorIds(Partner $partner)` | Parses `path` string to return array of ancestor IDs. |
| `guardMaxDepth(Partner $sponsor)` | Throws if sponsor is at max depth (reads `Setting: max_partner_depth`). |

**Path algorithm:**
```
path(root)   = "/"
path(child)  = path(parent) . parent.id . "/"
depth(root)  = 0
depth(child) = depth(parent) + 1
```

**Example:**
- Alice (L4) → path = "/", depth = 0
- Bob (L3, Alice's child) → path = "/1/", depth = 1
- Carol (L2, Bob's child) → path = "/1/3/", depth = 2

---

### `CommissionService`
**File:** `app/Services/CommissionService.php`

**Key methods:**

| Method | Purpose |
|--------|---------|
| `generateForPaidOrder(Order $order)` | Idempotent. Checks for existing commissions. If none, computes: direct = subtotal × partner level commission %; sponsor bonus = direct × sponsor level sponsor-bonus %. Creates Commission rows. Sends notification. |
| `adjust(Partner $partner, $amount, $reason, $createdBy)` | Manual adjustment. Creates adjustment Commission row (can be negative). Audit-logged. |

**Commission calculation:**
```
direct_commission = order.subtotal × partner.level.commission_percent

sponsor_bonus = direct_commission × partner.sponsor.level.sponsor_bonus_percent
```

All amounts rounded to nearest cent. Commissions are `pending` until paid by admin/payroll.

---

### `PricingService`
**File:** `app/Services/PricingService.php`

**Key methods:**

| Method | Purpose |
|--------|---------|
| `effectiveDiscountPercent(Customer $customer, Partner $partner, Product $product)` | Returns min(customer discount, partner effective discount, product discount). |
| `priceLine(OrderItem $item, Customer $customer, Partner $partner)` | Computes unit_final_price per line using effective discount. Rounded to cent per line. |

**Per-line math:**
```
effective_discount = min(
  customer.discount_percent,
  partner.effectiveDiscountPercent(),
  product.partner_discount_percent
)

unit_final_price = unit_retail_price × (1 - effective_discount / 100)
unit_discount = unit_retail_price - unit_final_price
line_total = unit_final_price × quantity
```

---

### `OrderService`
**File:** `app/Services/OrderService.php`

**Key methods:**

| Method | Purpose |
|--------|---------|
| `place(Customer $customer, Partner $partner, array $items)` | Transactional. Row-locks each product. Validates stock. Calls PricingService per line. Decrements stock. Creates Order+OrderItem+Payment. Notifies partner. |
| `setStatus(Order $order, string $status)` | Transitions order status. When set to `paid`, marks payment as paid, calls `CommissionService::generateForPaidOrder()`. |

---

### `MlmTreeService`
**File:** `app/Services/MlmTreeService.php`

**Key methods:**

Every method returns `['data' => nodes[], 'meta' => {current_page, last_page, per_page, total, has_more}]` (page size 25). `TreeController::nodes` picks the method by query param and wraps it via `TreeController::paged()` into the JSON envelope. The blade (`tree/index.blade.php`) reads `res.data`/`res.meta` and renders a "Load more" control from `has_more`.

| Method | Purpose |
|--------|---------|
| `roots(?Partner $scope, $page = 1)` | Paged depth-1 partners for staff; a scoped partner gets a single node (themselves). |
| `children(Partner $parent, $page = 1)` | One logical page over the concatenation **[child-partners … then customers]** (via `window()`) — a huge sibling set never loads at once. |
| `search(string $term, ?Partner $scope, $page = 1)` | Flat, paged hits by user name/email across partners + customers; a scoped partner is restricted to their own subtree (path prefix). |
| `window(Builder $partnerQuery, ?Builder $customerQuery, $page)` (private) | Offset-window engine: counts each side, slices partners first then fills with customers, returns `{data, meta}`. Shared by `roots`/`children`/`search`. |
| `canAccess(Partner $viewer, Partner $parent)` | True if viewer is target or target is a descendant (path prefix). Gate for `?parent=` expansion. |

---

### `ReportService`
**File:** `app/Services/ReportService.php`

`build($type, $filters, $pid)` returns a **spec**, not materialized rows: `['title', 'columns', 'query' => Builder, 'map' => fn($model)=>[...] ]`. The query is **not executed here** — exporters stream it. A non-null `$pid` scopes to that partner (sales/commissions/orders/customers). Types: `sales`, `commissions`, `partners`, `customers`, `orders`, `inventory`.

### `GenericExport` (streaming exporter)
**File:** `app/Exports/GenericExport.php` — implements `FromQuery` + `WithChunkReading` (`chunkSize 1000`) + `WithMapping` + `WithHeadings`. Constructor `(array $columns, Builder $query, Closure $map)`. maatwebsite/excel pulls the query in chunks — the full result set is never in memory.

### `ReportController::export`
**File:** `app/Http/Controllers/ReportController.php`
- `$pid = $user->isStaff() ? null : ($user->partner?->id ?? -1)` — **fail closed** (no partner ⇒ `-1` matches nothing, never all rows).
- Excel/CSV → `new GenericExport($spec['columns'], $spec['query'], $spec['map'])` (streamed).
- PDF → dompdf can't stream, so it's **capped at `PDF_MAX = 2000`** rows (`(clone $query)->limit(PDF_MAX)->get()->map(...)`), with a truncation note rendered in `reports/pdf.blade.php`.

---

### `AuditService`
**File:** `app/Services/AuditService.php`

**Key method:**

| Method | Purpose |
|--------|---------|
| `log(Model $model, string $action, $oldValues, $newValues, User $user, string $ip)` | Called automatically by `Auditable` trait on model create/update/delete. Writes an `AuditLog` row. |

Triggered by the `Auditable` model trait (see below).

---

### `SettingService`, `CustomerService`, `RealtimeService`
- **`SettingService`** — reads/writes `Setting` KV rows
- **`CustomerService`** — customer CRUD helpers
- **`RealtimeService`** — issues Socket.IO handshake tokens (sign/verify with `SOCKET_SECRET`)

---

## Model Traits & Concerns

### `Auditable` Trait
**File:** `app/Models/Concerns/Auditable.php`

Attached to: `Partner`, `PartnerLevel`, `Customer`, `Order`, `Payment`, `Commission`, `Product`, `Brand`, `Category`, `ProductImage`

Hooks into model events (created, updated, deleted) and calls `AuditService::log()` with before/after values. Every business model change is logged automatically.

---

## Data Isolation (partner sees only own customers)

Enforced **fail-closed** and consistently:
- **Canonical scope:** `Customer::scopeVisibleTo($user)` — staff → all; partner → own `partner_id`; non-staff w/o partner → none. Used by `OrderController::customerOptions` and the pattern for the customers list.
- **Lists:** `CustomerController::index` forces `partner_id` for non-staff (`?? 0`); `CommissionController::index` + `earningsQuery` force `partner_id` (`?? -1`) — both fail closed.
- **Reports:** `ReportController` `$pid` fails closed (`?? -1`).
- **IDOR guards:** `CustomerPolicy::owns` (update/delete), `OrderController::resolveCustomer` (store) and `canSee` (show **and** `updateStatus`).
- **Search leak fix:** `OrderRepository::applySearch` wraps its OR in a single closure so `order_number LIKE` can't escape the `partner_id` filter (`(A OR B) AND scope`).
- **Intentional exception:** the tree shows a partner's **downline** sub-partners' customers (`MlmTreeService::canAccess` allows descendants).

---

## Debugging Index

**Problem:** Wrong commission amount

→ **Check:** `CommissionService::generateForPaidOrder()` (app/Services/CommissionService.php)
  - Verify order is set to `Paid` status (not `Pending`)
  - Verify `order.partner.level.commission_percent` is correct
  - Verify `order.partner.sponsor.level.sponsor_bonus_percent` is correct
  - Check `order.subtotal` (final price after discount, not retail)
  - Verify `COMMISSIONS` table rows exist for the order

---

**Problem:** Wrong discount applied (customer charged too much or too little)

→ **Check:** `PricingService::effectiveDiscountPercent()` and `PricingService::priceLine()`
  - Verify `customer.discount_percent` in DB
  - Verify `partner.discount_override_percent` (if set) or `partner.level.discount_percent`
  - Verify `product.partner_discount_percent` (if set)
  - Check which is the **minimum** (system takes min of all three)
  - Verify rounding is to nearest cent per line (not per order)

Also check `PartnerService::effectiveDiscountPercent()` for the partner's effective discount computation.

---

**Problem:** Genealogy tree is wrong (wrong parent, wrong path, wrong depth)

→ **Check:** `PartnerService::register()` (app/Services/PartnerService.php)
  - Verify `path` column in `partners` table matches the algorithm
  - Verify `depth` column matches path length
  - Check `max_partner_depth` setting (default 4)
  - Manually inspect `partners.path` and `partners.depth` for affected partners
  - Check if `sponsor_id` and `parent_id` are correct (they should usually match)

Also check `MlmTreeService::canAccess()` if a partner can't see their subtree.

---

**Problem:** Stock goes negative or overselling occurs

→ **Check:** `OrderService::place()` (app/Services/OrderService.php)
  - Verify `lockForUpdate()` is called on each product (prevents race conditions)
  - Verify `quantity` on the `OrderItem` matches what was requested
  - Check `products.stock_quantity` after order creation (should be decremented)
  - If stock is negative, a previous order had a race condition; consider re-queueing the `place()` call or manual stock correction

---

**Problem:** Payment not created for an order

→ **Check:** `OrderService::place()` (app/Services/OrderService.php)
  - Verify a `Payment` row is created when order is placed (status: `pending`)
  - Check `payments` table for the order
  - Verify `order.payment` relationship loads the payment

---

**Problem:** Missing audit trail (no change history)

→ **Check:** `Auditable` trait (app/Models/Concerns/Auditable.php) and `AuditService::log()`
  - Verify model uses `use Auditable` trait
  - Verify `audit_logs` table has entries for the model change
  - Check app user is authenticated (audit log captures `user_id`)
  - Verify `AuditService::log()` is being called (should be automatic via the trait)

---

**Problem:** Real-time updates not showing (Tree, Orders, Commissions don't update live)

→ **Check:** `RealtimeService` (app/Services/RealtimeService.php) + `socket-server/index.js`
  - Verify `SOCKET_SECRET` is set in `.env` (generated during `make setup`)
  - Check `SOCKET_SECRET` is identical between Laravel and the Node socket service
  - Verify Redis is running and reachable (socket service depends on it)
  - Check Redis `SOCKET_CHANNEL` (default: `mlm.events`)
  - Verify browser console has no WebSocket connection errors (check Network tab for `/socket.io/` handshake)
  - Check `docker compose logs socket` for socket-server errors

---

**Problem:** Email not sent

→ **Check:** Mail configuration in `.env`
  - In dev: emails go to **Mailpit** (http://localhost:8025)
  - Verify `MAIL_HOST=mailpit`, `MAIL_PORT=1025` in `.env`
  - Check `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` are set
  - Check `queue` service is running (`make ps` → `mlm_queue` should be up)
  - In production: configure `MAIL_HOST=smtp.sendgrid.net` (or your provider) + auth credentials

---

**Problem:** A picker (partner/customer/product/sponsor/category/brand) is empty or search returns nothing

→ **Check:** the options endpoint + widget wiring
  - Hit the `*-options` route directly (e.g. `/customers/partner-options?search=al`) — expect `{data,meta}`; a 403 means the caller lacks the guarding permission (see the Options endpoints table)
  - Verify the blade inits `SearchableSelect` with the right `url` + `map` (and a hidden input whose `name` the form submits)
  - `orders/*-options` must be declared **before** `orders/{order}` in `routes/web.php` (route-order gotcha)

---

**Problem:** A partner sees another partner's customers/orders/commissions/report rows

→ **Check:** the isolation points
  - Customer queries should go through `Customer::scopeVisibleTo($user)`; lists/reports force `partner_id` fail-closed (`?? -1` / `?? 0`)
  - Order search leak → confirm `OrderRepository::applySearch` OR is wrapped in a closure
  - Mutations → `CustomerPolicy::owns`, `OrderController::resolveCustomer`/`canSee` (incl. `updateStatus`)
  - Tree exposing downline customers is **expected** (by design)

---

**Problem:** Tree doesn't paginate / "Load more" missing / search empty

→ **Check:** `MlmTreeService` + `TreeController::nodes`
  - Methods return `{data, meta:{has_more,...}}`; the blade shows "Load more" only when `has_more`
  - `nodes` routes on `?q` (search) → `?parent` (children) → none (roots); `?page` advances
  - Scoped partner search restricted to subtree via path prefix in `MlmTreeService::search`

---

**Problem:** Report export truncated, empty, or OOM

→ **Check:** `ReportService` / `GenericExport` / `ReportController`
  - Excel/CSV stream via `FromQuery`+`WithChunkReading` (`chunkSize 1000`) — never `->get()` the whole set
  - PDF is capped at `PDF_MAX = 2000` (expected truncation note) — use Excel/CSV for the full dataset
  - Empty for a partner user → `$pid` is fail-closed; confirm the user has a `partner` record

---

## File Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php (base)
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── OrderController.php
│   │   ├── CommissionController.php
│   │   ├── CustomerController.php
│   │   ├── TreeController.php
│   │   ├── ReportController.php
│   │   ├── Admin/
│   │   │   ├── PartnerController.php
│   │   │   ├── ProductController.php
│   │   │   └── ...
│   │   └── Auth/
│   │       ├── AuthController.php
│   │       ├── PasswordController.php
│   │       └── ProfileController.php
│   └── Middleware/ (permission checks, auth, etc.)
├── Models/
│   ├── User.php
│   ├── Partner.php
│   ├── Customer.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Payment.php
│   ├── Commission.php
│   ├── PartnerLevel.php
│   ├── Product.php
│   ├── Category.php
│   ├── Brand.php
│   ├── ProductImage.php
│   ├── Setting.php
│   ├── AuditLog.php
│   ├── Concerns/
│   │   └── Auditable.php (trait)
│   └── Enums/
│       ├── RoleName.php
│       ├── OrderStatus.php
│       ├── CommissionType.php
│       ├── CommissionStatus.php
│       └── ...
├── Services/
│   ├── PartnerService.php
│   ├── CommissionService.php
│   ├── PricingService.php
│   ├── OrderService.php
│   ├── MlmTreeService.php
│   ├── ReportService.php
│   ├── AuditService.php
│   ├── SettingService.php
│   ├── CustomerService.php
│   └── RealtimeService.php
├── Enums/ (domain enums)
│   ├── OrderStatus.php
│   ├── CommissionType.php
│   ├── CommissionStatus.php
│   └── ...
├── Events/ (domain events for broadcasting)
├── Notifications/ (email/notification classes)
├── Console/
│   └── Commands/
│       └── SetSocketSecret.php (generates SOCKET_SECRET)
└── ...
```

```
database/
├── migrations/ (schema changes)
├── seeders/
│   ├── DatabaseSeeder.php (main entry point)
│   ├── RolesAndPermissionsSeeder.php
│   ├── PartnerLevelSeeder.php
│   ├── SettingsSeeder.php
│   └── DemoSeeder.php (demo partner tree + customers)
└── factories/ (test factories)
```

```
resources/views/
├── layouts/
│   ├── app.blade.php (main layout)
│   └── partials/ (header, sidebar, footer)
├── dashboard.blade.php
├── auth/ (login, reset password)
├── profile/ (user profile)
├── customers/ (customer CRUD)
├── orders/ (order list/create/show)
├── commissions/ (commission list)
├── tree/ (genealogy tree view)
├── reports/ (reports list/export)
├── admin/ (admin panel)
│   ├── partners/ (partner CRUD)
│   ├── partner-levels/
│   ├── products/
│   ├── brands/
│   ├── categories/
│   ├── settings/
│   ├── audit/ (audit log)
│   └── ...
└── components/ (reusable Vue/Blade components)
```

```
routes/
├── web.php (all HTTP routes; session+CSRF)
└── api.php (stub; `/ping` only)
```

```
socket-server/ (standalone Node service)
├── index.js (Socket.IO + Redis listener)
├── package.json
└── Dockerfile
```

---

## Testing

**Run the test suite:**
```bash
make test
```

Tests run in an isolated environment (SQLite in-memory, array drivers for cache/session/queue) without touching your dev database.

**Example test locations:**
- `tests/Unit/Services/CommissionServiceTest.php`
- `tests/Feature/OrderPlacementTest.php`
- `tests/Feature/TreeControllerTest.php`

---

## Code Quality

**Format code with Pint:**
```bash
make pint
```

Enforces Laravel code standards (PSR-12, etc.).

---

## Key Takeaways

1. **Business logic lives in services**, not controllers.
2. **Materializedpath genealogy** — read `Partner.path` and `Partner.depth` to debug tree issues.
3. **Pricing is per-line** — discounts apply line-by-line, rounded to cent.
4. **Commissions are idempotent** — generating them multiple times is safe.
5. **Audit trail is automatic** — all business model changes are logged via `Auditable` trait.
6. **Real-time events flow** — Laravel → Redis → Socket.IO → Browser.
7. **No full-table dropdowns** — every picker is `SearchableSelect` + a paginated `*-options` endpoint; lists/tree/reports are all paged/streamed (nothing loads a whole table).
8. **Isolation is fail-closed** — `Customer::scopeVisibleTo` + forced `partner_id` (`?? -1`); a non-staff user with no partner sees nothing.
9. **Use the debugging index** above to find where to look when something breaks.
