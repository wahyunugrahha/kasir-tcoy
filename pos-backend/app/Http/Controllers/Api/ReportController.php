<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * GET /api/v1/reports/sales-by-date
     * Query params: month (YYYY-MM), start_date (YYYY-MM-DD), end_date (YYYY-MM-DD)
     */
    public function salesByDate(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $query = Transaction::query()->where('is_voided', false);

        if ($request->filled('month')) {
            [$year, $month] = array_map('intval', explode('-', (string) $request->string('month')));
            $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
        } elseif ($request->filled('start_date') || $request->filled('end_date')) {
            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
        } else {
            // Default: current month
            $query->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month);
        }

        $rows = $query
            ->selectRaw("DATE(created_at) as date, SUM(grand_total) as total_sales, COUNT(*) as transaction_count")
            ->groupByRaw("DATE(created_at)")
            ->orderBy('date')
            ->get();

        return response()->json($rows);
    }

    /**
     * GET /api/v1/reports/top-products
     * Query params: limit (int, default 10), start_date, end_date
     */
    public function topProducts(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $limit = $request->integer('limit', 10);

        $query = TransactionDetail::query()
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.is_voided', false);

        if ($request->filled('start_date')) {
            $query->whereDate('transactions.created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transactions.created_at', '<=', $request->end_date);
        }

        $rows = $query
            ->selectRaw(
                'transaction_details.product_id, '.
                'transaction_details.product_name_snapshot as product_name, '.
                'SUM(transaction_details.quantity) as total_quantity, '.
                'SUM(transaction_details.subtotal) as total_revenue'
            )
            ->groupBy('transaction_details.product_id', 'transaction_details.product_name_snapshot')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        return response()->json($rows);
    }

    /**
     * GET /api/v1/reports/summary
     * Returns today's quick summary: total transactions, total revenue, items sold
     */
    public function summary(): JsonResponse
    {
        $today = now()->toDateString();

        $dailyRevenue = Transaction::where('is_voided', false)->whereDate('created_at', $today)->sum('grand_total');
        $dailyCount = Transaction::where('is_voided', false)->whereDate('created_at', $today)->count();
        $dailyItems = TransactionDetail::whereHas('transaction', function ($q) use ($today) {
            $q->where('is_voided', false);
            $q->whereDate('created_at', $today);
        })->sum('quantity');

        $dailyCogs = TransactionDetail::whereHas('transaction', function ($q) use ($today) {
            $q->where('is_voided', false);
            $q->whereDate('created_at', $today);
        })->selectRaw('COALESCE(SUM(cogs_snapshot * quantity), 0) as total_cogs')->value('total_cogs');

        $monthRevenue = Transaction::where('is_voided', false)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('grand_total');
        $monthCogs = TransactionDetail::whereHas('transaction', function ($q) {
            $q->where('is_voided', false);
            $q->whereYear('created_at', now()->year);
            $q->whereMonth('created_at', now()->month);
        })->selectRaw('COALESCE(SUM(cogs_snapshot * quantity), 0) as total_cogs')->value('total_cogs');

        return response()->json([
            'today' => [
                'revenue' => (float) $dailyRevenue,
                'transactions' => (int) $dailyCount,
                'items_sold' => (int) $dailyItems,
                'gross_profit' => (float) $dailyRevenue - (float) $dailyCogs,
            ],
            'month' => [
                'revenue' => (float) $monthRevenue,
                'gross_profit' => (float) $monthRevenue - (float) $monthCogs,
            ],
        ]);
    }
}
