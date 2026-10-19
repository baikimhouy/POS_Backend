<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

class CatalogDemoSeeder extends Seeder
{
    public function run(InventoryService $inventory): void
    {
        if (app()->isProduction() || Product::query()->exists()) {
            return;
        }

        $cats = [];
        foreach (['Tops' => 'tops', 'Bottoms' => 'bottoms', 'Outerwear' => 'outerwear', 'Accessories' => 'accessories'] as $name => $slug) {
            $cats[$slug] = Category::create(['name' => $name, 'slug' => $slug]);
        }

        // ─────────────────────────────────────────────────────────────────────────
        // ADD YOUR PRODUCTS HERE. One line = one product. Copy a line and edit it.
        //
        //  [ category , name , slug (unique, a-z and -), brand , description ,
        //    cost price , selling price , [colours] , [sizes] , stock per size/colour ,
        //    image file name or null ]
        //
        // category must be one of: tops, bottoms, outerwear, accessories
        // (to add a category, add it to the $cats list above).
        // image: put the photo in backend/public/product-images/ and write its name, e.g. 'tee.jpg'
        //        (or leave null and upload it later from the Products page).
        // Then run:  php artisan migrate:fresh --seed     (this resets the database)
        // ─────────────────────────────────────────────────────────────────────────
        $catalog = [
            ['tops', 'Classic Cotton Tee', 'classic-cotton-tee', 'HORANGI', 'Soft 100% cotton crew-neck tee.', 4.5, 9.0, ['Black', 'White'], ['S', 'M', 'L'], 20, null],
            ['tops', 'Oversized Graphic Tee', 'oversized-graphic-tee', 'HORANGI', 'Boxy fit with tiger graphic on the back.', 7.0, 14.5, ['Black'], ['M', 'L', 'XL'], 12, null],
            ['bottoms', 'Cargo Pants', 'cargo-pants', 'HORANGI', 'Relaxed cargo pants with six pockets.', 14.0, 29.0, ['Black', 'Olive'], ['30', '32', '34'], 8, null],
            ['bottoms', 'Wide Denim', 'wide-denim', 'HORANGI', 'Wide-leg indigo denim.', 16.0, 32.0, ['Indigo'], ['30', '32'], 6, null],
            ['outerwear', 'Track Jacket', 'track-jacket', 'HORANGI', 'Lightweight zip track jacket.', 18.0, 38.0, ['White', 'Black'], ['M', 'L'], 6, null],
            ['outerwear', 'Zip Hoodie', 'zip-hoodie', 'HORANGI', 'Heavy fleece zip hoodie.', 17.0, 35.0, ['Black'], ['L', 'XL'], 4, null],
            ['accessories', 'Bucket Hat', 'bucket-hat', 'HORANGI', 'Cotton twill bucket hat.', 4.0, 12.0, ['Black', 'Beige'], ['OS'], 15, null],
            ['accessories', 'Chain Necklace', 'chain-necklace', 'HORANGI', 'Stainless steel chain.', 2.5, 8.0, ['Silver'], ['OS'], 25, null],
            ['accessories', 'Tote Bag', 'tote-bag', 'HORANGI', 'Heavy canvas tote.', 2.0, 7.5, ['White'], ['OS'], 3, null],
        ];

        foreach ($catalog as [$cat, $name, $slug, $brand, $desc, $cost, $price, $colors, $sizes, $stock, $image]) {
            $product = Product::create([
                'category_id' => $cats[$cat]->id, 'name' => $name, 'slug' => $slug,
                'brand' => $brand, 'description' => $desc, 'image_path' => $image,
            ]);

            foreach ($colors as $color) {
                foreach ($sizes as $size) {
                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', explode(' ', $name)[0]), 0, 3)
                            .'-'.substr(strtoupper(preg_replace('/[^A-Za-z]/', '', $name)), -3)
                            .'-'.strtoupper(substr($color, 0, 3)).'-'.$size),
                        'size' => $size, 'color' => $color,
                        'cost_price' => $cost, 'selling_price' => $price,
                    ]);

                    // Opening stock goes through the service so it leaves a movement record too.
                    $inventory->change($variant->id, $stock, StockMovementType::Adjustment, null, 'Opening stock (demo seed)');
                }
            }
        }

        Customer::create(['name' => 'Walk-in regular', 'phone' => '012 345 678']);
        Customer::create(['name' => 'Sokha Chan', 'phone' => '098 765 432', 'address' => 'Phnom Penh']);
    }
}
