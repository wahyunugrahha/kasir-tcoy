<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $category_id
 * @property string $sku
 * @property string $name
 * @property string $cost_price
 * @property string $selling_price
 * @property int $stock
 * @property int $min_stock
 * @property string|null $image_url
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'cost_price',
        'selling_price',
        'stock',
        'min_stock',
        'image_url',
        'description',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactionDetails(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
