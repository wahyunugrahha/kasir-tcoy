# KasirTcuy

Bahasa: Indonesia | [English](README.en.md)

KasirTcuy adalah aplikasi Point of Sale (POS) berbasis monorepo yang menggabungkan backend API Laravel dan frontend Vue untuk kebutuhan operasional kasir, manajemen master data, settlement shift, billing, riwayat transaksi, serta pelaporan penjualan.

Subjudul produk: POS System App.

## Daftar Isi

- [Ringkasan Proyek](#ringkasan-proyek)
- [Fitur Utama](#fitur-utama)
- [Tech Stack](#tech-stack)
- [Struktur Monorepo](#struktur-monorepo)
- [Arsitektur Singkat](#arsitektur-singkat)
- [Prasyarat](#prasyarat)
- [Panduan Instalasi Lokal](#panduan-instalasi-lokal)
- [Menjalankan Aplikasi](#menjalankan-aplikasi)
- [Akun Demo Seeder](#akun-demo-seeder)
- [Endpoint API Utama](#endpoint-api-utama)
- [Script Penting](#script-penting)
- [Troubleshooting Umum](#troubleshooting-umum)
- [Roadmap Pengembangan](#roadmap-pengembangan)
- [Lisensi](#lisensi)

## Ringkasan Proyek

KasirTcuy memisahkan concern antara API dan client:

- `pos-backend`: Laravel API untuk autentikasi, checkout, inventory movement, transaksi, shift, reporting, audit log, dan business rule.
- `pos-frontend`: antarmuka POS/admin berbasis Vue 3 dengan alur kasir modern (split payment, hold order, riwayat, export Excel, dan lain-lain).

Pendekatan ini memudahkan scaling tim dan deployment karena frontend dan backend dapat dijalankan terpisah.

## Fitur Utama

### 1) Autentikasi dan Otorisasi

- Login token-based (Laravel Sanctum).
- Session frontend di `localStorage`.
- Pembatasan akses berbasis role (`admin`, `cashier`) di endpoint dan UI tertentu.

### 2) Kasir / Checkout

- Katalog produk dengan pencarian dan filter kategori.
- Keranjang belanja dengan validasi stok real-time.
- Metode pembayaran tunggal dan split payment.
- Hitung subtotal, diskon persen, pajak persen, grand total, uang diterima, dan kembalian.
- Hold order untuk menunda transaksi.
- Input nama pembeli pada flow checkout.
- Cetak struk transaksi terakhir.

### 3) Transaksi dan Riwayat

- Riwayat transaksi dengan filter status/metode/tanggal.
- Detail transaksi (item, pembayaran, refund).
- Void transaksi dengan guard approval sesuai role.
- Refund parsial per item dengan alasan.

### 4) Billing

- Halaman daftar tagihan transaksi unpaid/partial.
- Menampilkan total pembayaran, uang diterima, dan kembalian.
- Menampilkan nama pembeli per transaksi.

### 5) Laporan dan Export

- Ringkasan penjualan dan analytics.
- Export riwayat ke Excel native (`.xlsx`) multi-sheet:
  - Sheet Ringkasan
  - Sheet Detail
- Header row freeze saat scroll di Excel.
- Kolom nominal otomatis format Rupiah pada sheet export.

### 6) Master Data dan Operasional

- Manajemen kategori, produk, customer, user.
- Inventory movement tercatat untuk mutasi stok.
- Shift opening/closing dan settlement.
- Approval manager flow untuk aksi tertentu.

## Tech Stack

### Backend

- PHP 8.3+
- Laravel 13
- Laravel Sanctum
- PostgreSQL
- PHPUnit 12

### Frontend

- Vue 3
- Pinia
- Vue Router
- Axios
- Tailwind CSS v4
- Chart.js + vue-chartjs
- Vite
- SheetJS (`xlsx`) untuk export Excel native

## Struktur Monorepo

```text
.
|-- pos-backend/   # Laravel API, migration, seeder, tests
|-- pos-frontend/  # Vue app POS/admin
|-- package.json   # root workspace package
```

Lokasi penting:

- `pos-backend/routes/api.php`: daftar endpoint API.
- `pos-backend/app/Services/TransactionService.php`: business rule checkout/transaksi utama.
- `pos-backend/database/seeders/DatabaseSeeder.php`: data demo awal.
- `pos-frontend/src/router/index.js`: route page frontend.
- `pos-frontend/src/pages`: halaman utama (Kasir, Riwayat, Bills, Settlement, dll).
- `pos-frontend/src/services/api.js`: konfigurasi axios + bearer token.

## Arsitektur Singkat

1. User login dari frontend, menerima token Sanctum.
2. Token disimpan di localStorage dan disisipkan otomatis di request API.
3. Kasir membuat transaksi dari halaman POS.
4. Backend memvalidasi payload, stok, approval, lalu menyimpan transaksi secara atomik.
5. Data transaksi dipakai kembali untuk billing, laporan, settlement, dan export Excel.

## Prasyarat

- PHP 8.3 atau lebih baru.
- Composer.
- Node.js 20.19+ atau 22.12+.
- npm.
- PostgreSQL.

## Panduan Instalasi Lokal

### 1) Setup Backend

```bash
cd pos-backend
composer install
```

Salin environment:

```bash
# Windows
copy .env.example .env

# macOS/Linux
cp .env.example .env
```

Generate key:

```bash
php artisan key:generate
```

Atur koneksi database PostgreSQL di `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=projectnganggur
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

Migrasi dan seed data:

```bash
php artisan migrate
php artisan db:seed
```

### 2) Setup Frontend

```bash
cd ../pos-frontend
npm install
```

Pastikan URL API frontend sesuai backend:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

Jika tidak diisi, frontend fallback ke URL tersebut.

## Menjalankan Aplikasi

### Backend

```bash
cd pos-backend
composer run dev
```

Alternatif minimal:

```bash
php artisan serve
```

Default backend: `http://127.0.0.1:8000`

### Frontend

```bash
cd pos-frontend
npm run dev
```

Default frontend: `http://127.0.0.1:5173`

## Akun Demo Seeder

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@pos.local` | `password` |
| Cashier | `cashier@pos.local` | `password` |

Seeder juga menyiapkan kategori, produk, customer, stok awal, dan sample transaksi.

## Endpoint API Utama

### Public / Semi-Public

- `GET /api/products`
- `POST /api/auth/login`

### Authenticated

- `GET /api/auth/me`
- `POST /api/auth/logout`
- `POST /api/checkout`

### Versioned (`/api/v1`)

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
- manager approval endpoints

Catatan: sebagian endpoint dibatasi middleware role `admin`.

## Script Penting

### Backend

- `composer run dev`: jalankan stack dev Laravel.
- `composer test`: jalankan test Laravel.
- `php artisan migrate`: jalankan migration.
- `php artisan db:seed`: isi data seed.

### Frontend

- `npm run dev`: jalankan Vite dev server.
- `npm run build`: build production.
- `npm run preview`: preview hasil build.
- `npm run lint`: jalankan lint.
- `npm run format`: format source frontend.

## Troubleshooting Umum

### 1) Frontend tidak bisa akses API

- Cek backend aktif di port yang benar.
- Cek `VITE_API_BASE_URL`.
- Pastikan token login tersedia.

### 2) Error database saat migrate

- Pastikan database PostgreSQL sudah dibuat.
- Verifikasi kredensial `.env`.
- Jalankan ulang `php artisan migrate`.

### 3) Export Excel tidak muncul

- Pastikan browser tidak memblokir download.
- Coba ulang dari halaman Riwayat dengan data transaksi tersedia.

## Roadmap Pengembangan

- Dokumentasi API request/response lebih detail.
- Peningkatan test coverage end-to-end checkout/refund/void.
- Integrasi printer thermal yang lebih kaya opsi.
- Hardening observability dan audit trail.

## Lisensi

MIT License.
