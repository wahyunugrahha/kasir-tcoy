<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionDetailModifier extends Model
{
    protected $fillable = [
        'transaction_detail_id',
        'name',
        'price_delta',
        'quantity',
        'notes',
    ];

    public function transactionDetail(): BelongsTo
    {
        return $this->belongsTo(TransactionDetail::class);
    }
}
