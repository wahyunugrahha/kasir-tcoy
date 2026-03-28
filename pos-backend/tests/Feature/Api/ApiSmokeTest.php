<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Category $category;

    private Product $product;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin POS',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'role' => 'admin',
            'manager_pin' => '123456',
        ]);

        $this->cashier = User::factory()->create([
            'name' => 'Cashier POS',
            'email' => 'cashier@example.com',
            'password' => 'secret123',
            'role' => 'cashier',
        ]);

        $this->category = Category::query()->create([
            'name' => 'Minuman',
        ]);

        $this->product = Product::query()->create([
            'category_id' => $this->category->id,
            'sku' => 'SKU-001',
            'name' => 'Es Teh',
            'cost_price' => 3000,
            'selling_price' => 7000,
            'stock' => 100,
            'description' => 'Smoke test product',
        ]);

        $this->customer = Customer::query()->create([
            'name' => 'Pelanggan Satu',
            'phone' => '081234567890',
            'points' => 0,
        ]);

        Shift::query()->create([
            'user_id' => $this->admin->id,
            'opening_cash' => 200000,
            'status' => 'open',
        ]);

        Shift::query()->create([
            'user_id' => $this->cashier->id,
            'opening_cash' => 150000,
            'status' => 'open',
        ]);
    }

    public function test_public_and_auth_endpoints_are_reachable(): void
    {
        $this->getJson('/api/products')->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => $this->admin->email,
            'password' => 'secret123',
            'device_name' => 'phpunit',
        ])->assertOk()->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_protected_endpoints_require_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
        $this->postJson('/api/checkout', [])->assertStatus(401);
        $this->getJson('/api/v1/users')->assertStatus(401);
        $this->getJson('/api/v1/customers')->assertStatus(401);
        $this->getJson('/api/v1/transactions')->assertStatus(401);
        $this->getJson('/api/v1/reports/summary')->assertStatus(401);
    }

    public function test_admin_can_smoke_all_api_endpoints(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/auth/me')->assertOk();

        $newCategory = $this->postJson('/api/v1/categories', [
            'name' => 'Snack',
        ])->assertCreated()->json('id');

        $this->getJson('/api/v1/categories')->assertOk();
        $this->getJson("/api/v1/categories/{$newCategory}")->assertOk();
        $this->putJson("/api/v1/categories/{$newCategory}", [
            'name' => 'Snack Updated',
        ])->assertOk();

        $this->getJson('/api/v1/products')->assertOk();
        $this->getJson('/api/products')->assertOk();
        $this->getJson('/api/v1/products/'.$this->product->id)->assertOk();

        $newProductId = $this->postJson('/api/v1/products', [
            'category_id' => $this->category->id,
            'sku' => 'SKU-002',
            'name' => 'Keripik',
            'cost_price' => 5000,
            'selling_price' => 12000,
            'stock' => 40,
            'description' => 'Created by smoke test',
        ])->assertCreated()->json('id');

        $this->putJson('/api/v1/products/'.$newProductId, [
            'category_id' => $this->category->id,
            'sku' => 'SKU-002-UPD',
            'name' => 'Keripik Updated',
            'cost_price' => 5500,
            'selling_price' => 13000,
            'stock' => 35,
            'description' => 'Updated by smoke test',
        ])->assertOk();

        $this->postJson('/api/products', [
            'category_id' => $this->category->id,
            'sku' => 'SKU-003',
            'name' => 'Mie Instan',
            'cost_price' => 2500,
            'selling_price' => 4500,
            'stock' => 60,
            'description' => 'Created by legacy endpoint',
        ])->assertCreated();

        $this->getJson('/api/v1/customers')->assertOk();

        $newCustomer = $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Dua',
            'phone' => '081234567891',
            'points' => 15,
        ])->assertCreated()->json('id');

        $this->getJson('/api/v1/customers/'.$newCustomer)->assertOk();

        $this->putJson('/api/v1/customers/'.$newCustomer, [
            'name' => 'Pelanggan Dua Updated',
            'phone' => '081234567892',
            'points' => 30,
        ])->assertOk();

        $this->getJson('/api/v1/users')->assertOk();
        $this->getJson('/api/v1/managers')->assertOk();
        $this->postJson('/api/v1/managers/verify-pin', [
            'manager_user_id' => $this->admin->id,
            'manager_pin' => '123456',
        ])->assertOk();

        $newUser = $this->postJson('/api/v1/users', [
            'name' => 'Api User',
            'email' => 'api-user@example.com',
            'password' => 'StrongPass123!',
            'role' => 'cashier',
        ])->assertCreated()->json('id');

        $this->getJson('/api/v1/users/'.$newUser)->assertOk();

        $this->putJson('/api/v1/users/'.$newUser, [
            'name' => 'Api User Updated',
            'email' => 'api-user-updated@example.com',
            'password' => 'StrongPass123!',
            'role' => 'cashier',
        ])->assertOk();

        $this->getJson('/api/v1/inventory-movements')->assertOk();

        $this->postJson('/api/v1/inventory-movements', [
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'type' => 'in',
            'quantity' => 5,
            'reference_type' => 'manual',
            'reference_id' => 1,
            'notes' => 'Stock opname test',
        ])->assertCreated();

        $checkoutPayload = [
            'user_id' => $this->admin->id,
            'customer_id' => $this->customer->id,
            'payment_method' => 'cash',
            'cash_received' => 20000,
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]],
        ];

        $this->postJson('/api/checkout', $checkoutPayload)->assertCreated();

        $transactionOne = $this->postJson('/api/v1/transactions', [
            'user_id' => $this->admin->id,
            'customer_id' => $this->customer->id,
            'payment_method' => 'cash',
            'cash_received' => 22000,
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]],
        ])->assertCreated()->json('id');

        $transactionTwo = $this->postJson('/api/v1/transactions', [
            'user_id' => $this->admin->id,
            'customer_id' => $this->customer->id,
            'payment_method' => 'cash',
            'cash_received' => 24000,
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]],
        ])->assertCreated()->json('id');

        $this->getJson('/api/v1/transactions')->assertOk();
        $this->getJson('/api/v1/transactions/'.$transactionOne)->assertOk();

        /** @var Transaction $transactionForRefund */
        $transactionForRefund = Transaction::query()->with('details')->findOrFail($transactionOne);
        $detail = $transactionForRefund->details->firstOrFail();

        $this->postJson('/api/v1/transactions/'.$transactionOne.'/refund', [
            'reason' => 'Produk rusak ringan',
            'items' => [[
                'transaction_detail_id' => $detail->id,
                'quantity' => 1,
            ]],
        ])->assertCreated();

        $this->putJson('/api/v1/transactions/'.$transactionTwo.'/void', [
            'reason' => 'Pesanan dibatalkan customer',
        ])->assertOk();

        $this->getJson('/api/v1/shifts')->assertOk();

        $newShiftId = $this->postJson('/api/v1/shifts', [
            'user_id' => $newUser,
            'opening_cash' => 120000,
        ])->assertCreated()->json('id');

        $this->getJson('/api/v1/shifts/'.$newShiftId)->assertOk();

        $this->postJson('/api/v1/shifts/'.$newShiftId.'/cash-movements', [
            'type' => 'cash_drop',
            'amount' => 10000,
            'reason' => 'Setor kas awal',
            'notes' => 'Smoketest',
        ])->assertCreated();

        $this->putJson('/api/v1/shifts/'.$newShiftId.'/close', [
            'closing_cash_physical' => 130000,
            'notes' => 'Close by smoke test',
        ])->assertOk();

        $this->getJson('/api/v1/reports/summary')->assertOk();
        $this->getJson('/api/v1/reports/sales-by-date')->assertOk();
        $this->getJson('/api/v1/reports/top-products')->assertOk();

        $this->getJson('/api/v1/audit-logs')->assertOk();

        $this->deleteJson('/api/v1/products/'.$newProductId)->assertOk();
        $this->deleteJson('/api/v1/customers/'.$newCustomer)->assertOk();
        $this->deleteJson('/api/v1/categories/'.$newCategory)->assertOk();
        $this->deleteJson('/api/v1/users/'.$newUser)->assertOk();

        $this->postJson('/api/auth/logout')->assertOk();
    }

    public function test_cashier_is_forbidden_for_admin_only_routes(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->getJson('/api/v1/users')->assertStatus(403);
        $this->getJson('/api/v1/categories')->assertStatus(403);
        $this->getJson('/api/v1/reports/summary')->assertStatus(403);
        $this->getJson('/api/v1/audit-logs')->assertStatus(403);
    }
}
