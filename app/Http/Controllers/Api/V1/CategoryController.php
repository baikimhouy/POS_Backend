<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Category::query()->withCount('products')->orderBy('name')->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'is_active' => $c->is_active, 'products_count' => $c->products_count]);

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:categories,name']]);

        $slug = Str::slug($data['name']) ?: 'category';
        if (Category::where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(5));
        }

        $c = Category::create(['name' => $data['name'], 'slug' => $slug]);

        return response()->json(['data' => ['id' => $c->id, 'name' => $c->name, 'is_active' => true, 'products_count' => 0]], 201);
    }
}
