<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\StoreSettingSeeder;

beforeEach(function () {
    $this->seed(StoreSettingSeeder::class);
    $cat = Category::create(['name' => 'Tops', 'slug' => 'tops']);
    $p = Product::create(['category_id' => $cat->id, 'name' => 'Tee', 'slug' => 'tee']);
    $this->variant = ProductVariant::create([
        'product_id' => $p->id, 'sku' => 'TEE-1', 'size' => 'M', 'color' => 'Black',
        'cost_price' => 4, 'selling_price' => 9, 'stock_quantity' => 5,
    ]);
});

it('completes a sale, takes prices from the database and reduces stock', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);

    $res = $this->actingAs($cashier)->postJson('/api/v1/sales', [
        'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]],
        'currency' => 'USD', 'payment_method' => 'cash',
    ])->assertCreated();

    expect($res->json('data.total_amount'))->toBe(18.0);
    expect($this->variant->fresh()->stock_quantity)->toBe(3);
});

it('rejects a sale larger than the stock', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);

    $this->actingAs($cashier)->postJson('/api/v1/sales', [
        'items' => [['variant_id' => $this->variant->id, 'quantity' => 6]],
        'currency' => 'USD', 'payment_method' => 'cash',
    ])->assertStatus(409);

    expect($this->variant->fresh()->stock_quantity)->toBe(5);
});

it('voiding a sale restores stock and is admin only', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);
    $admin = User::factory()->create(['role' => 'admin']);

    $id = $this->actingAs($cashier)->postJson('/api/v1/sales', [
        'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]],
        'currency' => 'KHR', 'payment_method' => 'khqr',
    ])->json('data.id');

    $this->actingAs($cashier)->postJson("/api/v1/sales/{$id}/void", ['reason' => 'x'])->assertForbidden();
    $this->actingAs($admin)->postJson("/api/v1/sales/{$id}/void", ['reason' => 'Wrong item'])->assertOk();

    expect($this->variant->fresh()->stock_quantity)->toBe(5);
});

it('lets only admins create products', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);
    $this->actingAs($cashier)->postJson('/api/v1/products', [])->assertForbidden();
});
