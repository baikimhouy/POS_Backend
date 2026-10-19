<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $fillable = [
        'store_name', 'address', 'phone', 'reporting_currency', 'khr_per_usd',
        'timezone', 'default_locale', 'receipt_footer',
    ];

    protected function casts(): array
    {
        return ['khr_per_usd' => 'decimal:4'];
    }

    /** The store has exactly one settings row. */
    public static function current(): self
    {
        return static::query()->firstOrFail();
    }
}
