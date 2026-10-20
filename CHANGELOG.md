# Changelog

## 2026-10-17 — Audit and hardening

### Database
- Added migration `2026_10_17_100001_add_order_type_index_to_commissions_table`. It only adds an index (`order_id`, `type`) on `commissions` and can be reversed.
- No schema changes beyond that, and no data was modified. The new `void` commission status reuses the existing `CommissionStatus::Void` value in a string column.

### Business logic
- Order status state machine:
  - pending → paid or cancelled
  - paid → processing or cancelled
  - processing → shipped or cancelled
  - shipped → completed
  - completed and cancelled are terminal
  - Illegal transitions return 422.
- Cancelling an order restocks its items, voids its pending commissions and marks its pending payment as failed.
- Commission generation locks the order, so concurrent "paid" requests can't create duplicates.
- Partners and customers whose status isn't active can't log in. Customers of an inactive partner can't place orders.
- The dashboard "Commissions paid" figure counts paid commissions only. Earnings totals exclude voided rows.

### Security
- Partner lists and option endpoints are scoped to self plus downline for non-staff users.
- Commission adjustments must reference the partner's own order and a bounded, non-zero amount.
- Category parent cycles are rejected.
- Partner deletion is atomic and locked.
- Rate limits added on forgot-password, reset-password and settings test-email.
- Realtime tokens are refused (503) when `SOCKET_SECRET` is empty.
- The encrypted mail password is no longer kept in the settings cache.
- `DemoSeeder` runs only in local and testing environments.
- Array-valued query parameters no longer cause 500 errors.
- Stricter max lengths and ranges on form inputs.
- Fixed XSS: a shared `esc()`/`safeUrl()` (new `public/assets/js/core/util.js`) is applied to all client-side renderers, notification links and confirm dialogs.
- jQuery is now loaded with SRI. Chart.js is pinned to 4.4.1.

### UI / UX
- Central AJAX error handling for network errors, 401, 419, 422, 429 and 5xx.
- Double-submit prevention on forms.
- Tables:
  - Show skeleton loading, empty and error-with-Retry states.
  - Cancel a pending request when a newer one starts.
- Modals and confirm dialogs:
  - Focus is trapped while open and restored on close; Escape closes.
  - Destructive dialogs put focus on the safe button by default.
- Orders: the status picker only offers valid next statuses. Cancel and complete ask for confirmation.
- Commissions: adjustments ask for confirmation, and voided commissions show a grey badge.
- Accessibility:
  - ARIA states on the navigation controls.
  - Skip link and visible focus rings.
  - Labels on filter inputs and keyboard-operable tree nodes.
  - Reduced-motion support.
- Responsive:
  - The dashboard grid collapses on mobile, with KPI cards two per row.
  - The notification dropdown is clamped to the screen width.
  - Headers and filter bars wrap on narrow screens.
  - Modals fit the viewport.

### Tests
- 71 → 106 passing tests. New test files:
  - `OrderStatusTransitionTest`
  - `SecurityHardeningTest`
  - `PasswordAndProfileTest`
  - `ValidationEdgeTest`
- `CommissionTest` was adapted to the state machine.

### Configuration
- `phpunit.xml` sets `SOCKET_SECRET=test-socket-secret` for tests (test-only).
- No `.env` changes are required: the existing `SOCKET_SECRET` is already set.
- For production, set:
  - `SESSION_SECURE_COOKIE=true`
  - `APP_DEBUG=false`
  - TLS
  - new passwords for the seeded admin accounts
