<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    protected $table = 'sale_return_items';

    protected $fillable = ['sale_return_id', 'sale_item_id', 'quantity', 'condition', 'refund_amount', 'restock'];

    protected function casts(): array
    {
        return ['refund_amount' => 'decimal:2', 'restock' => 'boolean'];
    }

    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }
}
