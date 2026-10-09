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
}
