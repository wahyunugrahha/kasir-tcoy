<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartialBillSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_off_a_partial_bill_marks_it_paid_and_earns_remaining_points(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create(['name' => 'Minuman']);
        $product = Product::query()->create([
            'category_id' => $category->id, 'sku' => 'BILL-1', 'name' => 'Kopi Susu',
            'cost_price' => 5000, 'selling_price' => 20000, 'stock' => 50,
        ]);
        $customer = Customer::query()->create(['name' => 'Rina', 'phone' => '0813', 'points' => 0]);
        Shift::query()->create(['user_id' => $admin->id, 'opening_cash' => 0, 'status' => 'open']);

        Sanctum::actingAs($admin);

        // Rp 20.000 bill, pay only Rp 8.000 up front -> partial, 0 points yet (< Rp 10.000 rate).
        $transactionId = $this->postJson('/api/checkout', [
            'customer_id' => $customer->id,
            'payment_method' => 'cash',
            'cash_received' => 8000,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('payment_status', 'partial')
            ->assertJsonPath('points_earned', 0)
            ->json('id');

        // Settle the remaining Rp 12.000 -> now fully paid, cumulative points = floor(20000/10000) = 2.
        $response = $this->postJson("/api/v1/transactions/{$transactionId}/pay", [
            'payment_method' => 'cash',
            'amount' => 12000,
        ])->assertOk();

        $response->assertJsonPath('payment_status', 'paid');
        $response->assertJsonPath('amount_paid', 20000);
        $response->assertJsonPath('points_earned', 2);

        $customer->refresh();
        $this->assertSame(2, $customer->points);

        // Bill is now paid — a second pay attempt must be rejected.
        $this->postJson("/api/v1/transactions/{$transactionId}/pay", [
            'payment_method' => 'cash',
            'amount' => 1000,
        ])->assertStatus(422);
    }
}
