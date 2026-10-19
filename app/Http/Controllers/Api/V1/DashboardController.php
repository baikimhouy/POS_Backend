<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $today = now()->toDateString();
        $completed = fn ($q) => $q->where('status', SaleStatus::Completed->value);

        $todaySales = Sale::query()->tap($completed)->whereDate('sold_at', $today);

        // Last 7 days of revenue, filling empty days with 0.
        $byDay = Sale::query()->tap($completed)
            ->whereDate('sold_at', '>=', now()->subDays(6)->toDateString())
            ->selectRaw('DATE(sold_at) as day, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('day')->get()->keyBy('day');

        $week = collect(range(6, 0))->map(function ($ago) use ($byDay) {
            $day = now()->subDays($ago)->toDateString();

            return ['date' => $day, 'revenue' => (float) ($byDay[$day]->revenue ?? 0), 'orders' => (int) ($byDay[$day]->orders ?? 0)];
        })->values();

        $top = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', SaleStatus::Completed->value)
            ->whereDate('sales.sold_at', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('sale_items.product_name as name, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as revenue')
            ->groupBy('sale_items.product_name')
            ->orderByDesc('qty')->limit(5)->get()
            ->map(fn ($r) => ['name' => $r->name, 'quantity' => (int) $r->qty, 'revenue' => (float) $r->revenue]);

        $low = ProductVariant::query()->with('product')->active()->lowStock()
            ->orderBy('stock_quantity')->limit(8)->get()
            ->map(fn ($v) => ['id' => $v->id, 'name' => $v->product?->name, 'sku' => $v->sku, 'size' => $v->size, 'color' => $v->color, 'stock_quantity' => $v->stock_quantity]);

        return response()->json(['data' => [
            'today_sales' => (float) (clone $todaySales)->sum('total_amount'),
            'today_orders' => (clone $todaySales)->count(),
            'today_items' => (int) SaleItem::query()->whereHas('sale', fn ($q) => $q->where('status', SaleStatus::Completed->value)->whereDate('sold_at', $today))->sum('quantity'),
            'low_stock_count' => ProductVariant::query()->active()->lowStock()->count(),
            'week' => $week,
            'top_products' => $top,
            'low_stock' => $low,
        ]]);
    }
}
