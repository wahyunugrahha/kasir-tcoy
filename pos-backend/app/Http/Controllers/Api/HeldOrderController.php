<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeldOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeldOrderController extends Controller
{
    // Held orders are a shared queue — any cashier can recall an order any other
    // cashier put on hold (e.g. handing off a register), so index/destroy aren't
    // scoped to the creating user.
    public function index(): JsonResponse
    {
        $heldOrders = HeldOrder::query()
            ->with('user:id,name')
            ->oldest()
            ->get();

        return response()->json($heldOrders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $heldOrder = HeldOrder::create([
            'user_id' => $request->user()->id,
            'label' => $validated['label'],
            'items' => $validated['items'],
            'subtotal' => $validated['subtotal'],
        ]);

        return response()->json($heldOrder->load('user:id,name'), 201);
    }

    public function destroy(HeldOrder $heldOrder): JsonResponse
    {
        $heldOrder->delete();

        return response()->json(['message' => 'Held order removed']);
    }
}
