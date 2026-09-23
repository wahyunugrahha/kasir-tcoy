<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HeldOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_held_order_can_be_created_and_is_visible_to_other_cashiers(): void
    {
        $cashierA = User::factory()->create(['role' => 'cashier']);
        $cashierB = User::factory()->create(['role' => 'cashier']);

        Sanctum::actingAs($cashierA);

        $heldOrderId = $this->postJson('/api/v1/held-orders', [
            'label' => 'Order #1',
            'items' => [['product_id' => 1, 'name' => 'Kopi', 'quantity' => 2, 'price' => 15000, 'subtotal' => 30000]],
            'subtotal' => 30000,
        ])->assertCreated()->json('id');

        // A different cashier (e.g. handing off the register) can see and recall it.
        Sanctum::actingAs($cashierB);
        $this->getJson('/api/v1/held-orders')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.label', 'Order #1')
            ->assertJsonPath('0.user.id', $cashierA->id);

        $this->deleteJson("/api/v1/held-orders/{$heldOrderId}")->assertOk();
        $this->getJson('/api/v1/held-orders')->assertOk()->assertJsonCount(0);
    }
}
