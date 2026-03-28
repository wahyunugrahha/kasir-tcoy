<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    /** Cached once per process — schema does not change at runtime. */
    private static ?bool $productVariantsTableExists = null;

    public function create(array $validated, bool $requireOpenShift = false, string $movementNotes = 'Automatic deduction from sales transaction'): Transaction
    {
        if ($requireOpenShift && ! $this->hasOpenShift((int) $validated['user_id'])) {
            throw ValidationException::withMessages([
                'shift' => 'Checkout ditolak. Kasir wajib membuka shift terlebih dahulu.',
            ]);
        }

        return DB::transaction(function () use ($validated, $movementNotes) {
            return $this->createInsideTransaction($validated, $movementNotes);
        });
    }

    private function createInsideTransaction(array $validated, string $movementNotes): Transaction
    {
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
            $variantIds = $items->pluck('variant_id')->filter()->unique()->values();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $hasProductVariantsTable = self::$productVariantsTableExists
                ?? (self::$productVariantsTableExists = Schema::hasTable('product_variants'));

            if (! $hasProductVariantsTable && $variantIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Fitur variant belum tersedia karena tabel product_variants belum dibuat.',
                ]);
            }

            $variants = collect();
            if ($hasProductVariantsTable && $variantIds->isNotEmpty()) {
                $variants = ProductVariant::query()
                    ->whereIn('id', $variantIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
            }

            $summaryTaxPercent = isset($validated['tax_percent'])
                ? $this->clampPercentage((float) $validated['tax_percent'])
                : null;
            $lineTaxRate = $summaryTaxPercent !== null
                ? 0
                : $this->clampPercentage((float) ($validated['tax_rate'] ?? 0));

            $subtotal = 0;
            $lineDiscountTotal = 0;
            $taxTotal = 0;
            $preCartTotal = 0;
            $detailPayload = [];

            foreach ($items as $item) {
                /** @var Product|null $product */
                $product = $products->get($item['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more products are invalid.',
                    ]);
                }

                $qty = (int) $item['quantity'];

                $variant = null;
                if (! empty($item['variant_id'])) {
                    $variant = $variants->get((int) $item['variant_id']);

                    if (! $variant || (int) $variant->product_id !== (int) $product->id) {
                        throw ValidationException::withMessages([
                            'items' => "Variant tidak valid untuk produk {$product->name}.",
                        ]);
                    }
                }

                $effectiveStock = (int) $product->stock + (int) ($variant?->stock_delta ?? 0);

                if ($effectiveStock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for product {$product->name}.",
                    ]);
                }

                $baseUnitPrice = (float) ($variant?->selling_price ?? $product->selling_price);
                $baseUnitCost = (float) ($variant?->cost_price ?? $product->cost_price);

                $modifiers = collect($item['modifiers'] ?? []);
                $modifierDeltaPerUnit = 0.0;
                foreach ($modifiers as $modifier) {
                    $delta = (float) ($modifier['price_delta'] ?? 0);
                    $modifierQty = max(1, (int) ($modifier['quantity'] ?? 1));
                    $modifierDeltaPerUnit += $delta * $modifierQty;
                }

                $unitPrice = $baseUnitPrice + $modifierDeltaPerUnit;
                $lineGross = $unitPrice * $qty;

                $lineDiscountType = $item['discount_type'] ?? null;
                $lineDiscountValue = (float) ($item['discount_value'] ?? 0);
                $lineDiscountAmount = $this->resolveDiscountAmount($lineDiscountType, $lineDiscountValue, $lineGross);

                $taxRate = $lineTaxRate;
                $taxIncluded = (bool) ($validated['tax_included'] ?? false);

                $lineAfterDiscount = max(0, $lineGross - $lineDiscountAmount);
                $lineNetSubtotal = $lineAfterDiscount;
                $lineTaxAmount = 0;

                if ($taxRate > 0) {
                    if ($taxIncluded) {
                        $lineNetSubtotal = $lineAfterDiscount / (1 + ($taxRate / 100));
                        $lineTaxAmount = $lineAfterDiscount - $lineNetSubtotal;
                    } else {
                        $lineTaxAmount = $lineAfterDiscount * ($taxRate / 100);
                        $lineNetSubtotal = $lineAfterDiscount;
                    }
                }

                $lineSubtotal = $taxIncluded
                    ? $lineAfterDiscount
                    : $lineNetSubtotal + $lineTaxAmount;

                $subtotal += $lineGross;
                $lineDiscountTotal += $lineDiscountAmount;
                $taxTotal += $lineTaxAmount;
                $preCartTotal += $lineSubtotal;

                $detailPayload[] = [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name_snapshot' => $product->name,
                    'variant_name_snapshot' => $variant?->name,
                    'price_snapshot' => $unitPrice,
                    'cogs_snapshot' => $baseUnitCost,
                    'line_discount_type' => $lineDiscountType,
                    'line_discount_rate' => $lineDiscountType === 'percent' ? $lineDiscountValue : 0,
                    'line_discount_amount' => $lineDiscountAmount,
                    'line_tax_amount' => $lineTaxAmount,
                    'net_subtotal' => $lineNetSubtotal,
                    'quantity' => $qty,
                    'subtotal' => $lineSubtotal,
                    'modifiers' => $modifiers,
                ];
            }

            $discountType = $validated['discount_type'] ?? 'fixed';
            $discountValue = (float) ($validated['discount_value'] ?? $validated['discount'] ?? 0);

            if (isset($validated['discount_percent'])) {
                $discountType = 'percent';
                $discountValue = $this->clampPercentage((float) $validated['discount_percent']);
            } elseif ($discountType === 'percent') {
                $discountValue = $this->clampPercentage($discountValue);
            }

            $cartDiscountAmount = $this->resolveDiscountAmount($discountType, $discountValue, $preCartTotal);

            $manualTax = (float) ($validated['tax'] ?? 0);
            $taxRate = $lineTaxRate;
            $taxIncluded = (bool) ($validated['tax_included'] ?? false);

            $taxAmount = $taxTotal;
            $grandTotal = max(0, $preCartTotal - $cartDiscountAmount);

            if ($summaryTaxPercent !== null) {
                $taxRate = $summaryTaxPercent;
                $taxIncluded = false;
                $taxAmount = max(0, $grandTotal) * ($taxRate / 100);
                $grandTotal += $taxAmount;
            } elseif ($taxRate <= 0 && ! $taxIncluded && $manualTax > 0) {
                $taxAmount += $manualTax;
                $grandTotal += $manualTax;
            }

            $this->guardManualDiscountApproval(
                userId: (int) $validated['user_id'],
                totalDiscountAmount: $lineDiscountTotal + $cartDiscountAmount,
                managerId: isset($validated['manager_user_id']) ? (int) $validated['manager_user_id'] : null,
                managerPin: $validated['manager_pin'] ?? null,
            );

            $payments = $this->normalizePayments($validated, $grandTotal);
            $paymentMethodSummary = $payments->pluck('payment_method')->unique()->values();
            $paymentMethod = $paymentMethodSummary->count() === 1 ? $paymentMethodSummary->first() : 'mixed';

            $cashReceived = (float) $payments
                ->where('payment_method', 'cash')
                ->sum('amount');

            $rawPaid = (float) $payments->sum('amount');
            $amountPaid = min($rawPaid, $grandTotal);
            $cashChange = max(0, $cashReceived - $grandTotal);
            $paymentStatus = $amountPaid <= 0 ? 'unpaid' : ($amountPaid < $grandTotal ? 'partial' : 'paid');

            $transaction = Transaction::create([
                'invoice_number' => $invoiceNumber,
                'user_id' => $validated['user_id'],
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $lineDiscountTotal + $cartDiscountAmount,
                'discount_type' => $discountType,
                'discount_rate' => $discountType === 'percent' ? $discountValue : 0,
                'discount_amount' => $lineDiscountTotal + $cartDiscountAmount,
                'tax' => $taxAmount,
                'tax_rate' => $taxRate,
                'tax_included' => $taxIncluded,
                'grand_total' => $grandTotal,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'amount_paid' => $amountPaid,
                'cash_received' => $cashReceived,
                'cash_change' => $cashChange,
            ]);

            foreach ($detailPayload as $detail) {
                $modifiers = $detail['modifiers'];
                unset($detail['modifiers']);

                $transactionDetail = $transaction->details()->create($detail);

                foreach ($modifiers as $modifier) {
                    $transactionDetail->modifiers()->create([
                        'name' => (string) ($modifier['name'] ?? 'Modifier'),
                        'price_delta' => (float) ($modifier['price_delta'] ?? 0),
                        'quantity' => max(1, (int) ($modifier['quantity'] ?? 1)),
                        'notes' => $modifier['notes'] ?? null,
                    ]);
                }

                $product = $products->get($detail['product_id']);
                $product->decrement('stock', $detail['quantity']);

                InventoryMovement::query()->create([
                    'product_id' => $product->id,
                    'user_id' => $validated['user_id'],
                    'type' => 'out',
                    'quantity' => $detail['quantity'],
                    'reference_type' => 'transaction',
                    'reference_id' => $transaction->id,
                    'notes' => $movementNotes,
                ]);
            }

            foreach ($payments as $payment) {
                $transaction->payments()->create([
                    'payment_method' => $payment['payment_method'],
                    'amount' => $payment['amount'],
                    'reference_number' => $payment['reference_number'] ?? null,
                    'metadata' => $payment['metadata'] ?? null,
                ]);
            }

            return $transaction;
    }

    private function resolveDiscountAmount(?string $discountType, float $discountValue, float $baseAmount): float
    {
        if ($discountValue <= 0 || $baseAmount <= 0) {
            return 0;
        }

        if ($discountType === 'percent') {
            return min($baseAmount, $baseAmount * ($discountValue / 100));
        }

        return min($baseAmount, $discountValue);
    }

    private function clampPercentage(float $value): float
    {
        return max(0, min(100, $value));
    }

    private function normalizePayments(array $validated, float $grandTotal): \Illuminate\Support\Collection
    {
        $payments = collect($validated['payments'] ?? []);

        if ($payments->isEmpty()) {
            $method = $validated['payment_method'] ?? 'cash';
            $amount = $method === 'cash'
                ? (float) ($validated['cash_received'] ?? 0)
                : $grandTotal;

            $payments = collect([[
                'payment_method' => $method,
                'amount' => $amount,
                'reference_number' => null,
                'metadata' => null,
            ]]);
        }

        if ($payments->isEmpty()) {
            throw ValidationException::withMessages([
                'payments' => 'Minimal satu pembayaran wajib diisi.',
            ]);
        }

        $normalized = [];
        foreach ($payments as $payment) {
            $method = $payment['payment_method'] ?? null;
            $amount = (float) ($payment['amount'] ?? 0);

            if (! in_array($method, ['cash', 'qris', 'debit', 'credit_card', 'e_wallet', 'bank_transfer'], true)) {
                throw ValidationException::withMessages([
                    'payments' => 'Metode pembayaran tidak valid.',
                ]);
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'payments' => 'Nilai pembayaran harus lebih besar dari 0.',
                ]);
            }

            $normalized[] = [
                'payment_method' => $method,
                'amount' => $amount,
                'reference_number' => $payment['reference_number'] ?? null,
                'metadata' => $payment['metadata'] ?? null,
            ];
        }

        return collect($normalized);
    }

    private function guardManualDiscountApproval(int $userId, float $totalDiscountAmount, ?int $managerId, ?string $managerPin): void
    {
        if ($totalDiscountAmount <= 0) {
            return;
        }

        /** @var User|null $actor */
        $actor = User::query()->find($userId);
        if (! $actor || $actor->role === 'admin') {
            return;
        }

        if (! $managerId || ! $managerPin) {
            throw ValidationException::withMessages([
                'manager_pin' => 'Diskon manual oleh kasir memerlukan persetujuan manager PIN.',
            ]);
        }

        /** @var User|null $manager */
        $manager = User::query()->find($managerId);
        if (! $manager || $manager->role !== 'admin' || empty($manager->manager_pin)) {
            throw ValidationException::withMessages([
                'manager_pin' => 'Akun manager tidak valid untuk approval.',
            ]);
        }

        if (! Hash::check($managerPin, $manager->manager_pin)) {
            throw ValidationException::withMessages([
                'manager_pin' => 'PIN manager tidak valid.',
            ]);
        }
    }

    private function hasOpenShift(int $userId): bool
    {
        return Shift::query()
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->exists();
    }
}
