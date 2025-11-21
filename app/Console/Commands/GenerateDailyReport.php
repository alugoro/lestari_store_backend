<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DailyReport;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateDailyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:generate-daily {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily sales report for a specific date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date') ?? Carbon::yesterday()->format('Y-m-d');
        
        try {
            $reportDate = Carbon::parse($date);
        } catch (\Exception $e) {
            $this->error('Invalid date format. Use: YYYY-MM-DD');
            return 1;
        }

        $this->info("Generating daily report for: {$reportDate->format('Y-m-d')}");

        // Get all transactions for the date
        $transactions = Transaction::whereDate('transaction_date', $reportDate)->get();

        if ($transactions->isEmpty()) {
            $this->warn("No transactions found for {$reportDate->format('Y-m-d')}");
            return 0;
        }

        // Calculate totals
        $totalSales = $transactions->sum('total_amount');
        $totalProfit = $transactions->sum('total_profit');
        $cashAmount = $transactions->where('payment_method', 'cash')->sum('total_amount');
        $transferAmount = $transactions->where('payment_method', 'transfer')->sum('total_amount');
        $transactionCount = $transactions->count();

        // Get top products
        $topProducts = $this->getTopProducts($reportDate->format('Y-m-d'));

        // Create or update report
        $report = DailyReport::updateOrCreate(
            ['report_date' => $reportDate->format('Y-m-d')],
            [
                'total_sales' => $totalSales,
                'total_profit' => $totalProfit,
                'cash_amount' => $cashAmount,
                'transfer_amount' => $transferAmount,
                'transaction_count' => $transactionCount,
                'top_products' => $topProducts,
            ]
        );

        $this->info("✓ Report generated successfully!");
        $this->line("  Transactions: {$transactionCount}");
        $this->line("  Total Sales: Rp " . number_format($totalSales, 0, ',', '.'));
        $this->line("  Total Profit: Rp " . number_format($totalProfit, 0, ',', '.'));

        return 0;
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
}