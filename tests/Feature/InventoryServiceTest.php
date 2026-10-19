<?php

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeVariant(int $stock = 0): ProductVariant
{
    $cat = Category::create(['name' => 'Tops', 'slug' => 'tops']);
    $product = Product::create(['category_id' => $cat->id, 'name' => 'Tee', 'slug' => 'tee']);

    return ProductVariant::create([
        'product_id' => $product->id, 'sku' => 'TEE-BLK-M', 'size' => 'M', 'color' => 'Black',
        'cost_price' => 4, 'selling_price' => 9, 'stock_quantity' => $stock,
    ]);
}

it('changes stock and records a movement in the same step', function () {
    $variant = makeVariant(10);

    app(InventoryService::class)->change($variant->id, -3, StockMovementType::Sale);

    expect($variant->fresh()->stock_quantity)->toBe(7);

    $movement = StockMovement::first();
    expect($movement->stock_before)->toBe(10)
        ->and($movement->stock_after)->toBe(7)
        ->and($movement->quantity_change)->toBe(-3);
});

it('refuses to oversell and leaves stock and movements untouched', function () {
    $variant = makeVariant(2);

    expect(fn () => app(InventoryService::class)->change($variant->id, -5, StockMovementType::Sale))
        ->toThrow(InsufficientStockException::class);

    expect($variant->fresh()->stock_quantity)->toBe(2)
        ->and(StockMovement::count())->toBe(0);
});

it('rejects a duplicate SKU', function () {
    makeVariant();

    expect(fn () => ProductVariant::create([
        'product_id' => Product::first()->id, 'sku' => 'TEE-BLK-M', 'size' => 'L', 'color' => 'Black',
        'selling_price' => 9,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
