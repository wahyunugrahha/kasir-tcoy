<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,qris,debit'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $openShift = Shift::query()
            ->where('user_id', $validated['user_id'])
            ->where('status', 'open')
            ->first();

        if (! $openShift) {
            return response()->json([
                'message' => 'Checkout ditolak. Kasir wajib membuka shift terlebih dahulu.',
            ], 422);
        }

        $transaction = DB::transaction(function () use ($validated) {
            $dateCode = Carbon::now()->format('Ymd');
            $lastInvoice = Transaction::query()
                ->whereDate('created_at', Carbon::today())
                ->where('invoice_number', 'like', "INV-{$dateCode}-%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('invoice_number');

            $nextSequence = 1;
            if ($lastInvoice) {
                $parts = explode('-', $lastInvoice);
                $lastSequence = (int) end($parts);
                $nextSequence = $lastSequence + 1;
            }

            $invoiceNumber = sprintf('INV-%s-%03d', $dateCode, $nextSequence);

            $items = collect($validated['items']);
            $productIds = $items->pluck('product_id')->unique()->values();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $detailPayload = [];

            foreach ($items as $item) {
                /** @var Product|null $product */
                $product = $products->get($item['product_id']);

                if (! $product) {
                    abort(422, 'One or more products are invalid.');
                }

                $qty = (int) $item['quantity'];

                if ($product->stock < $qty) {
                    abort(422, "Insufficient stock for product {$product->name}.");
                }

                $lineSubtotal = (float) $product->selling_price * $qty;
                $subtotal += $lineSubtotal;

                $detailPayload[] = [
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'price_snapshot' => $product->selling_price,
                    'quantity' => $qty,
                    'subtotal' => $lineSubtotal,
                ];
            }

            $discount = (float) ($validated['discount'] ?? 0);
            $tax = (float) ($validated['tax'] ?? 0);
            $grandTotal = $subtotal - $discount + $tax;
            $cashReceived = (float) ($validated['cash_received'] ?? 0);
            $cashChange = max(0, $cashReceived - $grandTotal);
            $amountPaid = $validated['payment_method'] === 'cash' ? min($cashReceived, $grandTotal) : $grandTotal;
            $paymentStatus = $amountPaid <= 0 ? 'unpaid' : ($amountPaid < $grandTotal ? 'partial' : 'paid');

            if ($validated['payment_method'] === 'cash' && $cashReceived < $grandTotal) {
                abort(422, 'Cash received is less than grand total.');
            }

            $transaction = Transaction::create([
                'invoice_number' => $invoiceNumber,
                'user_id' => $validated['user_id'],
                'customer_id' => $validated['customer_id'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'grand_total' => $grandTotal,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $paymentStatus,
                'amount_paid' => $amountPaid,
                'cash_received' => $cashReceived,
                'cash_change' => $cashChange,
            ]);

            foreach ($detailPayload as $detail) {
                $transaction->details()->create($detail);

                $product = $products->get($detail['product_id']);
                $product->decrement('stock', $detail['quantity']);

                InventoryMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $validated['user_id'],
                    'type' => 'out',
                    'quantity' => $detail['quantity'],
                    'reference_type' => 'transaction',
                    'reference_id' => $transaction->id,
                    'notes' => 'Automatic deduction from checkout',
                ]);
            }

            return $transaction;
        });

        return response()->json(
            $transaction->load(['user:id,name,email', 'customer:id,name,phone', 'details.product:id,name,sku']),
            201
        );
    }
}
