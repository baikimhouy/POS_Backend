<?php

namespace App\Models;

use App\Enums\SalePaymentStatus;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'receipt_number', 'idempotency_key', 'user_id', 'customer_id', 'status', 'payment_status',
        'currency', 'exchange_rate', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount',
        'notes', 'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'payment_status' => SalePaymentStatus::class,
            'exchange_rate' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sold_at' => 'datetime',
        ];
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }
}
