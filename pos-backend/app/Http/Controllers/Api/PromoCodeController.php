<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromoCodeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(PromoCode::query()->latest()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $validated['code'] = strtoupper($validated['code']);

        $promoCode = PromoCode::create($validated);

        return response()->json($promoCode, 201);
    }

    public function show(PromoCode $promoCode): JsonResponse
    {
        return response()->json($promoCode);
    }

    public function update(Request $request, PromoCode $promoCode): JsonResponse
    {
        $validated = $this->validated($request, $promoCode->id);
        $validated['code'] = strtoupper($validated['code']);

        $promoCode->update($validated);

        return response()->json($promoCode);
    }

    public function destroy(PromoCode $promoCode): JsonResponse
    {
        $promoCode->delete();

        return response()->json(['message' => 'Promo code deleted']);
    }

    /** Lets the cashier preview a code's discount before checkout without consuming it. */
    public function validateCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $promoCode = PromoCode::query()->whereRaw('upper(code) = ?', [strtoupper($validated['code'])])->first();

        if (! $promoCode) {
            return response()->json(['valid' => false, 'message' => 'Kode promo tidak ditemukan.'], 422);
        }

        $reason = $promoCode->ineligibilityReason((float) $validated['subtotal']);
        if ($reason) {
            return response()->json(['valid' => false, 'message' => $reason], 422);
        }

        return response()->json([
            'valid' => true,
            'code' => $promoCode->code,
            'discount_type' => $promoCode->discount_type,
            'discount_value' => (float) $promoCode->discount_value,
            'max_discount' => $promoCode->max_discount !== null ? (float) $promoCode->max_discount : null,
            'discount_amount' => $promoCode->calculateDiscount((float) $validated['subtotal']),
        ]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('promo_codes', 'code')->ignore($ignoreId)],
            'discount_type' => ['required', 'in:fixed,percent'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}
