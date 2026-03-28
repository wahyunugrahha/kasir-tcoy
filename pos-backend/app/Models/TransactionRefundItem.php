<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionRefundItem extends Model
{
    protected $fillable = [
        'transaction_refund_id',
        'transaction_detail_id',
        'quantity',
        'unit_price_snapshot',
        'line_refund_total',
    ];

    public function refund(): BelongsTo
    {
        return $this->belongsTo(TransactionRefund::class, 'transaction_refund_id');
    }

    public function transactionDetail(): BelongsTo
    {
        return $this->belongsTo(TransactionDetail::class);
    }
}
