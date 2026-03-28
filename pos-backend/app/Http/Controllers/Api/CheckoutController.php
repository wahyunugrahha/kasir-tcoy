<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(private readonly TransactionService $transactionService) {}

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $transaction = $this->transactionService->create(
            validated: $validated,
            requireOpenShift: true,
            movementNotes: 'Automatic deduction from checkout'
        );

        return response()->json(
            $transaction->load([
                'user:id,name,email',
                'customer:id,name,phone',
                'details.product:id,name,sku',
                'details.modifiers:id,transaction_detail_id,name,price_delta,quantity,notes',
                'payments:id,transaction_id,payment_method,amount,reference_number',
            ]),
            201
        );
    }
}
