<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $admin = User::query()->create([
                'name' => 'Admin POS',
                'email' => 'admin@pos.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]);

            $cashier = User::query()->create([
                'name' => 'Kasir Satu',
                'email' => 'cashier@pos.local',
                'password' => Hash::make('password'),
                'role' => 'cashier',
            ]);

            $categories = [
                'Makanan',
                'Minuman',
                'Snack',
            ];

            $categoryMap = [];
            foreach ($categories as $name) {
                $categoryMap[$name] = Category::query()->create(['name' => $name]);
            }

            $productSeed = [
                ['category' => 'Makanan', 'sku' => 'FD-RND-001', 'name' => 'Nasi Goreng', 'cost' => 12000, 'sell' => 18000, 'stock' => 50],
                ['category' => 'Makanan', 'sku' => 'FD-MGR-002', 'name' => 'Mie Goreng', 'cost' => 9000, 'sell' => 15000, 'stock' => 40],
                ['category' => 'Minuman', 'sku' => 'DR-EST-003', 'name' => 'Es Teh Manis', 'cost' => 2500, 'sell' => 5000, 'stock' => 100],
                ['category' => 'Minuman', 'sku' => 'DR-ESP-004', 'name' => 'Es Jeruk', 'cost' => 3500, 'sell' => 7000, 'stock' => 80],
                ['category' => 'Snack', 'sku' => 'SN-CRK-005', 'name' => 'Keripik Singkong', 'cost' => 4000, 'sell' => 8000, 'stock' => 60],
                ['category' => 'Snack', 'sku' => 'SN-CKL-006', 'name' => 'Cokelat Bar', 'cost' => 5000, 'sell' => 9500, 'stock' => 45],
            ];

            $products = collect($productSeed)->map(function (array $item) use ($categoryMap) {
                return Product::query()->create([
                    'category_id' => $categoryMap[$item['category']]->id,
                    'sku' => $item['sku'],
                    'name' => $item['name'],
                    'cost_price' => $item['cost'],
                    'selling_price' => $item['sell'],
                    'stock' => $item['stock'],
                ]);
            });

            foreach ($products as $product) {
                InventoryMovement::query()->create([
                    'product_id' => $product->id,
                    'user_id' => $admin->id,
                    'type' => 'in',
                    'quantity' => $product->stock,
                    'reference_type' => 'seed',
                    'reference_id' => null,
                    'notes' => 'Initial stock from seeder',
                ]);
            }

            $customers = [
                ['name' => 'Pelanggan Umum', 'phone' => '081200000001', 'points' => 0],
                ['name' => 'Budi Santoso', 'phone' => '081200000002', 'points' => 120],
                ['name' => 'Siti Rahma', 'phone' => '081200000003', 'points' => 80],
            ];

            $createdCustomers = collect($customers)->map(function (array $customer) {
                return Customer::query()->create($customer);
            });

            $transaction = Transaction::query()->create([
                'invoice_number' => 'INV-20260327-001',
                'user_id' => $cashier->id,
                'customer_id' => $createdCustomers[1]->id,
                'subtotal' => 23000,
                'discount' => 1000,
                'tax' => 2420,
                'grand_total' => 24420,
                'payment_method' => 'cash',
                'cash_received' => 30000,
                'cash_change' => 5580,
            ]);

            $lineOne = $products->firstWhere('sku', 'FD-RND-001');
            $lineTwo = $products->firstWhere('sku', 'DR-EST-003');

            $transaction->details()->create([
                'product_id' => $lineOne->id,
                'product_name_snapshot' => $lineOne->name,
                'price_snapshot' => 18000,
                'quantity' => 1,
                'subtotal' => 18000,
            ]);

            $transaction->details()->create([
                'product_id' => $lineTwo->id,
                'product_name_snapshot' => $lineTwo->name,
                'price_snapshot' => 5000,
                'quantity' => 1,
                'subtotal' => 5000,
            ]);

            $lineOne->decrement('stock', 1);
            $lineTwo->decrement('stock', 1);

            InventoryMovement::query()->create([
                'product_id' => $lineOne->id,
                'user_id' => $cashier->id,
                'type' => 'out',
                'quantity' => 1,
                'reference_type' => 'transaction',
                'reference_id' => $transaction->id,
                'notes' => 'Seeder sample sale',
            ]);

            InventoryMovement::query()->create([
                'product_id' => $lineTwo->id,
                'user_id' => $cashier->id,
                'type' => 'out',
                'quantity' => 1,
                'reference_type' => 'transaction',
                'reference_id' => $transaction->id,
                'notes' => 'Seeder sample sale',
            ]);
        });
    }
}
