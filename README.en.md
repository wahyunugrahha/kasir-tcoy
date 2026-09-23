# KasirTcuy

Language: [Bahasa Indonesia](README.md) | English

KasirTcuy is a Point of Sale monorepo consisting of a Laravel API backend and a Vue frontend for cashier operations, product management, shift settlement, and sales reporting. It ships with a public landing page, a light/dark theme, and a bilingual (Indonesian/English) UI.

## Overview

This repository separates responsibilities between the API and the client:

- `pos-backend` provides the REST API, token authentication with Laravel Sanctum, transactions, inventory movements, shift settlement, and reporting.
- `pos-frontend` provides the public landing page plus the cashier and admin interface, built with Vue 3, Pinia, Vue Router, vue-i18n, Chart.js, and Tailwind CSS v4.

Main features currently visible in the codebase:

- Public landing page describing the product, with its own light/dark and language toggle.
- Bearer-token based login.
- Light/dark theme, togglable from the sidebar (and from the landing/login pages), defaulting to light.
- Bilingual UI (Indonesian/English), togglable from the same place as the theme.
- Cashier dashboard for fast checkout, with split payment across cash, QRIS, debit, credit card, e-wallet, and bank transfer.
- Hold order and recall order flow (Order List page), stored server-side so any cashier can recall it.
- Promo codes with minimum-purchase rules, and a customer loyalty points system redeemable at checkout.
- Offline checkout queue: a transaction that fails to send while offline is queued locally and synced automatically once the connection returns.
- Print the latest receipt, and re-print any past receipt from Transaction History.
- Product, category, customer, and user management. Deleting a product soft-deletes it, so past transaction history stays intact.
- Transaction history and transaction details.
- Transaction void with automatic stock restoration, gated behind manager PIN approval for non-admin roles.
- Bills page for tracking unpaid/partial (pay-later) transactions.
- Shift settlement with physical cash vs system cash comparison.
- Summary reports, daily sales reports, top product analytics, cashier performance, profit by category, stock valuation, and void/refund reports, exportable to Excel.
- Audit logs for selected actions.

## Tech Stack

### Backend

- PHP 8.3+
- Laravel 13
- Laravel Sanctum
- PostgreSQL
- PHPUnit 12
- Vite for backend assets

### Frontend

- Vue 3
- Pinia
- Vue Router
- vue-i18n (Indonesian/English)
- Axios
- Chart.js and vue-chartjs
- Tailwind CSS v4
- Vite
- SheetJS (`xlsx`) for native Excel export

## Repository Structure

```text
.
|-- pos-backend/   # Laravel API + database + seeders + tests
|-- pos-frontend/  # Vue app for POS/admin workflows
|-- package.json   # small root workspace dependency
```

Important locations:

- `pos-backend/routes/api.php` contains authentication, checkout, master data, transaction, shift, reporting, inventory, promo code, held order, and audit log endpoints.
- `pos-backend/database/seeders/DatabaseSeeder.php` provides demo users, categories, products, customers, initial stock, and a sample transaction.
- `pos-frontend/src/router/index.js` defines landing, login, dashboard, POS, products, history, manager approval, reports, settings, order list, bills, settlement, and promo codes pages.
- `pos-frontend/src/stores` contains auth and cart state.
- `pos-frontend/src/services/api.js` handles the API base URL and bearer token injection.
- `pos-frontend/src/i18n/` holds the vue-i18n setup and the Indonesian/English translation dictionaries.
- `pos-frontend/src/composables/useTheme.js` holds the light/dark theme toggle, persisted to `localStorage`.

## Feature Modules

### Auth

- Login via `POST /api/auth/login`.
- Fetch the current user via `GET /api/auth/me`.
- Logout the current token via `POST /api/auth/logout`.
- Frontend session is stored in `localStorage`.

### POS / Checkout

- Loads product catalog from the backend, with grid or list view.
- Adds items to the cart with stock validation.
- Supports `cash`, `qris`, `debit`, `credit_card`, `e_wallet`, and `bank_transfer` payment methods, including split payment across several methods at once.
- Applies a promo code or redeems customer loyalty points against the total.
- Calculates subtotal, discount, tax, grand total, amount paid, and change, with quick-cash suggestions scaled to real IDR note denominations.
- Stores the transaction and deducts stock atomically in the backend.
- If the request fails because the device is offline, the transaction is queued locally and retried automatically once the connection returns.
- Supports printing the latest receipt, and re-printing any past receipt from Transaction History.

### Order Management

- Hold cart to postpone an order (stored server-side, in the Order List page).
- Recall a held order into the active cart from any cashier device.
- Sync cart quantities with the latest stock data.

### Promo Codes and Loyalty Points

- Admin-managed promo codes with a minimum-purchase requirement and a usage limit.
- Customers accrue points on purchase and can redeem them against a later transaction's total.

### Products and Inventory

- Endpoints for listing, viewing, creating, updating, and deleting products, with grid or list view in the admin UI.
- Deleting a product soft-deletes it (`deleted_at`), so past transaction history and reports stay intact; a soft-deleted product can no longer be checked out or moved in inventory.
- Inventory movements are recorded for stock in and stock out.
- A configurable reorder point per product drives the low-stock alert shown on the Dashboard and Kasir catalog.
- Voiding a transaction restores stock and creates a new inventory movement.

### Manager Approval

- Void and refund by a non-admin (cashier) require a manager PIN, verified against `POST /api/v1/managers/verify-pin` and time-limited once granted.
- A manager can change their own PIN from the Settings page.

### Shift Settlement

- Open a new shift with opening cash.
- Log cash movements in/out during the shift.
- Close a shift with physical cash input.
- Calculate cash differences.
- Store shift history.

### Bills (Pay-Later)

- Lists unpaid/partial transactions with the amount received and remaining balance.
- Lets a cashier take a further payment against a bill until it is fully paid.

### Reporting

- Today's and current month's sales summary.
- Daily sales chart and top products chart.
- Cashier performance, profit by category, stock valuation, and void/refund reports.
- Every report and the transaction history are exportable to a multi-sheet Excel workbook.

## Landing Page, Theming, and Localization

- `/` is a public landing page describing the product (workflow, roles, features), separate from the authenticated app shell. `/login` is also public.
- Every page ships a light and a dark theme (`useTheme` composable, class-based via Tailwind's `dark:` variant), toggled from an icon button in the sidebar header (authenticated pages) or the landing/login header. The choice is persisted to `localStorage` and defaults to light on first visit.
- The UI is bilingual (Indonesian/English) via `vue-i18n`, toggled from an EN/ID button next to the theme toggle. The choice is persisted to `localStorage` (`pos_locale`) and defaults to Indonesian.
- The visual design is a Coinbase-inspired system adapted for a cashier/POS context.

## Main Endpoints

Verified backend endpoints:

### Public / semi-public

- `GET /api/products`
- `POST /api/auth/login`

### Authenticated

- `GET /api/auth/me`
- `POST /api/auth/logout`
- `POST /api/checkout`

### Versioned API

All endpoints below are under the `/api/v1` prefix and protected by `auth:sanctum`:

- `users`
- `categories`
- `products`
- `customers`
- `transactions` (`pay`, `void`, `refund` sub-actions)
- `inventory-movements`
- `held-orders`
- `promo-codes` (plus `promo-codes/validate`)
- `shifts` (plus `cash-movements`, `close`)
- `managers` (plus `managers/verify-pin`)
- `reports/summary`
- `reports/sales-by-date`
- `reports/top-products`
- `reports/cashier-performance`
- `reports/profit-by-category`
- `reports/stock-valuation`
- `reports/void-refunds`
- `reports/low-stock`
- `audit-logs`

Some endpoints are restricted by the `admin` role middleware, while cashier users can access operational cashier and transaction flows as needed.

## Demo Seeder Accounts

The backend seeder creates these demo accounts:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@pos.local` | `password` |
| Cashier | `cashier@pos.local` | `password` |

The seeder also adds:

- 3 initial categories.
- 6 initial products.
- initial stock with seed inventory movement records.
- 3 sample customers.
- 1 sample transaction.

## Local Development Setup

### Requirements

- PHP 8.3 or newer.
- Composer.
- Node.js 20.19+ or 22.12+.
- npm.
- PostgreSQL.

### 1. Backend setup

Manual option:

```bash
cd pos-backend
composer install
```

Copy the environment file:

```bash
# Windows
copy .env.example .env

# macOS / Linux
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Configure PostgreSQL in `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=projectnganggur
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

Then run migrations and seeders:

```bash
php artisan migrate
php artisan db:seed
```

Install backend assets if needed:

```bash
npm install
```

For backend development:

```bash
composer run dev
```

That command runs the Laravel server, queue listener, log watcher, and backend Vite process in parallel.

Minimal alternative:

```bash
php artisan serve
```

The backend API is available by default at `http://127.0.0.1:8000`.

### 2. Frontend setup

```bash
cd pos-frontend
npm install
npm run dev
```

The frontend is available by default at `http://127.0.0.1:5173`.

By default the frontend uses:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

If this variable is not defined, the frontend still falls back to that URL.

### 3. Production build

Backend assets:

```bash
cd pos-backend
npm run build
```

Frontend:

```bash
cd pos-frontend
npm run build
```

## Important Scripts

### Backend

- `composer run dev` runs the Laravel development environment.
- `composer test` runs Laravel tests.
- `npm run dev` runs backend Vite.
- `npm run build` builds backend assets.

### Frontend

- `npm run dev` runs frontend Vite.
- `npm run build` builds the frontend.
- `npm run preview` previews the build output.
- `npm run lint` runs Oxlint and ESLint.
- `npm run format` runs Prettier on frontend source files.

## High-Level Data Flow

1. A user logs in from the frontend and receives a bearer token from Laravel Sanctum.
2. The token is stored in `localStorage` and automatically attached by the Axios interceptor.
3. The cashier selects products and the frontend sends a checkout payload to the backend.
4. The backend generates an invoice number, validates stock, stores transaction details, and deducts stock in a database transaction.
5. Inventory movement, shift settlement, and reporting are derived from the same transaction data.

## Further Development

Areas that look ready for extension:

- richer API request/response documentation.
- broader test coverage for POS and settlement flows.
- a production deployment guide.
- finishing the Indonesian/English translation coverage across every authenticated page (currently complete on the landing page, login, and sidebar navigation).

## Notes

- This project is used with PostgreSQL. Update the database credentials in `.env` before running migrations.
- Frontend and backend run as separate applications, so deployment can also be separated.
- Some frontend routes are restricted through router role metadata.

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE).