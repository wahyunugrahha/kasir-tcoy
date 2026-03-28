<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $invoice_number
 * @property int $user_id
 * @property int|null $customer_id
 * @property float $subtotal
 * @property float $discount
 * @property string $discount_type
 * @property float $discount_rate
 * @property float $discount_amount
 * @property float $tax
 * @property float $tax_rate
 * @property bool $tax_included
 * @property float $grand_total
 * @property string|null $payment_method
 * @property string $payment_status
 * @property float $amount_paid
 * @property float $refunded_amount
 * @property bool $is_voided
 * @property \Illuminate\Support\Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property float $cash_received
 * @property float $cash_change
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Transaction extends Model
{
    protected $fillable = [
        'invoice_number',
        'user_id',
        'customer_name',
        'customer_id',
        'subtotal',
        'discount',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'tax',
        'tax_rate',
        'tax_included',
        'grand_total',
        'payment_method',
        'payment_status',
        'amount_paid',
        'refunded_amount',
        'is_voided',
        'voided_at',
        'voided_by',
        'void_reason',
        'cash_received',
        'cash_change',
    ];

    protected $casts = [
        'is_voided' => 'boolean',
        'voided_at' => 'datetime',
        'tax_included' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(TransactionRefund::class);
    }
}
