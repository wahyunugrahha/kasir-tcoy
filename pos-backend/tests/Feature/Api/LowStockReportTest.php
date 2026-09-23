<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LowStockReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_report_only_returns_products_at_or_below_threshold(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create(['name' => 'Minuman']);

        $low = Product::query()->create([
            'category_id' => $category->id, 'sku' => 'LOW', 'name' => 'Low Stock',
            'cost_price' => 1000, 'selling_price' => 2000, 'stock' => 3, 'min_stock' => 5,
        ]);
        $exact = Product::query()->create([
            'category_id' => $category->id, 'sku' => 'EXACT', 'name' => 'At Threshold',
            'cost_price' => 1000, 'selling_price' => 2000, 'stock' => 5, 'min_stock' => 5,
        ]);
        Product::query()->create([
            'category_id' => $category->id, 'sku' => 'OK', 'name' => 'Healthy Stock',
            'cost_price' => 1000, 'selling_price' => 2000, 'stock' => 50, 'min_stock' => 5,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/reports/low-stock')->assertOk();
        $ids = collect($response->json())->pluck('id')->all();

        $this->assertContains($low->id, $ids);
        $this->assertContains($exact->id, $ids);
        $this->assertCount(2, $ids);
    }
}
