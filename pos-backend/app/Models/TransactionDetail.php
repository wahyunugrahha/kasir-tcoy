<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $transaction_id
 * @property int $product_id
 * @property int|null $variant_id
 * @property string $product_name_snapshot
 * @property string|null $variant_name_snapshot
 * @property float $price_snapshot
 * @property float|null $cogs_snapshot
 * @property string|null $line_discount_type
 * @property float $line_discount_rate
 * @property float $line_discount_amount
 * @property float $line_tax_amount
 * @property float $net_subtotal
 * @property int $quantity
 * @property float $subtotal
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TransactionDetail extends Model
{
    protected $fillable = [
        'transaction_id',
        'product_id',
        'variant_id',
        'product_name_snapshot',
        'variant_name_snapshot',
        'price_snapshot',
        'cogs_snapshot',
        'line_discount_type',
        'line_discount_rate',
        'line_discount_amount',
        'line_tax_amount',
        'net_subtotal',
        'quantity',
        'subtotal',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function modifiers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TransactionDetailModifier::class);
    }

    public function refundItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TransactionRefundItem::class);
    }
}
