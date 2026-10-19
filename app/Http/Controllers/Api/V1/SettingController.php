<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payload(StoreSetting::current())]);
    }

    /** Admin only: shop name, contact and the KHR-per-USD rate used by new sales. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_name' => ['sometimes', 'string', 'max:120'],
            'address' => ['sometimes', 'nullable', 'string', 'max:190'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'khr_per_usd' => ['sometimes', 'numeric', 'min:1', 'max:100000'],
            'receipt_footer' => ['sometimes', 'nullable', 'string', 'max:190'],
            'default_locale' => ['sometimes', Rule::in(['en', 'km'])],
        ]);

        $setting = StoreSetting::current();
        $setting->update($data);

        return response()->json(['data' => $this->payload($setting)]);
    }

    private function payload(StoreSetting $s): array
    {
        return [
            'store_name' => $s->store_name,
            'address' => $s->address,
            'phone' => $s->phone,
            'khr_per_usd' => (float) $s->khr_per_usd,
            'receipt_footer' => $s->receipt_footer,
        ];
    }
}
