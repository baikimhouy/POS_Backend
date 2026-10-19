<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'sale_id', 'method', 'currency', 'amount', 'exchange_rate', 'amount_base_currency',
        'status', 'reference_number', 'confirmed_by', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'amount_base_currency' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
