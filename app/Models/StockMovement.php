<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'product_variant_id', 'user_id', 'type', 'quantity_change',
        'stock_before', 'stock_after', 'reference_type', 'reference_id', 'reason',
    ];

    protected function casts(): array
    {
        return ['type' => StockMovementType::class];
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
