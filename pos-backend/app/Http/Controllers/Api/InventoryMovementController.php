<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryMovementController extends Controller
{
    public function index(): JsonResponse
    {
        $movements = InventoryMovement::query()
            ->with(['product:id,name,sku', 'user:id,name,email'])
            ->latest()
            ->paginate(20);

        return response()->json($movements);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['required', 'in:in,out,adjustment'],
            'quantity' => ['required', 'integer'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ]);

        $movement = DB::transaction(function () use ($validated) {
            $product = Product::query()->lockForUpdate()->findOrFail($validated['product_id']);
            $quantity = (int) $validated['quantity'];

            if ($validated['type'] === 'in') {
                if ($quantity <= 0) {
                    abort(422, 'Quantity for incoming stock must be greater than zero.');
                }
                $product->increment('stock', $quantity);
            }

            if ($validated['type'] === 'out') {
                if ($quantity <= 0) {
                    abort(422, 'Quantity for outgoing stock must be greater than zero.');
                }

                if ($product->stock < $quantity) {
                    abort(422, 'Insufficient stock for this movement.');
                }

                $product->decrement('stock', $quantity);
            }

            if ($validated['type'] === 'adjustment') {
                if ($quantity === 0) {
                    abort(422, 'Adjustment quantity cannot be zero.');
                }

                $newStock = $product->stock + $quantity;

                if ($newStock < 0) {
                    abort(422, 'Adjustment would make stock negative.');
                }

                $product->update(['stock' => $newStock]);
            }

            return InventoryMovement::create($validated);
        });

        return response()->json($movement->load(['product:id,name,sku', 'user:id,name,email']), 201);
    }
}
