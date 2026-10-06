<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $order->invoice_no }} - {{ $order->store->name ?? 'IOT POS' }}</title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 12px; margin: 0; padding: 15px; color: #111; }
        .receipt { max-width: 320px; margin: 0 auto; background: #fff; padding: 10px; border: 1px dashed #ccc; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .border-t { border-top: 1px dashed #000; margin-top: 6px; padding-top: 6px; }
        .border-b { border-bottom: 1px dashed #000; margin-bottom: 6px; padding-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        th, td { text-align: left; padding: 2px 0; font-size: 11px; }
        @media print {
            body { padding: 0; }
            .receipt { border: none; max-width: 100%; width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="margin-bottom: 15px; text-align: center;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">🖨️ Print / Save as PDF</button>
    </div>

    <div class="receipt">
        <div class="text-center">
            <h2 style="margin: 0; font-size: 16px;">{{ $order->store->name ?? 'IOT POS STORE' }}</h2>
            <p style="margin: 2px 0; font-size: 10px;">{{ $order->store->address ?? 'Main Branch, Dhaka, Bangladesh' }}</p>
            <p style="margin: 2px 0; font-size: 10px;">Phone: {{ $order->store->phone ?? '+8801700000000' }}</p>
            <p style="margin: 2px 0; font-size: 10px;">BIN/VAT: {{ $order->store->bin_number ?? '123456789-0000' }}</p>
        </div>

        <div class="border-t border-b" style="margin-top: 8px;">
            <div style="display: flex; justify-content: space-between;">
                <span>INV: <span class="bold">{{ $order->invoice_no }}</span></span>
                <span>Date: {{ $order->created_at->format('Y-m-d H:i') }}</span>
            </div>
            <div>Customer: <span class="bold">{{ $order->customer->name ?? 'Walk-in Customer' }}</span></div>
            <div>Cashier: <span>{{ $order->user->name ?? 'POS Counter' }}</span></div>
        </div>

        <table>
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th>Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td class="text-right">{{ (float) $item->quantity }}</td>
                    <td class="text-right">৳{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">৳{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="border-t">
            <div style="display: flex; justify-content: space-between;">
                <span>Subtotal:</span>
                <span class="bold">৳{{ number_format($order->subtotal, 2) }}</span>
            </div>
            @if($order->discount_amount > 0)
            <div style="display: flex; justify-content: space-between;">
                <span>Discount:</span>
                <span>-৳{{ number_format($order->discount_amount, 2) }}</span>
            </div>
            @endif
            <div style="display: flex; justify-content: space-between;">
                <span>VAT (5%):</span>
                <span>+৳{{ number_format($order->tax_amount, 2) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 14px; margin-top: 4px;" class="bold">
                <span>Grand Total:</span>
                <span>৳{{ number_format($order->grand_total, 2) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>Paid ({{ strtoupper($order->payment_method) }}):</span>
                <span>৳{{ number_format($order->paid_amount, 2) }}</span>
            </div>
            @if($order->change_return > 0)
            <div style="display: flex; justify-content: space-between;">
                <span>Change Return:</span>
                <span>৳{{ number_format($order->change_return, 2) }}</span>
            </div>
            @endif
        </div>

        <div class="text-center border-t" style="margin-top: 12px; font-size: 10px;">
            <p style="margin: 2px 0;">Thank you for shopping with us!</p>
            <p style="margin: 2px 0;">Powered by IOT POS ERP Solutions</p>
        </div>
    </div>
</body>
</html>
