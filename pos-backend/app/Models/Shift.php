<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property string $opening_cash
 * @property string|null $closing_cash_physical
 * @property string|null $closing_cash_system
 * @property string|null $cash_difference
 * @property string|null $notes
 * @property string $status
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Shift extends Model
{
    protected $fillable = [
        'user_id',
        'started_at',
        'ended_at',
        'opening_cash',
        'closing_cash_physical',
        'closing_cash_system',
        'cash_difference',
        'notes',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'opening_cash' => 'decimal:2',
        'closing_cash_physical' => 'decimal:2',
        'closing_cash_system' => 'decimal:2',
        'cash_difference' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(ShiftCashMovement::class);
    }
}
