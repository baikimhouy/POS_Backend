<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Product photos are added by hand: copy the file into backend/public/product-images/
     * and put just the file name (e.g. "tee.jpg") in the product's image_path.
     */
    public static function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('product-images/'.ltrim($path, '/'));
    }

    public function toArray(Request $request): array
    {
        $variants = $this->relationLoaded('variants') ? $this->variants : collect();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'brand' => $this->brand,
            'category_id' => $this->category_id,
            'category_name' => $this->category?->name,
            'image_path' => $this->image_path,
            'image_url' => self::imageUrl($this->image_path),
            'is_active' => $this->is_active,
            'total_stock' => $variants->sum('stock_quantity'),
            'min_price' => $variants->isNotEmpty() ? (float) $variants->min('selling_price') : null,
            'max_price' => $variants->isNotEmpty() ? (float) $variants->max('selling_price') : null,
            'variants' => $this->relationLoaded('variants')
                ? VariantResource::collection($this->variants->each->setRelation('product', $this->resource))
                : [],
        ];
    }
}
