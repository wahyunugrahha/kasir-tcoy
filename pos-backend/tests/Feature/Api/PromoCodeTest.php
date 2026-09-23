<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\PromoCode;
use App\Models\Product;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_applies_promo_discount_and_increments_usage(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create(['name' => 'Minuman']);
        $product = Product::query()->create([
            'category_id' => $category->id, 'sku' => 'PROMO-1', 'name' => 'Kopi',
            'cost_price' => 5000, 'selling_price' => 20000, 'stock' => 50,
        ]);
        $promo = PromoCode::query()->create([
            'code' => 'diskon10', // lowercase on purpose — lookup must be case-insensitive
            'discount_type' => 'percent',
            'discount_value' => 10,
            'usage_limit' => 1,
            'times_used' => 0,
            'active' => true,
        ]);
        Shift::query()->create(['user_id' => $admin->id, 'opening_cash' => 0, 'status' => 'open']);

        Sanctum::actingAs($admin);

        // Rp 20.000 - 10% = Rp 18.000.
        $response = $this->postJson('/api/checkout', [
            'promo_code' => 'DISKON10',
            'payment_method' => 'cash',
            'cash_received' => 18000,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $response->assertJsonPath('grand_total', 18000);
        $response->assertJsonPath('promo_code', 'diskon10');
        $response->assertJsonPath('promo_discount_amount', 2000);

        $promo->refresh();
        $this->assertSame(1, $promo->times_used);

        // Usage limit of 1 is now exhausted — a second use must be rejected.
        $this->postJson('/api/checkout', [
            'promo_code' => 'DISKON10',
            'payment_method' => 'cash',
            'cash_received' => 20000,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('promo_code');
    }

    public function test_expired_promo_code_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create(['name' => 'Minuman']);
        $product = Product::query()->create([
            'category_id' => $category->id, 'sku' => 'PROMO-2', 'name' => 'Teh',
            'cost_price' => 3000, 'selling_price' => 10000, 'stock' => 50,
        ]);
        PromoCode::query()->create([
            'code' => 'EXPIRED', 'discount_type' => 'fixed', 'discount_value' => 5000,
            'ends_at' => now()->subDay(), 'active' => true,
        ]);
        Shift::query()->create(['user_id' => $admin->id, 'opening_cash' => 0, 'status' => 'open']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/checkout', [
            'promo_code' => 'EXPIRED',
            'payment_method' => 'cash',
            'cash_received' => 10000,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('promo_code');
    }
}
