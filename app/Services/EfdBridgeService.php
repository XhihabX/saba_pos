<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * EFD Bridge Module
 * NOTICE: Payload generator only, not connected to an NBR device.
 */
class EfdBridgeService
{
    /**
     * Generate NBR EFD (Electronic Fiscal Device) Statutory JSON Payload & Hash
     * Note: Payload generator only, not connected to an NBR device.
     */
    public static function generatePayload(Order $order): array
    {
        $store = $order->store;
        $binNumber = trim($store?->bin_number ?? $store?->vat_number ?? '');

        if ($store?->is_vat_registered && empty($binNumber)) {
            throw ValidationException::withMessages([
                'store' => ['VAT registered store must have a valid BIN number before issuing invoices.']
            ]);
        }

        $items = $order->items->map(function ($item) {
            return [
                'item_code' => $item->product?->sku ?? "PRD-{$item->product_id}",
                'item_name' => $item->product_name,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total,
                'vat_rate' => (float) ($item->vat_rate ?? 15.0),
                'vat_amount' => (float) ($item->vat_amount ?? 0.00),
            ];
        })->toArray();

        $payload = [
            'disclaimer' => 'Payload generator only, not connected to an NBR device',
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

        $efdSecret = config('services.efd.secret_key') ?: env('EFD_SECRET_KEY', '');
        $securityHash = hash_hmac('sha256', json_encode($payload), $efdSecret);
        $payload['security_hash'] = $securityHash;
        $payload['efd_status'] = 'READY_FOR_SDC';

        return $payload;
    }
}

