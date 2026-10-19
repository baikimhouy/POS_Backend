<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    protected $table = 'sale_returns';

    protected $fillable = ['sale_id', 'user_id', 'reason', 'status', 'refund_total'];

    protected function casts(): array
    {
        return ['status' => ReturnStatus::class, 'refund_total' => 'decimal:2'];
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
