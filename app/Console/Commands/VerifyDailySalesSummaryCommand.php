<?php

namespace App\Console\Commands;

use App\Models\DailySalesSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerifyDailySalesSummaryCommand extends Command
{
    protected $signature = 'pos:verify-daily-summaries {--fix : Automatically recalculate and fix any summary drift}';
    protected $description = 'Audit daily_sales_summaries against direct aggregate of orders & returns for every tenant/store/day and report discrepancies';

    public function handle(): int
    {
        $this->info("==========================================================================================");
        $this->info(" 🔍 DAILY SALES SUMMARY INTEGRITY & DRIFT AUDIT");
        $this->info("==========================================================================================");

        $shouldFix = $this->option('fix');

        // Fetch distinct tenant_id, store_id, date combinations from both orders and daily_sales_summaries
        $orderDates = DB::table('orders')
            ->selectRaw('tenant_id, store_id, DATE(created_at) as date_val')
            ->groupBy('tenant_id', 'store_id', DB::raw('DATE(created_at)'));

        $summaryDates = DB::table('daily_sales_summaries')
            ->selectRaw('tenant_id, store_id, date as date_val');

        $allDates = $orderDates->union($summaryDates)->get();

        $discrepanciesCount = 0;
        $fixedCount = 0;
        $discrepancyAlerts = [];

        foreach ($allDates as $row) {
            $tenantId = (int)$row->tenant_id;
            $storeId = (int)$row->store_id;
            $date = (string)$row->date_val;

            if (empty($date)) continue;

            $start = "{$date} 00:00:00";
            $end = "{$date} 23:59:59";

            // Direct Aggregate from Orders
            $orderAgg = DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('
                    COUNT(*) as count,
                    COALESCE(SUM(subtotal), 0) as subtotal,
                    COALESCE(SUM(tax_amount), 0) as tax,
                    COALESCE(SUM(grand_total), 0) as grand,
                    COALESCE(SUM(cogs), 0) as cogs
                ')
                ->first();

            // Direct Aggregate from Returns
            $returnAgg = DB::table('product_returns')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('COALESCE(SUM(refund_amount), 0) as refunds')
                ->first();

            // Summary Record
            $summary = DailySalesSummary::where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('date', $date)
                ->first();

            $expectedCount = (int)($orderAgg->count ?? 0);
            $expectedSubtotal = round((float)($orderAgg->subtotal ?? 0.00), 2);
            $expectedTax = round((float)($orderAgg->tax ?? 0.00), 2);
            $expectedGrand = round((float)($orderAgg->grand ?? 0.00), 2);
            $expectedCogs = round((float)($orderAgg->cogs ?? 0.00), 2);
            $expectedRefunds = round((float)($returnAgg->refunds ?? 0.00), 2);

            $actualCount = $summary ? (int)$summary->orders_count : 0;
            $actualSubtotal = $summary ? round((float)$summary->subtotal, 2) : 0.00;
            $actualTax = $summary ? round((float)$summary->tax_amount, 2) : 0.00;
            $actualGrand = $summary ? round((float)$summary->grand_total, 2) : 0.00;
            $actualCogs = $summary ? round((float)$summary->cogs, 2) : 0.00;
            $actualRefunds = $summary ? round((float)$summary->refunds, 2) : 0.00;

            $differs = (
                $expectedCount !== $actualCount ||
                abs($expectedSubtotal - $actualSubtotal) > 0.01 ||
                abs($expectedTax - $actualTax) > 0.01 ||
                abs($expectedGrand - $actualGrand) > 0.01 ||
                abs($expectedCogs - $actualCogs) > 0.01 ||
                abs($expectedRefunds - $actualRefunds) > 0.01
            );

            if ($differs) {
                $discrepanciesCount++;

                $expectedData = [
                    'count' => $expectedCount,
                    'subtotal' => $expectedSubtotal,
                    'tax' => $expectedTax,
                    'grand_total' => $expectedGrand,
                    'cogs' => $expectedCogs,
                    'refunds' => $expectedRefunds,
                ];

                $actualData = [
                    'count' => $actualCount,
                    'subtotal' => $actualSubtotal,
                    'tax' => $actualTax,
                    'grand_total' => $actualGrand,
                    'cogs' => $actualCogs,
                    'refunds' => $actualRefunds,
                ];

                Log::error("DAILY_SUMMARY_DRIFT_DISCREPANCY: Discrepancy detected for tenant #{$tenantId}, store #{$storeId}, date {$date}", [
                    'tenant_id' => $tenantId,
                    'store_id' => $storeId,
                    'date' => $date,
                    'expected' => $expectedData,
                    'actual' => $actualData,
                ]);

                // Store persistent discrepancy alert in DB table discrepancy_alerts
                $alertRecord = \App\Models\DiscrepancyAlert::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'store_id' => $storeId,
                        'date' => $date,
                        'resolved_at' => null,
                    ],
                    [
                        'expected' => $expectedData,
                        'actual' => $actualData,
                    ]
                );

                // Send Email Notification to Tenant Owner
                try {
                    $tenant = \App\Models\Tenant::find($tenantId);
                    $ownerEmail = $tenant?->email;
                    if (!$ownerEmail) {
                        $owner = \App\Models\User::where('tenant_id', $tenantId)
                            ->whereIn('role', ['merchant', 'owner', 'super_admin'])
                            ->first();
                        $ownerEmail = $owner?->email;
                    }

                    if ($ownerEmail) {
                        \Illuminate\Support\Facades\Mail::to($ownerEmail)
                            ->send(new \App\Mail\DiscrepancyAlertMail($alertRecord));
                    }
                } catch (\Throwable $e) {
                    Log::warning("Could not send DiscrepancyAlertMail: " . $e->getMessage());
                }

                $this->warn(sprintf(
                    "⚠️ DRIFT DISCREPANCY DETECTED [Tenant #%d | Store #%d | Date: %s]:",
                    $tenantId, $storeId, $date
                ));
                $this->line(sprintf("   Orders Count -> Expected: %d | Actual: %d", $expectedCount, $actualCount));
                $this->line(sprintf("   Grand Total  -> Expected: ৳%.2f | Actual: ৳%.2f", $expectedGrand, $actualGrand));
                $this->line(sprintf("   Refunds      -> Expected: ৳%.2f | Actual: ৳%.2f", $expectedRefunds, $actualRefunds));

                if ($shouldFix) {
                    DailySalesSummary::recalculateDay($tenantId, $storeId, $date);
                    $alertRecord->update(['resolved_at' => now()]);
                    $fixedCount++;
                    $this->info("   ✓ Recalculated & synced summary row, resolved alert.");
                }
            }
        }

        $this->info("==========================================================================================");
        if ($discrepanciesCount === 0) {
            $this->info(" ✅ AUDIT VERDICT: 100% SUMMARY INTEGRITY (Zero drift across all tenant/store/day rows).");
            return 0;
        }

        if ($shouldFix) {
            $this->info(sprintf(" 🔧 AUDIT FIX COMPLETE: Corrected %d / %d summary discrepancies.", $fixedCount, $discrepanciesCount));
            return 0;
        }

        $this->error(sprintf(" ❌ AUDIT FAILURE: Found %d summary discrepancies. Logged to system and published admin alert.", $discrepanciesCount));
        return 1;
    }
}
