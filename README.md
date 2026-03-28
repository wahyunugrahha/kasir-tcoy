# Kasir Tcoy

Bahasa: Indonesia | [English](README.en.md)

Kasir Tcoy adalah aplikasi Point of Sale berbasis monorepo yang terdiri dari backend API Laravel dan frontend Vue untuk operasional kasir, manajemen produk, settlement shift, dan laporan penjualan.

## Ringkasan

Proyek ini memisahkan tanggung jawab antara API dan client:

- `pos-backend` menyediakan REST API, autentikasi token dengan Laravel Sanctum, transaksi, inventory movement, shift settlement, dan reporting.
- `pos-frontend` menyediakan antarmuka kasir dan admin berbasis Vue 3, Pinia, Vue Router, Chart.js, dan Tailwind CSS v4.

Fitur utama yang sudah terlihat di codebase:

- Login berbasis token bearer.
- Dashboard kasir untuk checkout cepat.
- Hold order dan recall order pada keranjang.
- Cetak struk transaksi terakhir.
- Manajemen produk, kategori, customer, dan user.
- Riwayat transaksi dan detail transaksi.
- Void transaksi dengan restock stok otomatis.
- Settlement shift dengan perbandingan kas fisik vs kas sistem.
- Laporan ringkasan, penjualan per tanggal, dan produk terlaris.
- Audit log untuk aksi tertentu.

## Tech Stack

### Backend

- PHP 8.3+
- Laravel 13
- Laravel Sanctum
- PostgreSQL
- PHPUnit 12
- Vite untuk asset backend

### Frontend

- Vue 3
- Pinia
- Vue Router
- Axios
- Chart.js dan vue-chartjs
- Tailwind CSS v4
- Vite

## Struktur Repository

```text
.
|-- pos-backend/   # Laravel API + database + seeders + tests
|-- pos-frontend/  # Vue app untuk POS/admin
|-- package.json   # root dependency kecil untuk workspace
```

Struktur utama:

- `pos-backend/routes/api.php` berisi endpoint autentikasi, checkout, resource master data, transaksi, shift, report, inventory, dan audit log.
- `pos-backend/database/seeders/DatabaseSeeder.php` menyediakan akun demo, data kategori, produk, customer, stok awal, dan contoh transaksi.
- `pos-frontend/src/router/index.js` mendefinisikan halaman landing, login, POS, products, history, reports, settings, order list, bills, dan settlement.
- `pos-frontend/src/stores` berisi state auth dan cart.
- `pos-frontend/src/services/api.js` menangani base URL API dan injeksi bearer token.

## Fitur Per Modul

### Auth

- Login melalui endpoint `POST /api/auth/login`.
- Profil user aktif melalui `GET /api/auth/me`.
- Logout token aktif melalui `POST /api/auth/logout`.
- Frontend menyimpan sesi di `localStorage`.

### POS / Checkout

- Memuat katalog produk dari backend.
- Menambahkan item ke keranjang dengan validasi stok.
- Mendukung metode pembayaran `cash`, `qris`, dan `debit`.
- Menghitung subtotal, diskon, pajak, total akhir, pembayaran, dan kembalian.
- Menyimpan transaksi dan mengurangi stok secara atomik di backend.
- Menyediakan cetak struk untuk transaksi terakhir.

### Order Management

- Hold cart untuk menunda order.
- Recall held order ke keranjang aktif.
- Sinkronisasi jumlah item terhadap stok terbaru.

### Produk dan Inventori

- Endpoint untuk daftar, detail, tambah, ubah, dan hapus produk.
- Inventory movement tercatat untuk stok masuk dan keluar.
- Void transaksi akan mengembalikan stok dan membuat inventory movement baru.

### Shift Settlement

- Membuka shift baru dengan modal awal.
- Menutup shift dengan input kas fisik.
- Menghitung selisih kas.
- Menyimpan riwayat shift.

### Reporting

- Ringkasan penjualan hari ini dan bulan ini.
- Grafik penjualan harian.
- Grafik produk terlaris.

## Endpoint Utama

Endpoint yang terverifikasi dari backend:

### Public / semi-public

- `GET /api/products`
- `POST /api/auth/login`

### Authenticated

- `GET /api/auth/me`
- `POST /api/auth/logout`
- `POST /api/checkout`

### Versioned API

Semua endpoint berikut berada di bawah prefix `/api/v1` dan dilindungi `auth:sanctum`:

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

Beberapa endpoint dibatasi oleh middleware role `admin`, sementara kasir dapat mengakses alur operasional kasir dan transaksi sesuai kebutuhan.

## Akun Demo Seeder

Data seeder backend membuat akun berikut:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@pos.local` | `password` |
| Cashier | `cashier@pos.local` | `password` |

Seeder juga menambahkan:

- 3 kategori awal.
- 6 produk awal.
- stok awal beserta inventory movement seed.
- 3 customer contoh.
- 1 transaksi contoh.

## Menjalankan Project Secara Lokal

### Prasyarat

- PHP 8.3 atau lebih baru.
- Composer.
- Node.js 20.19+ atau 22.12+.
- npm.
- PostgreSQL.

### 1. Setup backend

Opsi manual:

```bash
cd pos-backend
composer install
```

Salin file environment:

```bash
# Windows
copy .env.example .env

# macOS / Linux
cp .env.example .env
```

Lalu generate application key:

```bash
php artisan key:generate
```

Alternatif cepat, backend juga menyediakan script setup bawaan:

```bash
cd pos-backend
composer run setup
```

Jika memakai `composer run setup`, tetap jalankan `php artisan db:seed` bila Anda ingin data demo terisi.

Konfigurasi database PostgreSQL di file `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=projectnganggur
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

Lalu jalankan migrasi dan seed:

```bash
php artisan migrate
php artisan db:seed
```

Install asset backend bila diperlukan:

```bash
npm install
```

Untuk development backend:

```bash
composer run dev
```

Perintah tersebut menjalankan server Laravel, queue listener, log watcher, dan Vite backend secara paralel.

Alternatif minimal:

```bash
php artisan serve
```

Backend API default akan tersedia di `http://127.0.0.1:8000`.

### 2. Setup frontend

```bash
cd pos-frontend
npm install
npm run dev
```

Frontend default akan tersedia di `http://127.0.0.1:5173`.

Secara default frontend memakai base URL:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

Jika variabel ini tidak diatur, frontend tetap fallback ke URL tersebut.

### 3. Build production

Backend asset:

```bash
cd pos-backend
npm run build
```

Frontend:

```bash
cd pos-frontend
npm run build
```

## Scripts Penting

### Backend

- `composer run dev` menjalankan environment development Laravel.
- `composer test` menjalankan test Laravel.
- `npm run dev` menjalankan Vite backend.
- `npm run build` build asset backend.

### Frontend

- `npm run dev` menjalankan Vite frontend.
- `npm run build` build frontend.
- `npm run preview` preview hasil build.
- `npm run lint` menjalankan Oxlint dan ESLint.
- `npm run format` menjalankan Prettier pada source frontend.

## Alur Data Singkat

1. User login dari frontend dan menerima bearer token dari Laravel Sanctum.
2. Token disimpan di `localStorage` dan dikirim otomatis lewat interceptor Axios.
3. Kasir memilih produk dan frontend mengirim payload checkout ke backend.
4. Backend membuat nomor invoice, memvalidasi stok, menyimpan detail transaksi, dan mengurangi stok dalam transaksi database.
5. Inventory movement, shift, dan laporan dibangun dari data transaksi yang sama.

## Pengembangan Lanjutan

Beberapa area yang tampak siap dikembangkan lebih lanjut:

- dokumentasi request/response API yang lebih detail.
- test coverage untuk flow POS dan settlement.
- deployment guide untuk production.
- screenshot UI untuk landing, cashier, reports, dan settlement.

## Catatan

- Environment proyek ini digunakan dengan PostgreSQL. Sesuaikan kredensial database di `.env` sebelum menjalankan migrasi.
- Frontend dan backend berjalan sebagai aplikasi terpisah, sehingga deployment juga bisa dipisah.
- Beberapa route frontend dibatasi role admin melalui metadata router.

## Lisensi

Project ini menggunakan lisensi MIT. Lihat file [LICENSE](LICENSE).

## Versi Bahasa Lain

- Bahasa Indonesia: file ini.
- English: [README.en.md](README.en.md)
