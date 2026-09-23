<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()->latest();

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('phone', 'like', $term));
        }

        $customers = $query->paginate((int) $request->integer('per_page', 15));

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'unique:customers,phone'],
            'points' => ['nullable', 'integer', 'min:0'],
        ]);

        $customer = Customer::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'points' => $validated['points'] ?? 0,
        ]);

        return response()->json($customer, 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'unique:customers,phone,'.$customer->id],
            'points' => ['nullable', 'integer', 'min:0'],
        ]);

        $customer->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'points' => $validated['points'] ?? $customer->points,
        ]);

        return response()->json($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }
}
