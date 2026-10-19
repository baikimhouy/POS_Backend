<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only place that changes ProductVariant::stock_quantity.
 * Every change locks the row, re-checks stock and writes a stock_movements record
 * inside one transaction (it joins an outer transaction when called from checkout).
 */
class InventoryService
{
    public function change(
        int $variantId,
        int $delta,
        StockMovementType $type,
        ?User $user = null,
        ?string $reason = null,
        ?Model $reference = null,
    ): StockMovement {
        return DB::transaction(function () use ($variantId, $delta, $type, $user, $reason, $reference) {
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variantId);

            $before = $variant->stock_quantity;
            $after = $before + $delta;

            if ($after < 0) {
                throw new InsufficientStockException($variantId, $before, abs($delta));
            }

            $variant->update(['stock_quantity' => $after]);

            return StockMovement::create([
                'product_variant_id' => $variant->id,
                'user_id' => $user?->id,
                'type' => $type,
                'quantity_change' => $delta,
                'stock_before' => $before,
                'stock_after' => $after,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
                'reason' => $reason,
            ]);
        });
    }
}
