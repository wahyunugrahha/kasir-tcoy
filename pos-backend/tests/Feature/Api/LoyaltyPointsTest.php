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

class LoyaltyPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_earns_points_and_void_reverses_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create(['name' => 'Minuman']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SKU-PTS',
            'name' => 'Kopi',
            'cost_price' => 5000,
            'selling_price' => 20000,
            'stock' => 50,
        ]);
        $customer = Customer::query()->create(['name' => 'Budi', 'phone' => '0811', 'points' => 0]);
        Shift::query()->create(['user_id' => $admin->id, 'opening_cash' => 0, 'status' => 'open']);

        Sanctum::actingAs($admin);

        // Rp 20.000 spent / Rp 10.000 per point (TransactionService::POINTS_EARN_RATE) = 2 points earned.
        $response = $this->postJson('/api/checkout', [
            'customer_id' => $customer->id,
            'payment_method' => 'cash',
            'cash_received' => 20000,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $response->assertJsonPath('points_earned', 2);
        $customer->refresh();
        $this->assertSame(2, $customer->points);

        $transactionId = $response->json('id');

        $this->putJson("/api/v1/transactions/{$transactionId}/void", [
            'reason' => 'Testing point reversal',
        ])->assertOk();

        $customer->refresh();
        $this->assertSame(0, $customer->points, 'Voiding the sale should reverse the points it earned.');
    }

    public function test_checkout_redeems_points_as_discount_and_caps_at_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create(['name' => 'Minuman']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SKU-PTS-2',
            'name' => 'Teh',
            'cost_price' => 3000,
            'selling_price' => 10000,
            'stock' => 50,
        ]);
        // 200 points available; only 100 (worth Rp 100 * 100 = Rp 10.000) should be spendable
        // against a Rp 10.000 bill without going negative — TransactionService caps redemption
        // at what the grand total can actually absorb.
        $customer = Customer::query()->create(['name' => 'Ani', 'phone' => '0812', 'points' => 200]);
        Shift::query()->create(['user_id' => $admin->id, 'opening_cash' => 0, 'status' => 'open']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/checkout', [
            'customer_id' => $customer->id,
            'redeem_points' => 150,
            'payment_method' => 'cash',
            'cash_received' => 0,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $response->assertJsonPath('points_redeemed', 100);
        $response->assertJsonPath('grand_total', 0);
        $customer->refresh();
        $this->assertSame(100, $customer->points);
    }

    public function test_redeem_points_requires_customer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create(['name' => 'Minuman']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SKU-PTS-3',
            'name' => 'Air',
            'cost_price' => 1000,
            'selling_price' => 5000,
            'stock' => 50,
        ]);
        Shift::query()->create(['user_id' => $admin->id, 'opening_cash' => 0, 'status' => 'open']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/checkout', [
            'redeem_points' => 10,
            'payment_method' => 'cash',
            'cash_received' => 5000,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('redeem_points');

        $this->assertSame(0, Transaction::count());
    }
}
