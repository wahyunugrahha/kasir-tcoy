<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_type' => ['nullable', 'in:fixed,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_included' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', 'in:cash,qris,debit,credit_card,e_wallet,bank_transfer'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array', 'min:1'],
            'payments.*.payment_method' => ['required_with:payments', 'in:cash,qris,debit,credit_card,e_wallet,bank_transfer'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'gt:0'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:100'],
            'payments.*.metadata' => ['nullable', 'array'],
            'manager_user_id' => ['nullable', 'exists:users,id'],
            'manager_pin' => ['nullable', 'string', 'min:4', 'max:20'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_type' => ['nullable', 'in:fixed,percent'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.modifiers' => ['nullable', 'array'],
            'items.*.modifiers.*.name' => ['required_with:items.*.modifiers', 'string', 'max:100'],
            'items.*.modifiers.*.price_delta' => ['nullable', 'numeric'],
            'items.*.modifiers.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.modifiers.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
