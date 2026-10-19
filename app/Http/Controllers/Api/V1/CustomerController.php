<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = Customer::query()
            ->withCount('sales')
            ->withSum(['sales as total_spent' => fn ($q) => $q->where('status', 'completed')], 'total_amount')
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%")))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 20), 100));

        return response()->json([
            'data' => collect($page->items())->map(fn ($c) => $this->row($c)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json(['data' => $this->row(Customer::create($data))], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'address' => ['sometimes', 'nullable', 'string', 'max:190'],
        ]);
        $customer->update($data);

        return response()->json(['data' => $this->row($customer)]);
    }

    private function row(Customer $c): array
    {
        return [
            'id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'email' => $c->email, 'address' => $c->address,
            'sales_count' => (int) ($c->sales_count ?? 0),
            'total_spent' => (float) ($c->total_spent ?? 0),
        ];
    }
}
