<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DailySalesSummary extends Model
{
    use HasFactory, Tenantable;

    protected $table = 'daily_sales_summaries';

    protected $fillable = [
        'tenant_id',
        'store_id',
        'date',
        'orders_count',
        'subtotal',
        'tax_amount',
        'grand_total',
        'cogs',
        'refunds',
        'cash_total',
        'card_total',
        'bkash_total',
        'nagad_total',
        'rocket_total',
        'upay_total',
        'due_total',
        'other_total',
    ];

    protected $casts = [
        'date' => 'string',
        'orders_count' => 'integer',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'cogs' => 'decimal:2',
        'refunds' => 'decimal:2',
        'cash_total' => 'decimal:2',
        'card_total' => 'decimal:2',
        'bkash_total' => 'decimal:2',
        'nagad_total' => 'decimal:2',
        'rocket_total' => 'decimal:2',
        'upay_total' => 'decimal:2',
        'due_total' => 'decimal:2',
        'other_total' => 'decimal:2',
    ];

    /**
     * Atomically record a completed sale in the daily summary.
     */
    public static function recordSale(
        int $tenantId,
        int $storeId,
        string $date,
        float $subtotal,
        float $taxAmount,
        float $grandTotal,
        float $cogs,
        array $payments = []
    ): void {
        $paymentTotals = [
            'cash_total' => 0.0,
            'card_total' => 0.0,
            'bkash_total' => 0.0,
            'nagad_total' => 0.0,
            'rocket_total' => 0.0,
            'upay_total' => 0.0,
            'due_total' => 0.0,
            'other_total' => 0.0,
        ];

        foreach ($payments as $method => $amount) {
            $key = strtolower((string)$method) . '_total';
            if (array_key_exists($key, $paymentTotals)) {
                $paymentTotals[$key] += (float)$amount;
            } else {
                $paymentTotals['other_total'] += (float)$amount;
            }
        }

        $now = now()->toDateTimeString();
        $driver = DB::getDriverName();

        $bindings = [
            $tenantId, $storeId, $date, $subtotal, $taxAmount, $grandTotal, $cogs,
            $paymentTotals['cash_total'], $paymentTotals['card_total'], $paymentTotals['bkash_total'],
            $paymentTotals['nagad_total'], $paymentTotals['rocket_total'], $paymentTotals['upay_total'],
            $paymentTotals['due_total'], $paymentTotals['other_total'],
            $now, $now
        ];

        if ($driver === 'sqlite') {
            DB::statement("
                INSERT INTO daily_sales_summaries (
                    tenant_id, store_id, date, orders_count, subtotal, tax_amount, grand_total, cogs, refunds,
                    cash_total, card_total, bkash_total, nagad_total, rocket_total, upay_total, due_total, other_total,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, 1, ?, ?, ?, ?, 0.00,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?
                )
                ON CONFLICT(tenant_id, store_id, date) DO UPDATE SET
                    orders_count = orders_count + 1,
                    subtotal = subtotal + excluded.subtotal,
                    tax_amount = tax_amount + excluded.tax_amount,
                    grand_total = grand_total + excluded.grand_total,
                    cogs = cogs + excluded.cogs,
                    cash_total = cash_total + excluded.cash_total,
                    card_total = card_total + excluded.card_total,
                    bkash_total = bkash_total + excluded.bkash_total,
                    nagad_total = nagad_total + excluded.nagad_total,
                    rocket_total = rocket_total + excluded.rocket_total,
                    upay_total = upay_total + excluded.upay_total,
                    due_total = due_total + excluded.due_total,
                    other_total = other_total + excluded.other_total,
                    updated_at = excluded.updated_at
            ", $bindings);
        } else {
            DB::statement("
                INSERT INTO daily_sales_summaries (
                    tenant_id, store_id, date, orders_count, subtotal, tax_amount, grand_total, cogs, refunds,
                    cash_total, card_total, bkash_total, nagad_total, rocket_total, upay_total, due_total, other_total,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, 1, ?, ?, ?, ?, 0.00,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?
                )
                ON DUPLICATE KEY UPDATE
                    orders_count = orders_count + 1,
                    subtotal = subtotal + VALUES(subtotal),
                    tax_amount = tax_amount + VALUES(tax_amount),
                    grand_total = grand_total + VALUES(grand_total),
                    cogs = cogs + VALUES(cogs),
                    cash_total = cash_total + VALUES(cash_total),
                    card_total = card_total + VALUES(card_total),
                    bkash_total = bkash_total + VALUES(bkash_total),
                    nagad_total = nagad_total + VALUES(nagad_total),
                    rocket_total = rocket_total + VALUES(rocket_total),
                    upay_total = upay_total + VALUES(upay_total),
                    due_total = due_total + VALUES(due_total),
                    other_total = other_total + VALUES(other_total),
                    updated_at = VALUES(updated_at)
            ", $bindings);
        }
    }

    /**
     * Atomically record a product return / refund in the daily summary.
     */
    public static function recordReturn(
        int $tenantId,
        int $storeId,
        string $date,
        float $refundAmount
    ): void {
        $now = now()->toDateTimeString();
        $driver = DB::getDriverName();

        $bindings = [$tenantId, $storeId, $date, $refundAmount, $now, $now];

        if ($driver === 'sqlite') {
            DB::statement("
                INSERT INTO daily_sales_summaries (
                    tenant_id, store_id, date, orders_count, subtotal, tax_amount, grand_total, cogs, refunds,
                    cash_total, card_total, bkash_total, nagad_total, rocket_total, upay_total, due_total, other_total,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, 0, 0.00, 0.00, 0.00, 0.00, ?,
                    0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00,
                    ?, ?
                )
                ON CONFLICT(tenant_id, store_id, date) DO UPDATE SET
                    refunds = refunds + excluded.refunds,
                    updated_at = excluded.updated_at
            ", $bindings);
        } else {
            DB::statement("
                INSERT INTO daily_sales_summaries (
                    tenant_id, store_id, date, orders_count, subtotal, tax_amount, grand_total, cogs, refunds,
                    cash_total, card_total, bkash_total, nagad_total, rocket_total, upay_total, due_total, other_total,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, 0, 0.00, 0.00, 0.00, 0.00, ?,
                    0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00,
                    ?, ?
                )
                ON DUPLICATE KEY UPDATE
                    refunds = refunds + VALUES(refunds),
                    updated_at = VALUES(updated_at)
            ", $bindings);
        }
    }

    /**
     * Atomically record a voided / cancelled / deleted sale in the daily summary.
     */
    public static function recordVoidSale(
        int $tenantId,
        int $storeId,
        string $date,
        float $subtotal,
        float $taxAmount,
        float $grandTotal,
        float $cogs,
        array $payments = []
    ): void {
        $paymentTotals = [
            'cash_total' => 0.0,
            'card_total' => 0.0,
            'bkash_total' => 0.0,
            'nagad_total' => 0.0,
            'rocket_total' => 0.0,
            'upay_total' => 0.0,
            'due_total' => 0.0,
            'other_total' => 0.0,
        ];

        foreach ($payments as $method => $amount) {
            $key = strtolower((string)$method) . '_total';
            if (array_key_exists($key, $paymentTotals)) {
                $paymentTotals[$key] += (float)$amount;
            } else {
                $paymentTotals['other_total'] += (float)$amount;
            }
        }

        $now = now()->toDateTimeString();
        $driver = DB::getDriverName();

        $bindings = [
            $tenantId, $storeId, $date, $subtotal, $taxAmount, $grandTotal, $cogs,
            $paymentTotals['cash_total'], $paymentTotals['card_total'], $paymentTotals['bkash_total'],
            $paymentTotals['nagad_total'], $paymentTotals['rocket_total'], $paymentTotals['upay_total'],
            $paymentTotals['due_total'], $paymentTotals['other_total'],
            $now, $now
        ];

        if ($driver === 'sqlite') {
            DB::statement("
                INSERT INTO daily_sales_summaries (
                    tenant_id, store_id, date, orders_count, subtotal, tax_amount, grand_total, cogs, refunds,
                    cash_total, card_total, bkash_total, nagad_total, rocket_total, upay_total, due_total, other_total,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, 0, ?, ?, ?, ?, 0.00,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?
                )
                ON CONFLICT(tenant_id, store_id, date) DO UPDATE SET
                    orders_count = max(0, orders_count - 1),
                    subtotal = max(0, subtotal - excluded.subtotal),
                    tax_amount = max(0, tax_amount - excluded.tax_amount),
                    grand_total = max(0, grand_total - excluded.grand_total),
                    cogs = max(0, cogs - excluded.cogs),
                    cash_total = max(0, cash_total - excluded.cash_total),
                    card_total = max(0, card_total - excluded.card_total),
                    bkash_total = max(0, bkash_total - excluded.bkash_total),
                    nagad_total = max(0, nagad_total - excluded.nagad_total),
                    rocket_total = max(0, rocket_total - excluded.rocket_total),
                    upay_total = max(0, upay_total - excluded.upay_total),
                    due_total = max(0, due_total - excluded.due_total),
                    other_total = max(0, other_total - excluded.other_total),
                    updated_at = excluded.updated_at
            ", $bindings);
        } else {
            DB::statement("
                INSERT INTO daily_sales_summaries (
                    tenant_id, store_id, date, orders_count, subtotal, tax_amount, grand_total, cogs, refunds,
                    cash_total, card_total, bkash_total, nagad_total, rocket_total, upay_total, due_total, other_total,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, 0, ?, ?, ?, ?, 0.00,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?
                )
                ON DUPLICATE KEY UPDATE
                    orders_count = GREATEST(0, CAST(orders_count AS SIGNED) - 1),
                    subtotal = GREATEST(0.00, subtotal - VALUES(subtotal)),
                    tax_amount = GREATEST(0.00, tax_amount - VALUES(tax_amount)),
                    grand_total = GREATEST(0.00, grand_total - VALUES(grand_total)),
                    cogs = GREATEST(0.00, cogs - VALUES(cogs)),
                    cash_total = GREATEST(0.00, cash_total - VALUES(cash_total)),
                    card_total = GREATEST(0.00, card_total - VALUES(card_total)),
                    bkash_total = GREATEST(0.00, bkash_total - VALUES(bkash_total)),
                    nagad_total = GREATEST(0.00, nagad_total - VALUES(nagad_total)),
                    rocket_total = GREATEST(0.00, rocket_total - VALUES(rocket_total)),
                    upay_total = GREATEST(0.00, upay_total - VALUES(upay_total)),
                    due_total = GREATEST(0.00, due_total - VALUES(due_total)),
                    other_total = GREATEST(0.00, other_total - VALUES(other_total)),
                    updated_at = VALUES(updated_at)
            ", $bindings);
        }
    }

    /**
     * Deterministically recalculate and sync the daily summary for a specific tenant, store, and date.
     */
    public static function recalculateDay(int $tenantId, int $storeId, string $date): void
    {
        $start = "{$date} 00:00:00";
        $end = "{$date} 23:59:59";

        $orderStats = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('
                COUNT(*) as orders_count,
                SUM(subtotal) as subtotal,
                SUM(tax_amount) as tax_amount,
                SUM(grand_total) as grand_total,
                SUM(cogs) as cogs
            ')
            ->first();

        $paymentStats = DB::table('order_payments')
            ->join('orders', 'order_payments.order_id', '=', 'orders.id')
            ->where('order_payments.tenant_id', $tenantId)
            ->where('orders.store_id', $storeId)
            ->whereBetween('order_payments.created_at', [$start, $end])
            ->selectRaw("
                SUM(CASE WHEN LOWER(order_payments.payment_method) = 'cash' THEN amount ELSE 0 END) as cash_total,
                SUM(CASE WHEN LOWER(order_payments.payment_method) = 'card' THEN amount ELSE 0 END) as card_total,
                SUM(CASE WHEN LOWER(order_payments.payment_method) = 'bkash' THEN amount ELSE 0 END) as bkash_total,
                SUM(CASE WHEN LOWER(order_payments.payment_method) = 'nagad' THEN amount ELSE 0 END) as nagad_total,
                SUM(CASE WHEN LOWER(order_payments.payment_method) = 'rocket' THEN amount ELSE 0 END) as rocket_total,
                SUM(CASE WHEN LOWER(order_payments.payment_method) = 'upay' THEN amount ELSE 0 END) as upay_total,
                SUM(CASE WHEN LOWER(order_payments.payment_method) IN ('due', 'credit') THEN amount ELSE 0 END) as due_total,
                SUM(CASE WHEN LOWER(order_payments.payment_method) NOT IN ('cash', 'card', 'bkash', 'nagad', 'rocket', 'upay', 'due', 'credit') THEN amount ELSE 0 END) as other_total
            ")
            ->first();

        $returnStats = DB::table('product_returns')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('SUM(refund_amount) as total_refunds')
            ->first();

        self::updateOrCreate(
            ['tenant_id' => $tenantId, 'store_id' => $storeId, 'date' => $date],
            [
                'orders_count' => (int)($orderStats->orders_count ?? 0),
                'subtotal' => (float)($orderStats->subtotal ?? 0.00),
                'tax_amount' => (float)($orderStats->tax_amount ?? 0.00),
                'grand_total' => (float)($orderStats->grand_total ?? 0.00),
                'cogs' => (float)($orderStats->cogs ?? 0.00),
                'refunds' => (float)($returnStats->total_refunds ?? 0.00),
                'cash_total' => (float)($paymentStats->cash_total ?? 0.00),
                'card_total' => (float)($paymentStats->card_total ?? 0.00),
                'bkash_total' => (float)($paymentStats->bkash_total ?? 0.00),
                'nagad_total' => (float)($paymentStats->nagad_total ?? 0.00),
                'rocket_total' => (float)($paymentStats->rocket_total ?? 0.00),
                'upay_total' => (float)($paymentStats->upay_total ?? 0.00),
                'due_total' => (float)($paymentStats->due_total ?? 0.00),
                'other_total' => (float)($paymentStats->other_total ?? 0.00),
            ]
        );
    }
}
