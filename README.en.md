# Kasir Tcoy

Language: [Bahasa Indonesia](README.md) | English

Kasir Tcoy is a Point of Sale monorepo consisting of a Laravel API backend and a Vue frontend for cashier operations, product management, shift settlement, and sales reporting.

## Overview

This repository separates responsibilities between the API and the client:

- `pos-backend` provides the REST API, token authentication with Laravel Sanctum, transactions, inventory movements, shift settlement, and reporting.
- `pos-frontend` provides the cashier and admin interface built with Vue 3, Pinia, Vue Router, Chart.js, and Tailwind CSS v4.

Main features currently visible in the codebase:

- Bearer-token based login.
- Cashier dashboard for fast checkout.
- Hold order and recall order flow in the cart.
- Print the latest receipt.
- Product, category, customer, and user management.
- Transaction history and transaction details.
- Transaction void with automatic stock restoration.
- Shift settlement with physical cash vs system cash comparison.
- Summary reports, daily sales reports, and top product analytics.
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
- Axios
- Chart.js and vue-chartjs
- Tailwind CSS v4
- Vite

## Repository Structure

```text
.
|-- pos-backend/   # Laravel API + database + seeders + tests
|-- pos-frontend/  # Vue app for POS/admin workflows
|-- package.json   # small root workspace dependency
```

Important locations:

- `pos-backend/routes/api.php` contains authentication, checkout, master data, transaction, shift, reporting, inventory, and audit log endpoints.
- `pos-backend/database/seeders/DatabaseSeeder.php` provides demo users, categories, products, customers, initial stock, and a sample transaction.
- `pos-frontend/src/router/index.js` defines landing, login, POS, products, history, reports, settings, order list, bills, and settlement pages.
- `pos-frontend/src/stores` contains auth and cart state.
- `pos-frontend/src/services/api.js` handles the API base URL and bearer token injection.

## Feature Modules

### Auth

- Login via `POST /api/auth/login`.
- Fetch the current user via `GET /api/auth/me`.
- Logout the current token via `POST /api/auth/logout`.
- Frontend session is stored in `localStorage`.

### POS / Checkout

- Loads product catalog from the backend.
- Adds items to the cart with stock validation.
- Supports `cash`, `qris`, and `debit` payment methods.
- Calculates subtotal, discount, tax, grand total, amount paid, and change.
- Stores the transaction and deducts stock atomically in the backend.
- Supports printing the latest receipt.

### Order Management

- Hold cart to postpone an order.
- Recall held orders into the active cart.
- Sync cart quantities with the latest stock data.

### Products and Inventory

- Endpoints for listing, viewing, creating, updating, and deleting products.
- Inventory movements are recorded for stock in and stock out.
- Voiding a transaction restores stock and creates a new inventory movement.

### Shift Settlement

- Open a new shift with opening cash.
- Close a shift with physical cash input.
- Calculate cash differences.
- Store shift history.

### Reporting

- Today's and current month's sales summary.
- Daily sales chart.
- Top products chart.

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
- `transactions`
- `inventory-movements`
- `shifts`
- `reports/summary`
- `reports/sales-by-date`
- `reports/top-products`
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
- UI screenshots for landing, cashier, reports, and settlement pages.

## Notes

- This project is used with PostgreSQL. Update the database credentials in `.env` before running migrations.
- Frontend and backend run as separate applications, so deployment can also be separated.
- Some frontend routes are restricted through router role metadata.

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE).

*** Add File: d:/ProjectNganggur/LICENSE
MIT License

Copyright (c) 2026 Kasir Tcoy

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.