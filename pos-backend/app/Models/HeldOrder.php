<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $label
 * @property array $items
 * @property float $subtotal
 * @property \Illuminate\Support\Carbon $created_at
 */
class HeldOrder extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'items',
        'subtotal',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
