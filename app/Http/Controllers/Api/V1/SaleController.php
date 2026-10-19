<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Enums\SalePaymentStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StoreSetting;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly AuditLogger $audit,
    ) {}

    /** Sales history. Cashiers see only their own sales; admins see all. */
    public function index(Request $request)
    {
        $user = $request->user();

        $sales = Sale::query()
            ->with(['cashier:id,name', 'customer:id,name,phone'])
            ->withSum('items', 'quantity')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('date'), fn ($q, $d) => $q->whereDate('sold_at', $d))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('receipt_number', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))))
            ->latest('sold_at')->latest('id')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return SaleResource::collection($sales);
    }

    public function show(Request $request, Sale $sale): SaleResource
    {
        abort_if(! $request->user()->isAdmin() && $sale->user_id !== $request->user()->id, 403);

        return new SaleResource($sale->load(['cashier:id,name', 'customer:id,name,phone', 'items', 'payments']));
    }

    /**
     * Checkout. Prices come from the database (never from the browser), stock is locked and
     * re-checked per variant, and everything happens in one transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'currency' => ['required', Rule::in(['USD', 'KHR'])],
            'payment_method' => ['required', Rule::in(['cash', 'khqr', 'bank_transfer'])],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ]);

        // A retried request (double click, flaky network) returns the sale that already exists.
        if (! empty($data['idempotency_key'])
            && ($existing = Sale::where('idempotency_key', $data['idempotency_key'])->first())) {
            return (new SaleResource($existing->load(['cashier:id,name', 'customer:id,name,phone', 'items', 'payments'])))
                ->response()->setStatusCode(200);
        }

        $user = $request->user();
        $setting = StoreSetting::current();
        $rate = (float) $setting->khr_per_usd;

        // Merge duplicate lines for the same variant.
        $qtyByVariant = collect($data['items'])->groupBy('variant_id')->map(fn ($rows) => (int) $rows->sum('quantity'));

        $sale = DB::transaction(function () use ($data, $user, $rate, $qtyByVariant) {
            $variants = ProductVariant::query()->with('product')->whereIn('id', $qtyByVariant->keys())->get()->keyBy('id');

            $lines = [];
            $subtotal = 0.0;
            foreach ($qtyByVariant as $variantId => $qty) {
                $v = $variants[$variantId];
                if (! $v->is_active || ! $v->product->is_active) {
                    throw ValidationException::withMessages(['items' => ["{$v->sku} is not available for sale."]]);
                }
                $lineTotal = round((float) $v->selling_price * $qty, 2);
                $subtotal += $lineTotal;
                $lines[] = ['variant' => $v, 'qty' => $qty, 'line_total' => $lineTotal];
            }

            $subtotal = round($subtotal, 2);
            $discount = min(round((float) ($data['discount_amount'] ?? 0), 2), $subtotal);
            $total = round($subtotal - $discount, 2);

            $sale = Sale::create([
                'receipt_number' => 'TMP-'.Str::uuid(),
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'user_id' => $user->id,
                'customer_id' => $data['customer_id'] ?? null,
                'status' => SaleStatus::Completed,
                'payment_status' => SalePaymentStatus::Paid,
                'currency' => $data['currency'],
                'exchange_rate' => $rate,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => 0,
                'total_amount' => $total,
                'notes' => $data['notes'] ?? null,
                'sold_at' => now(),
            ]);
            $sale->update(['receipt_number' => 'R'.now()->format('ymd').'-'.str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT)]);

            foreach ($lines as $l) {
                /** @var ProductVariant $v */
                $v = $l['variant'];
                $sale->items()->create([
                    'product_variant_id' => $v->id,
                    'product_name' => $v->product->name,
                    'sku' => $v->sku,
                    'size' => $v->size,
                    'color' => $v->color,
                    'quantity' => $l['qty'],
                    'unit_price' => $v->selling_price,
                    'unit_cost' => $v->cost_price,
                    'discount_amount' => 0,
                    'line_total' => $l['line_total'],
                ]);

                // Locks the variant row, re-checks stock, writes the movement. Throws 409 when short.
                $this->inventory->change($v->id, -$l['qty'], StockMovementType::Sale, $user, null, $sale);
            }

            // Amount in the currency the customer pays with. KHR is rounded to the nearest 100.
            $paid = $data['currency'] === 'KHR' ? round($total * $rate / 100) * 100 : $total;
            $sale->payments()->create([
                'method' => $data['payment_method'],
                'currency' => $data['currency'],
                'amount' => $paid,
                'exchange_rate' => $rate,
                'amount_base_currency' => $total,
                'status' => PaymentStatus::Confirmed,
                'reference_number' => $data['reference_number'] ?? null,
                'confirmed_by' => $user->id,
                'paid_at' => now(),
            ]);

            return $sale;
        });

        $this->audit->log('sale.completed', $sale, null, ['receipt' => $sale->receipt_number, 'total' => (float) $sale->total_amount]);

        return (new SaleResource($sale->load(['cashier:id,name', 'customer:id,name,phone', 'items', 'payments'])))
            ->response()->setStatusCode(201);
    }

    /** Admin only: cancel a completed sale and put the stock back. */
    public function void(Request $request, Sale $sale): SaleResource|JsonResponse
    {
        if ($sale->status !== SaleStatus::Completed) {
            return response()->json(['message' => 'Only completed sales can be voided.', 'errors' => ['status' => ['Only completed sales can be voided.']]], 422);
        }

        $request->validate(['reason' => ['required', 'string', 'max:190']]);

        DB::transaction(function () use ($sale, $request) {
            foreach ($sale->items as $item) {
                $this->inventory->change($item->product_variant_id, $item->quantity, StockMovementType::VoidReversal, $request->user(), $request->input('reason'), $sale);
            }
            $sale->update(['status' => SaleStatus::Voided]);
            $sale->payments()->update(['status' => PaymentStatus::Refunded]);
        });

        $this->audit->log('sale.voided', $sale, null, ['reason' => $request->input('reason')]);

        return new SaleResource($sale->fresh()->load(['cashier:id,name', 'customer:id,name,phone', 'items', 'payments']));
    }
}
