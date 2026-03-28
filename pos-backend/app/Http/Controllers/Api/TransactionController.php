<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionRefund;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transactionService) {}

    /**
     * @return array<int, string>
     */
    private function detailRelationsWithOptionalVariant(): array
    {
        $relations = [
            'details.product:id,name,sku',
            'details.modifiers:id,transaction_detail_id,name,price_delta,quantity,notes',
        ];

        if (Schema::hasTable('product_variants')) {
            $relations[] = 'details.variant:id,product_id,name,sku';
        }

        return $relations;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Transaction::query()
            ->with([
                'user:id,name,email',
                'customer:id,name,phone',
                'details:id,transaction_id,product_id,variant_id,product_name_snapshot,variant_name_snapshot,price_snapshot,cogs_snapshot,line_discount_type,line_discount_rate,line_discount_amount,line_tax_amount,net_subtotal,quantity,subtotal',
                'payments:id,transaction_id,payment_method,amount,reference_number',
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

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', (string) $request->string('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', (string) $request->string('end_date'));
        }

        $transactions = $query->paginate((int) $request->integer('per_page', 20));

        return response()->json($transactions);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $transaction->load(array_merge([
            'user:id,name,email',
            'customer:id,name,phone',
            'payments:id,transaction_id,payment_method,amount,reference_number',
            'refunds:id,transaction_id,processed_by,refund_total,reason,created_at',
        ], $this->detailRelationsWithOptionalVariant()));

        return response()->json($transaction);
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $transaction = $this->transactionService->create(
            validated: $validated,
            requireOpenShift: true,
            movementNotes: 'Automatic deduction from sales transaction'
        );

        return response()->json(
            $transaction->load(array_merge([
                'user:id,name,email',
                'customer:id,name,phone',
                'payments:id,transaction_id,payment_method,amount,reference_number',
            ], $this->detailRelationsWithOptionalVariant())),
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
            'manager_user_id' => ['nullable', 'exists:users,id'],
            'manager_pin' => ['nullable', 'string', 'min:4', 'max:20'],
        ]);

        if ($request->user()?->role === 'cashier') {
            if (empty($validated['manager_user_id']) || empty($validated['manager_pin'])) {
                return response()->json(['message' => 'Void oleh kasir memerlukan approval manager PIN.'], 422);
            }

            $manager = User::query()->find($validated['manager_user_id']);

            if (! $manager || $manager->role !== 'admin' || empty($manager->manager_pin) || ! Hash::check($validated['manager_pin'], $manager->manager_pin)) {
                return response()->json(['message' => 'PIN manager tidak valid untuk approval void.'], 422);
            }
        }

        $actorId = (int) $request->user()->id;
        $ipAddress = $request->ip();
        $userAgent = (string) $request->userAgent();

        DB::transaction(function () use ($transaction, $validated, $actorId, $ipAddress, $userAgent) {
            $transaction->load('details');

            foreach ($transaction->details as $detail) {
                $product = Product::query()->whereKey($detail->product_id)->lockForUpdate()->first();

                if ($product) {
                    $product->increment('stock', $detail->quantity);

                    InventoryMovement::create([
                        'product_id' => $product->id,
                        'user_id' => $actorId,
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
                'voided_by' => $actorId,
                'void_reason' => $validated['reason'],
            ]);

            AuditLog::create([
                'user_id' => $actorId,
                'action' => 'transaction.voided',
                'entity_type' => 'transaction',
                'entity_id' => $transaction->id,
                'old_values' => $oldValues,
                'new_values' => $transaction->only(['payment_status', 'amount_paid', 'cash_received', 'cash_change', 'is_voided', 'voided_at', 'voided_by', 'void_reason']),
                'metadata' => ['invoice_number' => $transaction->invoice_number],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        });

        return response()->json([
            'message' => 'Transaksi berhasil di-void.',
            'transaction' => $transaction->fresh(['user:id,name,email', 'customer:id,name,phone', 'details.product:id,name,sku', 'voidedBy:id,name,email']),
        ]);
    }

    public function refund(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->is_voided) {
            return response()->json(['message' => 'Transaksi sudah di-void, tidak bisa diproses refund parsial.'], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.transaction_detail_id' => ['required', 'exists:transaction_details,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'manager_user_id' => ['nullable', 'exists:users,id'],
            'manager_pin' => ['nullable', 'string', 'min:4', 'max:20'],
        ]);

        $actor = $request->user();
        if ($actor?->role === 'cashier') {
            if (empty($validated['manager_user_id']) || empty($validated['manager_pin'])) {
                return response()->json(['message' => 'Refund oleh kasir memerlukan approval manager PIN.'], 422);
            }

            $manager = User::query()->find($validated['manager_user_id']);
            if (! $manager || $manager->role !== 'admin' || empty($manager->manager_pin) || ! Hash::check($validated['manager_pin'], $manager->manager_pin)) {
                return response()->json(['message' => 'PIN manager tidak valid untuk approval refund.'], 422);
            }
        }

        $actorId = (int) $actor->id;
        $ipAddress = $request->ip();
        $userAgent = (string) $request->userAgent();

        $refund = DB::transaction(function () use ($transaction, $validated, $actorId, $ipAddress, $userAgent) {
            $transaction->load('details');

            $detailMap = $transaction->details->keyBy('id');
            $refundTotal = 0;

            $refund = TransactionRefund::query()->create([
                'transaction_id' => $transaction->id,
                'processed_by' => $actorId,
                'reason' => $validated['reason'],
                'refund_total' => 0,
            ]);

            foreach ($validated['items'] as $item) {
                /** @var TransactionDetail|null $detail */
                $detail = $detailMap->get((int) $item['transaction_detail_id']);

                if (! $detail) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => 'Ada item refund yang bukan bagian dari transaksi ini.',
                    ]);
                }

                $alreadyRefundedQty = $detail->refundItems()->sum('quantity');
                $remainingQty = (int) $detail->quantity - (int) $alreadyRefundedQty;
                $refundQty = (int) $item['quantity'];

                if ($refundQty > $remainingQty) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => "Qty refund melebihi sisa qty untuk {$detail->product_name_snapshot}.",
                    ]);
                }

                $unitPrice = (float) $detail->subtotal / max(1, (int) $detail->quantity);
                $lineRefundTotal = $unitPrice * $refundQty;

                $refund->items()->create([
                    'transaction_detail_id' => $detail->id,
                    'quantity' => $refundQty,
                    'unit_price_snapshot' => $unitPrice,
                    'line_refund_total' => $lineRefundTotal,
                ]);

                $refundTotal += $lineRefundTotal;

                $product = Product::query()->whereKey($detail->product_id)->lockForUpdate()->first();
                if ($product) {
                    $product->increment('stock', $refundQty);

                    InventoryMovement::query()->create([
                        'product_id' => $product->id,
                        'user_id' => $actorId,
                        'type' => 'in',
                        'quantity' => $refundQty,
                        'reference_type' => 'transaction_refund',
                        'reference_id' => $refund->id,
                        'notes' => 'Restock from partial refund',
                    ]);
                }
            }

            $refund->update(['refund_total' => $refundTotal]);

            $newRefundedAmount = (float) $transaction->refunded_amount + $refundTotal;
            $newAmountPaid = max(0, (float) $transaction->amount_paid - $refundTotal);
            $newStatus = $newAmountPaid <= 0 ? 'unpaid' : ($newAmountPaid < (float) $transaction->grand_total ? 'partial' : 'paid');

            $transaction->update([
                'refunded_amount' => $newRefundedAmount,
                'amount_paid' => $newAmountPaid,
                'payment_status' => $newStatus,
            ]);

            AuditLog::create([
                'user_id' => $actorId,
                'action' => 'transaction.partial_refund',
                'entity_type' => 'transaction',
                'entity_id' => $transaction->id,
                'old_values' => [
                    'refunded_amount' => $transaction->getOriginal('refunded_amount'),
                    'amount_paid' => $transaction->getOriginal('amount_paid'),
                    'payment_status' => $transaction->getOriginal('payment_status'),
                ],
                'new_values' => [
                    'refunded_amount' => $newRefundedAmount,
                    'amount_paid' => $newAmountPaid,
                    'payment_status' => $newStatus,
                ],
                'metadata' => ['refund_id' => $refund->id, 'refund_total' => $refundTotal],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return $refund->load([
                'items.transactionDetail:id,transaction_id,product_id,product_name_snapshot,quantity',
                'processor:id,name,email',
            ]);
        });

        return response()->json([
            'message' => 'Partial refund berhasil diproses.',
            'refund' => $refund,
            'transaction' => $transaction->fresh([
                'user:id,name,email',
                'customer:id,name,phone',
                'details.product:id,name,sku',
                'payments:id,transaction_id,payment_method,amount,reference_number',
                'refunds:id,transaction_id,processed_by,refund_total,reason,created_at',
            ]),
        ], 201);
    }
}
