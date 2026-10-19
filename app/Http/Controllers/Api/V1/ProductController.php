<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\VariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InventoryService $inventory,
    ) {}

    /** Product list (one row per product, with its variants). */
    private function makeSku(Product $product): string
{
    do {
        $sku = 'P'.$product->id.'-'.strtoupper(Str::random(6));
    } while (ProductVariant::where('sku', $sku)->exists());

    return $sku;
}
    public function index(Request $request)
    {
        $isAdmin = $request->user()->isAdmin();

        $products = Product::query()
            ->with(['category', 'variants'])
            ->when(! $isAdmin, fn ($q) => $q->where('is_active', true))
            ->when($request->query('category_id'), fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('brand', 'like', "%{$s}%")
                ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$s}%")->orWhere('barcode', 'like', "%{$s}%"))))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 24), 100));

        return ProductResource::collection($products);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        abort_if(! $product->is_active && ! $request->user()->isAdmin(), 404);

        return new ProductResource($product->load(['category', 'variants']));
    }

    /** Flat list of sellable variants. The POS screen and the inventory page use this. */
    public function variants(Request $request)
    {
        $isAdmin = $request->user()->isAdmin();

        $variants = ProductVariant::query()
            ->with('product.category')
            ->whereHas('product', fn ($q) => $q->when(! $isAdmin, fn ($q) => $q->where('is_active', true)))
            ->when(! $isAdmin || $request->boolean('sellable'), fn ($q) => $q->where('is_active', true))
            ->when($request->query('category_id'), fn ($q, $c) => $q->whereHas('product', fn ($p) => $p->where('category_id', $c)))
            ->when($request->boolean('low_stock'), fn ($q) => $q->lowStock())
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('sku', 'like', "%{$s}%")
                ->orWhere('barcode', 'like', "%{$s}%")
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$s}%"))))
            ->orderBy('product_id')->orderBy('color')->orderBy('size')
            ->paginate(min((int) $request->query('per_page', 100), 500));

        return VariantResource::collection($variants);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...$this->productRules(),
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.sku' => ['nullable', 'string', 'max:60', 'distinct', 'unique:product_variants,sku'],            'variants.*.barcode' => ['nullable', 'string', 'max:60', 'distinct', 'unique:product_variants,barcode'],
            'variants.*.size' => ['required', 'string', 'max:20'],
            'variants.*.color' => ['required', 'string', 'max:40'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.selling_price' => ['required', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'slug' => $this->slugFor($data['name']),
                'description' => $data['description'] ?? null,
                'brand' => $data['brand'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($data['variants'] as $v) {
                $variant = $product->variants()->create([
                    
                    'sku' => $v['sku'] ?? $this->makeSku($product),
                    'barcode' => $v['barcode'] ?? null,
                    'size' => $v['size'],
                    'color' => $v['color'],
                    'cost_price' => $v['cost_price'] ?? 0,
                    'selling_price' => $v['selling_price'],
                    'low_stock_threshold' => $v['low_stock_threshold'] ?? 5,
                ]);

                if (($v['stock_quantity'] ?? 0) > 0) {
                    $this->inventory->change($variant->id, (int) $v['stock_quantity'], StockMovementType::Adjustment, $request->user(), 'Opening stock');
                }
            }

            return $product;
        });

        $this->audit->log('product.created', $product, null, ['name' => $product->name]);

        return (new ProductResource($product->load(['category', 'variants'])))->response()->setStatusCode(201);
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $data = $request->validate($this->productRules(partial: true));
        $before = $product->only(array_keys($data));
        $product->update($data);

        $this->audit->log('product.updated', $product, $before, $data);

        return new ProductResource($product->load(['category', 'variants']));
    }

    public function storeVariant(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['nullable', 'string', 'max:60', 'unique:product_variants,sku'],
            'barcode' => ['nullable', 'string', 'max:60', 'unique:product_variants,barcode'],
            'size' => ['required', 'string', 'max:20'],
            'color' => ['required', 'string', 'max:40'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        if ($product->variants()->where('size', $data['size'])->where('color', $data['color'])->exists()) {
            return response()->json([
                'message' => 'That size and colour already exists.',
                'errors' => ['size' => ['That size and colour already exists for this product.']],
            ], 422);
        }

        $variant = DB::transaction(function () use ($product, $data, $request) {
            $variant = $product->variants()->create([
                'sku' => $data['sku'] ?? $this->makeSku($product),
                ...collect($data)->except('stock_quantity','sku')->all(),
                'cost_price' => $data['cost_price'] ?? 0,
                'low_stock_threshold' => $data['low_stock_threshold'] ?? 5,
            ]);
            if (($data['stock_quantity'] ?? 0) > 0) {
                $this->inventory->change($variant->id, (int) $data['stock_quantity'], StockMovementType::Adjustment, $request->user(), 'Opening stock');
            }

            return $variant;
        });

        return (new VariantResource($variant->fresh()->load('product.category')))->response()->setStatusCode(201);
    }

    public function updateVariant(Request $request, ProductVariant $variant): VariantResource
    {
        $data = $request->validate([
            'sku' => ['sometimes', 'string', 'max:60', 'unique:product_variants,sku,'.$variant->id],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:60', 'unique:product_variants,barcode,'.$variant->id],
            'cost_price' => ['sometimes', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $before = $variant->only(array_keys($data));
        $variant->update($data);
        $this->audit->log('variant.updated', $variant, $before, $data);

        return new VariantResource($variant->load('product.category'));
    }

    /** Upload (or replace) the product photo. Files are saved in public/product-images. */
    public function uploadImage(Request $request, Product $product): ProductResource
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);

        $file = $request->file('image');
        $name = Str::lower(Str::random(24)).'.'.$file->extension();
        $file->move(public_path('product-images'), $name);

        $this->deleteImageFile($product->image_path);
        $product->update(['image_path' => $name]);

        return new ProductResource($product->load(['category', 'variants']));
    }

    public function deleteImage(Product $product): ProductResource
    {
        $this->deleteImageFile($product->image_path);
        $product->update(['image_path' => null]);

        return new ProductResource($product->load(['category', 'variants']));
    }

    private function deleteImageFile(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            $file = public_path('product-images/'.basename($path));
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function productRules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'max:190'],
            'category_id' => [$req, 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'brand' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function slugFor(string $name): string
    {
        $slug = Str::slug($name) ?: 'product';

        return Product::where('slug', $slug)->exists() ? $slug.'-'.Str::lower(Str::random(5)) : $slug;
    }
}
