<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        $shifts = Shift::query()
            ->with('user:id,name')
            ->latest()
            ->paginate(20);

        return response()->json($shifts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);

        // Check if this user already has an open shift
        $existingOpen = Shift::where('user_id', $validated['user_id'])
            ->where('status', 'open')
            ->first();

        if ($existingOpen) {
            return response()->json([
                'message' => 'Kasir ini masih memiliki shift yang sedang berjalan.',
                'shift' => $existingOpen->load('user:id,name'),
            ], 422);
        }

        $shift = Shift::create([
            'user_id' => $validated['user_id'],
            'opening_cash' => $validated['opening_cash'],
            'status' => 'open',
        ]);

        return response()->json($shift->load('user:id,name'), 201);
    }

    public function show(Shift $shift): JsonResponse
    {
        return response()->json($shift->load('user:id,name'));
    }

    public function close(Request $request, Shift $shift): JsonResponse
    {
        if ($shift->status === 'closed') {
            return response()->json(['message' => 'Shift ini sudah ditutup.'], 422);
        }

        $validated = $request->validate([
            'closing_cash_physical' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        // Calculate system cash: opening_cash + cash sales during shift
        $cashSales = Transaction::where('user_id', $shift->user_id)
            ->where('is_voided', false)
            ->where('payment_method', 'cash')
            ->where('created_at', '>=', $shift->started_at)
            ->sum('amount_paid');

        $systemCash = (float) $shift->opening_cash + (float) $cashSales;
        $physicalCash = (float) $validated['closing_cash_physical'];
        $difference = $physicalCash - $systemCash;

        $shift->update([
            'ended_at' => now(),
            'closing_cash_physical' => $physicalCash,
            'closing_cash_system' => $systemCash,
            'cash_difference' => $difference,
            'notes' => $validated['notes'] ?? null,
            'status' => 'closed',
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'shift.closed',
            'entity_type' => 'shift',
            'entity_id' => $shift->id,
            'old_values' => null,
            'new_values' => [
                'closing_cash_physical' => $physicalCash,
                'closing_cash_system' => $systemCash,
                'cash_difference' => $difference,
                'notes' => $validated['notes'] ?? null,
            ],
            'metadata' => ['shift_user_id' => $shift->user_id],
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return response()->json($shift->load('user:id,name'));
    }
}
