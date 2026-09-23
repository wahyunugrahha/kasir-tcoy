<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shift;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_product_with_transaction_history_soft_deletes_instead_of_failing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create(['name' => 'Minuman']);
        $product = Product::query()->create([
            'category_id' => $category->id, 'sku' => 'SD-1', 'name' => 'Kopi',
            'cost_price' => 5000, 'selling_price' => 10000, 'stock' => 10,
        ]);
        Shift::query()->create(['user_id' => $admin->id, 'opening_cash' => 0, 'status' => 'open']);

        // Give the product real transaction history — this used to make a hard delete fail
        // with a foreign key violation (transaction_details_product_id_foreign).
        app(TransactionService::class)->create([
            'user_id' => $admin->id, 'payment_method' => 'cash', 'cash_received' => 10000,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/products/{$product->id}")->assertOk();

        // Soft-deleted: gone from the normal catalog query, but the row (and the history
        // pointing at it) still exists.
        $this->assertNull(Product::find($product->id));
        $this->assertNotNull(Product::withTrashed()->find($product->id));
        $this->assertDatabaseHas('transaction_details', ['product_id' => $product->id]);

        // A soft-deleted product can no longer be sold.
        $this->postJson('/api/checkout', [
            'payment_method' => 'cash', 'cash_received' => 10000,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
    }
}
