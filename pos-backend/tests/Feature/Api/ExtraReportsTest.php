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

class ExtraReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_performance_profit_and_stock_valuation_reports(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create(['name' => 'Minuman']);
        $product = Product::query()->create([
            'category_id' => $category->id, 'sku' => 'REP-1', 'name' => 'Kopi',
            'cost_price' => 4000, 'selling_price' => 10000, 'stock' => 20, 'min_stock' => 5,
        ]);
        Shift::query()->create(['user_id' => $admin->id, 'opening_cash' => 0, 'status' => 'open']);

        app(TransactionService::class)->create([
            'user_id' => $admin->id, 'payment_method' => 'cash', 'cash_received' => 10000,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        Sanctum::actingAs($admin);

        $perf = $this->getJson('/api/v1/reports/cashier-performance')->assertOk()->json();
        $this->assertSame($admin->id, $perf[0]['user_id']);
        $this->assertEquals(10000, $perf[0]['total_sales']);

        $profit = $this->getJson('/api/v1/reports/profit-by-category')->assertOk()->json();
        $this->assertSame($category->id, $profit[0]['category_id']);
        $this->assertEquals(10000, $profit[0]['revenue']);
        $this->assertEquals(4000, $profit[0]['cogs']);
        $this->assertEquals(6000, $profit[0]['gross_profit']);

        // Remaining stock after the sale: 19 units x Rp 4.000 cost = Rp 76.000.
        $valuation = $this->getJson('/api/v1/reports/stock-valuation')->assertOk()->json();
        $this->assertEquals(76000, $valuation['total_value']);
    }

    public function test_void_refunds_report_lists_voided_transactions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::query()->create(['name' => 'Minuman']);
        $product = Product::query()->create([
            'category_id' => $category->id, 'sku' => 'REP-2', 'name' => 'Teh',
            'cost_price' => 2000, 'selling_price' => 8000, 'stock' => 20, 'min_stock' => 5,
        ]);
        Shift::query()->create(['user_id' => $admin->id, 'opening_cash' => 0, 'status' => 'open']);

        Sanctum::actingAs($admin);

        $transactionId = $this->postJson('/api/checkout', [
            'payment_method' => 'cash', 'cash_received' => 8000,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('id');

        $this->putJson("/api/v1/transactions/{$transactionId}/void", ['reason' => 'Testing void report'])->assertOk();

        $report = $this->getJson('/api/v1/reports/void-refunds')->assertOk()->json();
        $this->assertCount(1, $report['voided_transactions']);
        $this->assertEquals(8000, $report['total_voided']);
    }
}
