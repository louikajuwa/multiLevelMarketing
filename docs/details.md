# System Overview & Default Credentials

## Default Login Credentials

After running `make setup`, the database is seeded with the following accounts. All passwords are `password`.

### Staff Accounts

| Email | Password | Role | Access |
|-------|----------|------|--------|
| `superadmin@mlm.test` | `password` | `super_admin` | All features, all permissions |
| `admin@mlm.test` | `password` | `admin` | All features except user/role management |

### Demo Partners (MLM Hierarchy)

Partners can recruit sub-partners and customers, and earn commissions on sales.

| Email | Password | Level | Role | Parent |
|-------|----------|-------|------|--------|
| `alice@mlm.test` | `password` | L4 | partner | — (root) |
| `bob@mlm.test` | `password` | L3 | partner | Alice |
| `eve@mlm.test` | `password` | L2 | partner | Alice |
| `carol@mlm.test` | `password` | L2 | partner | Bob |
| `dan@mlm.test` | `password` | L1 | partner | Carol |

### Demo Customers

Customers belong to a specific partner and can purchase products at a negotiated discount.

| Email | Password | Role | Associated Partner |
|-------|----------|------|-------------------|
| `cust1@mlm.test` | `password` | customer | Alice |
| `cust2@mlm.test` | `password` | customer | Bob |
| `cust3@mlm.test` | `password` | customer | Carol |
| `cust4@mlm.test` | `password` | customer | Dan |

## Roles & Permissions

The system uses **Spatie Laravel Permission** for role-based access control.

### Roles

- **`super_admin`** — Full access to all features and admin functions
- **`admin`** — All features except user and role management
- **`partner`** — Can manage own customers, view own genealogy tree, see commissions, place orders
- **`customer`** — Can browse products, view own orders

### Permissions

Permissions are named `module.action` and include:

| Module | Actions |
|--------|---------|
| `partners` | view, create, update, delete |
| `partner_levels` | view, create, update, delete |
| `customers` | view, create, update, delete |
| `products` | view, create, update, delete |
| `categories` | view, create, update, delete |
| `brands` | view, create, update, delete |
| `orders` | view, create, update, update_status, delete |
| `commissions` | view, adjust |
| `reports` | view |
| `settings` | view, update |
| `users` | view, create, update, delete |
| `roles` | view, create, update, delete |
| `audit` | view |
| `tree` | view |

Permission assignment happens during seeding in `database/seeders/RolesAndPermissionsSeeder.php`. Super Admin gets all; Admin gets all except user/role CRUD; Partner and Customer get scoped subsets.

## How the System Works

### Partner Hierarchy & Genealogy

Partners form a tree structure. When a partner signs up, they choose a **sponsor** (upline partner). The system stores the genealogy as a **materialized path** — each partner has:
- `path`: a string encoding all ancestors, e.g., `/1/3/7/` for descendants of node 7
- `depth`: nesting level (max configurable, default 4 levels)
- `direct_referral_count`: immediate children
- `total_downline_count`: all descendants

When a partner is created:
1. Lock the sponsor row
2. Compute the new partner's path and depth
3. Enforce max-depth limit (Setting: `max_partner_depth`)
4. Increment sponsor's counts and all ancestors' downline counts

**Example tree:**
```
Alice (L4, root)
├─ Bob (L3)
│  └─ Carol (L2)
│     └─ Dan (L1)
└─ Eve (L2)
```

### Partner Levels & Discounts

Four configurable partner **levels** (L1–L4), seeded with:

| Level | Discount % | Commission % | Sponsor Bonus % |
|-------|-----------|-------------|-----------------|
| L1 | 5 | 8 | 5 |
| L2 | 10 | 10 | 5 |
| L3 | 15 | 12 | 5 |
| L4 | 20 | 15 | 5 |

Each level defines:
- **Discount %:** discount given to customers of this partner
- **Commission %:** percent of order final-price earned by this partner when their customer places a paid order
- **Sponsor Bonus %:** percent of the direct commission earned by the upline sponsor

### Pricing & Discounts

When a customer places an order, the system calculates the final price per line:

```
Effective Discount = min(
  customer.discount_percent,
  partner.effective_discount_percent,  # partner's level discount (or override)
  product.partner_discount_percent
)

Unit Final Price = Unit Retail Price × (1 - Effective Discount / 100)

Line Total = Unit Final Price × Quantity
```

All calculations are rounded to the nearest cent per line.

**Who applies the discount?**
- **Partner's effective discount** = partner's `discount_override_percent` (if set), else their level's `discount_percent`
- **Customer's discount** = their assigned `discount_percent` (often 0 or a loyalty override)
- **Product's partner discount** = hardcoded on the product (for volume discounts, sales, etc.)

The final discount is the **minimum** of all three, capping the total discount given.

### Orders & Payments

**Order placement** (Customer → Partner):
1. Customer or partner places order (order belongs to both the customer and the partner who facilitated it)
2. `OrderService::place()` validates each product:
   - Row-locks the stock to prevent overselling
   - Calls `PricingService` to compute final prices
   - Deducts stock
3. Creates `Order` + `OrderItem` records + a pending `Payment` (status: Pending)
4. Notifies the partner

**Setting an order to "Paid":**
1. Partner or admin marks the order as Paid
2. `OrderController::updateStatus()` → `OrderService::setStatus()`
3. Updates the `Payment` status to Paid
4. Calls `CommissionService::generateForPaidOrder()`:
   - **Direct Commission** = order subtotal (after discount) × partner's level commission %
   - **Sponsor Bonus** = direct commission × sponsor's level sponsor-bonus %
   - Creates `Commission` rows for both
   - Sends a `CommissionEarnedNotification`

**Idempotency:** Calling `generateForPaidOrder()` multiple times on the same order is safe — it checks for existing commission rows and skips generation if already done.

### Commissions & Payouts

Each commission row records:
- `partner_id` — the partner who earned it
- `order_id` — which order triggered it
- `type` — `Direct` or `SponsorBonus`
- `amount` — in cents (rounded)
- `status` — `Pending`, `Paid`, or `Reversed`

**Manual adjustments:** Admin can create adjustment commission rows (negative or positive) via `CommissionController::adjust()` to correct calculation errors, refunds, chargebacks, etc.

All commission changes are audit-logged (see Audit Trail below).

### Audit Trail

Every create/update/delete of a business model is automatically logged:
- `Model` (model class name)
- `action` (created, updated, deleted)
- `old_values` (before change)
- `new_values` (after change)
- `user_id` (who made the change)
- `ip_address` (where the request came from)
- `timestamp`

This is implemented via the `Auditable` model trait (attached to `Partner`, `Order`, `Commission`, `Customer`, `Product`, `PartnerLevel`, etc.). Admin can view the full audit log in `Admin → Audit Logs`.

### Real-Time Updates

The system publishes domain events to Redis when key actions occur:
- New order placed
- Commission earned
- Partner registered
- etc.

A standalone Node.js service (`socket-server/`) listens to Redis and broadcasts these events to connected browsers via **Socket.IO**. The browser receives real-time updates without polling.

**Security:** Socket.IO connections are verified using a shared `SOCKET_SECRET` (generated during `make setup`). Both Laravel and the Node service sign/verify handshake tokens with this secret.

### Reports & Exports

Admin and partners can generate tabular reports:
- **Sales Report:** orders by date range, partner, customer
- **Commission Report:** commissions earned/paid by partner, date range
- **Partner Report:** hierarchy, counts, discount/commission levels
- **Customer Report:** customer list, associated partner, total orders/spend
- **Inventory Report:** stock levels, product sales

Reports can be exported as **Excel** (`.xlsx`), **CSV**, or **PDF**. There is **no on-screen report table** — the page is export buttons + a date-range filter. Excel/CSV **stream the full dataset in chunks** (never loaded fully into memory); PDF is **capped at 2000 rows** with a "truncated — use Excel/CSV" note.

### Searchable Selectors (scale-safe pickers)

Any place you pick one record from many — partner, customer, product, sponsor, category, brand, parent-category — is a **search-as-you-type input**, not a dropdown of every row. It queries a paginated `*-options` endpoint (10 at a time, "Load more" for the rest). This keeps pages small and fast no matter how many partners/customers/products exist. Powered by one shared widget, `public/assets/js/core/searchable-select.js` (see `code.md`).

### Genealogy Tree (Pagination & Search)

The tree never loads the whole network. Roots and each node's children load **one page at a time** ("Load more"), and a **search box** returns flat matches (partners + customers by name/email) across the accessible subtree. A partner searching only sees their own subtree.

### Data Isolation (who sees whose customers)

A **partner sees only their own customers** — in the customer list, every picker, orders, commissions, and report exports. This is enforced **fail-closed**: a non-staff user with no partner record sees **nothing** (never everything). Staff (`super_admin`/`admin`) see all. The **one intentional exception** is the genealogy tree, which also shows a partner's **downline sub-partners' customers** (network visibility by design). Reusable rule: `Customer::scopeVisibleTo($user)`.

## Database Structure

### Core Tables

| Table | Purpose |
|-------|---------|
| `users` | Login accounts; username, email, password hash, 2FA settings |
| `partners` | MLM nodes; sponsor, parent, path, depth, downline counts, discount override |
| `partner_levels` | Discount/commission percentages per level (L1–L4) |
| `customers` | Customer profiles; partner assignment, discount override |
| `orders` | Order records; customer, partner, subtotal, final total, status |
| `order_items` | Line items of orders; product snapshot (name, qty, prices) |
| `payments` | Payment records linked to orders (simulated; no gateway) |
| `commissions` | Earnings; partner, order, type (Direct/SponsorBonus), amount, status |
| `products` | Catalog items; category, brand, retail price, stock, partner discount |
| `product_images` | Images for products |
| `categories` | Product categories |
| `brands` | Product brands |
| `settings` | Key/value app config (e.g., `default_commission_percent`, `max_partner_depth`) |
| `audit_logs` | Change history; model, action, old/new values, user, IP, timestamp |
| `activity_logs` | Activity tracking (optional; currently unused in this build) |

### Permission Tables (Spatie)

- `roles` — role names
- `permissions` — permission names (`module.action` format)
- `model_has_roles` — user → role assignments
- `model_has_permissions` — user → direct permission assignments
- etc.

## Configuration

Key settings stored in `settings` table (viewable/editable via `Admin → Settings`):

- `default_commission_percent` — fallback commission % if partner's level doesn't specify
- `default_sponsor_bonus_percent` — fallback sponsor bonus % if partner's level doesn't specify
- `max_partner_depth` — max genealogy depth (default 4)
- `enable_2fa` — whether to enforce 2FA on login
- etc.

These can be overridden per partner or product.

## Common Workflows

### Signing Up a New Partner
1. Admin or super-admin navigates to `Admin → Partners → Create`
2. Fills in name, email, password, chooses sponsor (upline), selects level
3. System auto-computes path/depth, validates max-depth
4. Sends welcome email with login credentials

### A Partner Placing an Order on Behalf of Their Customer
1. Partner navigates to `Orders → Create`
2. Selects a customer from their roster
3. Adds products to cart (prices auto-calculated with discount)
4. Submits order
5. System creates `Order` + `OrderItem` + pending `Payment`
6. Partner or admin marks order as Paid
7. System generates commission rows for partner and their sponsor
8. Real-time notification pushed to partner's browser

### Viewing the Genealogy Tree
1. Partner navigates to `Tree` (under `Partner → Tree`)
2. Sees their downline as a lazy-loaded tree
3. Can click to expand and see sub-partners and customers
4. Can access controls to manage sub-partners/customers (scoped by role)

### Exporting a Report
1. Admin navigates to `Reports`
2. Selects report type (Sales, Commissions, Partners, etc.)
3. Optionally filters by date range and partner
4. Clicks "Export"
5. Browser downloads Excel file

## Troubleshooting

**Wrong discount applied?**
→ Check `PricingService.php` and `PartnerService::effectiveDiscountPercent()`. Verify partner level, customer override, and product discount in the database.

**Commission not generated?**
→ Check order status is set to "Paid", not "Pending". Verify `CommissionService::generateForPaidOrder()` is called. Check audit logs for errors.

**Genealogy tree shows wrong parent?**
→ Check the `path` and `depth` columns in the `partners` table. Run `PartnerService::register()` logic to recompute. See `code.md` for the algorithm.

**Payment not recorded?**
→ Orders create a payment record in `payments` table when placed. Check `order.payment` relationship in `OrderController`.

**Email not delivered?**
→ In development, emails go to Mailpit (http://localhost:8025). Check that `MAIL_HOST=mailpit` and `MAIL_PORT=1025` in `.env`. For production, configure SendGrid (example in `.env.example`).
