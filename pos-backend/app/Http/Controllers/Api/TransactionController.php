<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::query()
            ->with([
                'user:id,name,email',
                'customer:id,name,phone',
                'details:id,transaction_id,product_id,product_name_snapshot,price_snapshot,quantity,subtotal',
            ])
            ->latest();

        if ($request->filled('payment_status')) {
            $statuses = collect(explode(',', (string) $request->string('payment_status')))
                ->map(fn ($status) => trim($status))
                ->filter(fn ($status) => in_array($status, ['paid', 'partial', 'unpaid'], true))
                ->values();

            if ($statuses->isNotEmpty()) {
                $query->whereIn('payment_status', $statuses->all());
            }
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->string('payment_method'));
        }

        if ($request->filled('is_voided')) {
            $query->where('is_voided', filter_var((string) $request->string('is_voided'), FILTER_VALIDATE_BOOLEAN));
        }

        $transactions = $query->paginate((int) $request->integer('per_page', 20));

        return response()->json($transactions);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $transaction->load([
            'user:id,name,email',
            'customer:id,name,phone',
            'details.product:id,name,sku',
        ]);

        return response()->json($transaction);
    }

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
                    'notes' => 'Automatic deduction from sales transaction',
                ]);
            }

            return $transaction;
        });

        return response()->json(
            $transaction->load(['user:id,name,email', 'customer:id,name,phone', 'details.product:id,name,sku']),
            201
        );
    }

    public function void(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->is_voided) {
            return response()->json(['message' => 'Transaksi ini sudah di-void sebelumnya.'], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($transaction, $validated, $request) {
            $transaction->load('details');

            foreach ($transaction->details as $detail) {
                $product = Product::query()->whereKey($detail->product_id)->lockForUpdate()->first();

                if ($product) {
                    $product->increment('stock', $detail->quantity);

                    InventoryMovement::create([
                        'product_id' => $product->id,
                        'user_id' => $request->user()->id,
                        'type' => 'in',
                        'quantity' => $detail->quantity,
                        'reference_type' => 'transaction_void',
                        'reference_id' => $transaction->id,
                        'notes' => 'Restock from void transaction',
                    ]);
                }
            }

            $oldValues = $transaction->only(['payment_status', 'amount_paid', 'cash_received', 'cash_change', 'is_voided', 'voided_at', 'voided_by', 'void_reason']);

            $transaction->update([
                'payment_status' => 'unpaid',
                'amount_paid' => 0,
                'cash_received' => 0,
                'cash_change' => 0,
                'is_voided' => true,
                'voided_at' => now(),
                'voided_by' => $request->user()->id,
                'void_reason' => $validated['reason'],
            ]);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'transaction.voided',
                'entity_type' => 'transaction',
                'entity_id' => $transaction->id,
                'old_values' => $oldValues,
                'new_values' => $transaction->only(['payment_status', 'amount_paid', 'cash_received', 'cash_change', 'is_voided', 'voided_at', 'voided_by', 'void_reason']),
                'metadata' => ['invoice_number' => $transaction->invoice_number],
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        });

        return response()->json([
            'message' => 'Transaksi berhasil di-void.',
            'transaction' => $transaction->fresh(['user:id,name,email', 'customer:id,name,phone', 'details.product:id,name,sku', 'voidedBy:id,name,email']),
        ]);
    }
}
