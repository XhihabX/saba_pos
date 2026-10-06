<?php

namespace App\Services;

use App\Models\Order;

class EfdBridgeService
{
    /**
     * Generate NBR EFD (Electronic Fiscal Device) Statutory JSON Payload & Hash
     */
    public static function generatePayload(Order $order): array
    {
        $store = $order->store;
        $binNumber = $store?->bin_number ?? $store?->vat_number ?? '123456789-0000';

        $items = $order->items->map(function ($item) {
            return [
                'item_code' => $item->product?->sku ?? "PRD-{$item->product_id}",
                'item_name' => $item->product_name,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total,
                'vat_amount' => round((float) $item->total * 0.05, 2),
            ];
        })->toArray();

        $payload = [
            'bin' => $binNumber,
            'store_code' => $store?->code ?? 'STORE-001',
            'invoice_number' => $order->invoice_no,
            'order_timestamp' => $order->created_at->toIso8601String(),
            'subtotal' => (float) $order->subtotal,
            'discount_amount' => (float) $order->discount_amount,
            'vat_amount' => (float) $order->tax_amount,
            'grand_total' => (float) $order->grand_total,
            'payment_method' => $order->payment_method,
            'items' => $items,
        ];

        $securityHash = hash_hmac('sha256', json_encode($payload), 'IOT_POS_EFD_SECRET_KEY');
        $payload['security_hash'] = $securityHash;
        $payload['efd_status'] = 'READY_FOR_SDC';

        return $payload;
    }
}
