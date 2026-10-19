<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One sellable SKU (a product + size + colour). cost_price is admin-only. */
class VariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'category_id' => $this->product?->category_id,
            'category_name' => $this->product?->category?->name,
            'image_url' => $this->product ? ProductResource::imageUrl($this->product->image_path) : null,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'size' => $this->size,
            'color' => $this->color,
            'selling_price' => (float) $this->selling_price,
            'cost_price' => $request->user()?->isAdmin() ? (float) $this->cost_price : null,
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'is_active' => $this->is_active,
        ];
    }
}
