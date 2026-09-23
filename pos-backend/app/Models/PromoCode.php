<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $discount_type
 * @property float $discount_value
 * @property float|null $min_purchase
 * @property float|null $max_discount
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property int|null $usage_limit
 * @property int $times_used
 * @property bool $active
 */
class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'min_purchase',
        'max_discount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'times_used',
        'active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'active' => 'boolean',
    ];

    /** Returns an error message if this code can't be applied to a cart of this subtotal, null if it can. */
    public function ineligibilityReason(float $subtotal): ?string
    {
        if (! $this->active) {
            return 'Kode promo tidak aktif.';
        }

        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return 'Kode promo belum berlaku.';
        }
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return 'Kode promo sudah kedaluwarsa.';
        }
        if ($this->usage_limit !== null && $this->times_used >= $this->usage_limit) {
            return 'Kode promo sudah mencapai batas penggunaan.';
        }
        if ($this->min_purchase !== null && $subtotal < (float) $this->min_purchase) {
            return 'Belum memenuhi minimum pembelian untuk kode promo ini.';
        }

        return null;
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0;
        }

        $amount = $this->discount_type === 'percent'
            ? $subtotal * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        if ($this->discount_type === 'percent' && $this->max_discount !== null) {
            $amount = min($amount, (float) $this->max_discount);
        }

        return max(0, min($subtotal, $amount));
    }
}
