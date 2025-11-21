<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DailyReportController extends Controller
{
    /**
     * Get all daily reports dengan filter
     */
    public function index(Request $request)
    {
        $query = DailyReport::query();

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('report_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('report_date', '<=', $request->end_date);
        }

        // Filter by month
        if ($request->has('month') && $request->has('year')) {
            $query->whereYear('report_date', $request->year)
                  ->whereMonth('report_date', $request->month);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'report_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 31); // 1 bulan
        $reports = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $reports
        ], 200);
    }

    /**
     * Get daily report by specific date
     */
    public function show($date)
    {
        try {
            $reportDate = Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Format tanggal tidak valid. Gunakan format: YYYY-MM-DD'
            ], 400);
        }

        $report = DailyReport::where('report_date', $reportDate)->first();

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan untuk tanggal ' . $reportDate . ' belum tersedia'
            ], 404);
        }

        // Get transactions for this date
        $transactions = Transaction::with(['user:id,name', 'items.product:id,name'])
            ->whereDate('transaction_date', $reportDate)
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'report' => $report,
                'transactions' => $transactions
            ]
        ], 200);
    }

    /**
     * Generate daily report (manual trigger atau auto via scheduler)
     */
    public function generate(Request $request)
    {
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));

        try {
            $reportDate = Carbon::parse($date);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Format tanggal tidak valid'
            ], 400);
        }

        $report = $this->generateDailyReport($reportDate);

        return response()->json([
            'success' => true,
            'message' => 'Laporan harian berhasil di-generate',
            'data' => $report
        ], 200);
    }

    /**
     * Generate daily report logic
     */
    private function generateDailyReport($date)
    {
        $reportDate = Carbon::parse($date)->format('Y-m-d');

        // Get all transactions for the date
        $transactions = Transaction::whereDate('transaction_date', $reportDate)->get();

        // Calculate totals
        $totalSales = $transactions->sum('total_amount');
        $totalProfit = $transactions->sum('total_profit');
        $cashAmount = $transactions->where('payment_method', 'cash')->sum('total_amount');
        $transferAmount = $transactions->where('payment_method', 'transfer')->sum('total_amount');
        $transactionCount = $transactions->count();

        // Get top products
        $topProducts = $this->getTopProducts($reportDate);

        // Create or update report
        $report = DailyReport::updateOrCreate(
            ['report_date' => $reportDate],
            [
                'total_sales' => $totalSales,
                'total_profit' => $totalProfit,
                'cash_amount' => $cashAmount,
                'transfer_amount' => $transferAmount,
                'transaction_count' => $transactionCount,
                'top_products' => $topProducts,
            ]
        );

        return $report;
    }

    /**
     * Get top 5 selling products for a date
     */
    private function getTopProducts($date)
    {
        $topProducts = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->whereDate('transactions.transaction_date', $date)
            ->select(
                'products.id',
                'products.name',
                'products.code',
                'products.unit',
                DB::raw('SUM(transaction_items.quantity) as total_quantity'),
                DB::raw('SUM(transaction_items.subtotal) as total_sales'),
                DB::raw('SUM(transaction_items.profit) as total_profit')
            )
            ->groupBy('products.id', 'products.name', 'products.code', 'products.unit')
            ->orderBy('total_sales', 'desc')
            ->limit(5)
            ->get();

        return $topProducts;
    }

    /**
     * Get monthly summary
     */
    public function monthlySummary(Request $request)
    {
        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);

        $reports = DailyReport::whereYear('report_date', $year)
            ->whereMonth('report_date', $month)
            ->get();

        $summary = [
            'month' => $month,
            'year' => $year,
            'total_days' => $reports->count(),
            'total_sales' => $reports->sum('total_sales'),
            'total_profit' => $reports->sum('total_profit'),
            'total_transactions' => $reports->sum('transaction_count'),
            'average_daily_sales' => $reports->count() > 0 ? $reports->avg('total_sales') : 0,
            'average_daily_profit' => $reports->count() > 0 ? $reports->avg('total_profit') : 0,
            'cash_total' => $reports->sum('cash_amount'),
            'transfer_total' => $reports->sum('transfer_amount'),
            'best_day' => $reports->sortByDesc('total_sales')->first(),
            'worst_day' => $reports->where('total_sales', '>', 0)->sortBy('total_sales')->first(),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ], 200);
    }

    /**
     * Get date range summary (custom period)
     */
    public function rangeSummary(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::today()->subDays(7)->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::today()->format('Y-m-d'));

        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Format tanggal tidak valid'
            ], 400);
        }

        if ($start->gt($end)) {
            return response()->json([
                'success' => false,
                'message' => 'Start date tidak boleh lebih besar dari end date'
            ], 400);
        }

        $reports = DailyReport::whereBetween('report_date', [$startDate, $endDate])
            ->orderBy('report_date', 'asc')
            ->get();

        $summary = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $reports->count(),
            'total_sales' => $reports->sum('total_sales'),
            'total_profit' => $reports->sum('total_profit'),
            'total_transactions' => $reports->sum('transaction_count'),
            'average_daily_sales' => $reports->count() > 0 ? $reports->avg('total_sales') : 0,
            'average_daily_profit' => $reports->count() > 0 ? $reports->avg('total_profit') : 0,
            'cash_total' => $reports->sum('cash_amount'),
            'transfer_total' => $reports->sum('transfer_amount'),
            'daily_breakdown' => $reports,
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ], 200);
    }

    /**
     * Get today's report (realtime)
     */
    public function today(Request $request)
    {
        $today = Carbon::today()->format('Y-m-d');
        
        // Generate fresh report untuk hari ini
        $report = $this->generateDailyReport($today);

        // Get today's transactions
        $transactions = Transaction::with(['user:id,name', 'items.product:id,name'])
            ->whereDate('transaction_date', $today)
            ->orderBy('transaction_date', 'desc')
            ->get();

        // Get hourly breakdown untuk chart
        $hourlyBreakdown = DB::table('transactions')
            ->whereDate('transaction_date', $today)
            ->select(
                DB::raw('HOUR(transaction_date) as hour'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('SUM(total_profit) as total_profit')
            )
            ->groupBy('hour')
            ->orderBy('hour', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'report' => $report,
                'transactions' => $transactions,
                'hourly_breakdown' => $hourlyBreakdown
            ]
        ], 200);
    }
}