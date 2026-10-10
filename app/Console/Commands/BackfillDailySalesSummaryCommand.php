<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillDailySalesSummaryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:backfill-summary {--tenant= : Specific tenant ID to backfill}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill daily sales summary table from historical orders and returns';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $this->info('Starting backfill of daily_sales_summaries...');

        $now = now()->toDateTimeString();

        // 1. Backfill Sales from orders & order_payments
        $salesSql = "
            INSERT INTO daily_sales_summaries (
                tenant_id, store_id, date, orders_count, subtotal, tax_amount, grand_total, cogs, refunds,
                cash_total, card_total, bkash_total, nagad_total, rocket_total, upay_total, due_total, other_total,
                created_at, updated_at
            )
            SELECT
                o.tenant_id,
                o.store_id,
                DATE(o.created_at) as summary_date,
                COUNT(*) as orders_count,
                COALESCE(SUM(o.subtotal), 0) as subtotal,
                COALESCE(SUM(o.tax_amount), 0) as tax_amount,
                COALESCE(SUM(o.grand_total), 0) as grand_total,
                COALESCE(SUM(o.cogs), 0) as cogs,
                0.00 as refunds,
                COALESCE(SUM(CASE WHEN LOWER(p.payment_method) = 'cash' THEN p.amount ELSE 0 END), 0) as cash_total,
                COALESCE(SUM(CASE WHEN LOWER(p.payment_method) = 'card' THEN p.amount ELSE 0 END), 0) as card_total,
                COALESCE(SUM(CASE WHEN LOWER(p.payment_method) = 'bkash' THEN p.amount ELSE 0 END), 0) as bkash_total,
                COALESCE(SUM(CASE WHEN LOWER(p.payment_method) = 'nagad' THEN p.amount ELSE 0 END), 0) as nagad_total,
                COALESCE(SUM(CASE WHEN LOWER(p.payment_method) = 'rocket' THEN p.amount ELSE 0 END), 0) as rocket_total,
                COALESCE(SUM(CASE WHEN LOWER(p.payment_method) = 'upay' THEN p.amount ELSE 0 END), 0) as upay_total,
                COALESCE(SUM(CASE WHEN LOWER(p.payment_method) = 'due' THEN p.amount ELSE 0 END), 0) as due_total,
                COALESCE(SUM(CASE WHEN LOWER(p.payment_method) NOT IN ('cash', 'card', 'bkash', 'nagad', 'rocket', 'upay', 'due') THEN p.amount ELSE 0 END), 0) as other_total,
                '{$now}' as created_at,
                '{$now}' as updated_at
            FROM orders o
            LEFT JOIN order_payments p ON o.id = p.order_id
            ".($tenantId ? 'WHERE o.tenant_id = '.intval($tenantId) : '').'
            GROUP BY o.tenant_id, o.store_id, DATE(o.created_at)
        ';

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $salesSql .= '
                ON CONFLICT(tenant_id, store_id, date) DO UPDATE SET
                    orders_count = excluded.orders_count,
                    subtotal = excluded.subtotal,
                    tax_amount = excluded.tax_amount,
                    grand_total = excluded.grand_total,
                    cogs = excluded.cogs,
                    cash_total = excluded.cash_total,
                    card_total = excluded.card_total,
                    bkash_total = excluded.bkash_total,
                    nagad_total = excluded.nagad_total,
                    rocket_total = excluded.rocket_total,
                    upay_total = excluded.upay_total,
                    due_total = excluded.due_total,
                    other_total = excluded.other_total,
                    updated_at = excluded.updated_at
            ';
        } else {
            $salesSql .= '
                ON DUPLICATE KEY UPDATE
                    orders_count = VALUES(orders_count),
                    subtotal = VALUES(subtotal),
                    tax_amount = VALUES(tax_amount),
                    grand_total = VALUES(grand_total),
                    cogs = VALUES(cogs),
                    cash_total = VALUES(cash_total),
                    card_total = VALUES(card_total),
                    bkash_total = VALUES(bkash_total),
                    nagad_total = VALUES(nagad_total),
                    rocket_total = VALUES(rocket_total),
                    upay_total = VALUES(upay_total),
                    due_total = VALUES(due_total),
                    other_total = VALUES(other_total),
                    updated_at = VALUES(updated_at)
            ';
        }

        DB::statement($salesSql);

        // 2. Backfill Returns from product_returns
        $returnsSql = "
            INSERT INTO daily_sales_summaries (
                tenant_id, store_id, date, orders_count, subtotal, tax_amount, grand_total, cogs, refunds,
                cash_total, card_total, bkash_total, nagad_total, rocket_total, upay_total, due_total, other_total,
                created_at, updated_at
            )
            SELECT
                r.tenant_id,
                r.store_id,
                DATE(r.created_at) as summary_date,
                0 as orders_count,
                0.00 as subtotal,
                0.00 as tax_amount,
                0.00 as grand_total,
                0.00 as cogs,
                COALESCE(SUM(r.refund_amount), 0) as refunds,
                0.00 as cash_total,
                0.00 as card_total,
                0.00 as bkash_total,
                0.00 as nagad_total,
                0.00 as rocket_total,
                0.00 as upay_total,
                0.00 as due_total,
                0.00 as other_total,
                '{$now}' as created_at,
                '{$now}' as updated_at
            FROM product_returns r
            ".($tenantId ? 'WHERE r.tenant_id = '.intval($tenantId) : '').'
            GROUP BY r.tenant_id, r.store_id, DATE(r.created_at)
        ';

        if ($driver === 'sqlite') {
            $returnsSql .= '
                ON CONFLICT(tenant_id, store_id, date) DO UPDATE SET
                    refunds = excluded.refunds,
                    updated_at = excluded.updated_at
            ';
        } else {
            $returnsSql .= '
                ON DUPLICATE KEY UPDATE
                    refunds = VALUES(refunds),
                    updated_at = VALUES(updated_at)
            ';
        }

        DB::statement($returnsSql);

        $count = DB::table('daily_sales_summaries')->count();
        $this->info("✅ Successfully backfilled {$count} daily summary records!");

        return Command::SUCCESS;
    }
}
