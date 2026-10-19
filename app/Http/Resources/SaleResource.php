<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'currency' => $this->currency,
            'exchange_rate' => (float) $this->exchange_rate,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'sold_at' => $this->sold_at?->toIso8601String(),
            'cashier' => $this->whenLoaded('cashier', fn () => ['id' => $this->cashier->id, 'name' => $this->cashier->name]),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer
                ? ['id' => $this->customer->id, 'name' => $this->customer->name, 'phone' => $this->customer->phone]
                : null),
            'item_count' => $this->when(isset($this->items_sum_quantity), fn () => (int) $this->items_sum_quantity),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'product_name' => $i->product_name,
                'sku' => $i->sku,
                'size' => $i->size,
                'color' => $i->color,
                'quantity' => $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'line_total' => (float) $i->line_total,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'method' => $p->method,
                'currency' => $p->currency,
                'amount' => (float) $p->amount,
                'status' => $p->status->value,
            ])),
        ];
    }
}
