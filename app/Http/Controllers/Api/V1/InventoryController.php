<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Resources\VariantResource;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    /** Add (+) or remove (-) stock with a reason. Always leaves a stock_movements record. */
    public function adjust(Request $request, ProductVariant $variant): VariantResource
    {
        $data = $request->validate([
            'quantity_change' => ['required', 'integer', 'not_in:0', 'between:-100000,100000'],
            'reason' => ['required', 'string', 'max:190'],
        ]);

        $this->inventory->change($variant->id, (int) $data['quantity_change'], StockMovementType::Adjustment, $request->user(), $data['reason']);

        return new VariantResource($variant->fresh()->load('product.category'));
    }

    public function movements(Request $request, ProductVariant $variant): JsonResponse
    {
        $rows = StockMovement::query()
            ->where('product_variant_id', $variant->id)
            ->latest('id')->limit(50)->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type instanceof \BackedEnum ? $m->type->value : $m->type,
                'quantity_change' => $m->quantity_change,
                'stock_before' => $m->stock_before,
                'stock_after' => $m->stock_after,
                'reason' => $m->reason,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }
}
