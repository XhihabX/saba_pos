<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Invoice #{{ $order->id }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 15px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .header h2 { margin: 0 0 5px 0; color: #1e293b; }
        .header p { margin: 2px 0; color: #64748b; }
        .details-table { width: 100%; margin-bottom: 20px; }
        .details-table td { vertical-align: top; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th, .items-table td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }
        .items-table th { background-color: #f1f5f9; font-weight: bold; color: #334155; }
        .text-right { text-align: right; }
        .totals-table { width: 40%; float: right; border-collapse: collapse; }
        .totals-table td { padding: 5px 8px; }
        .totals-table tr.grand-total { font-weight: bold; font-size: 14px; border-top: 2px solid #0f172a; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $order->store->name ?? 'Saba POS Store' }}</h2>
        <p>{{ $order->store->address ?? 'Main Branch' }} | Phone: {{ $order->store->phone ?? 'N/A' }}</p>
        <p>BIN: {{ $order->store->bin_number ?? 'N/A' }}</p>
        <h3>SALES INVOICE</h3>
    </div>

    <table class="details-table">
        <tr>
            <td>
                <strong>Invoice No:</strong> {{ $order->invoice_no ?? ('#'.$order->id) }}<br>
                <strong>Date & Time:</strong> {{ $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s') }}<br>
                <strong>Cashier:</strong> {{ $order->user->name ?? 'System' }}
            </td>

            <td class="text-right">
                <strong>Customer:</strong> {{ $order->customer->name ?? 'Walk-in Customer' }}<br>
                <strong>Phone:</strong> {{ $order->customer->phone ?? 'N/A' }}<br>
                <strong>Payment Method:</strong> {{ strtoupper($order->payment_method ?? 'CASH') }}
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Item Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">VAT</th>
                <th class="text-right">Total (BDT)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $item->product_name }}
                        @if($item->variant_name)
                            <br><small style="color: #64748b;">Variant: {{ $item->variant_name }}</small>
                        @endif
                    </td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format($item->vat_amount, 2) }}</td>
                    <td class="text-right">{{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Subtotal:</td>
            <td class="text-right">BDT {{ number_format($order->subtotal ?? $order->total_amount, 2) }}</td>
        </tr>
        <tr>
            <td>Discount:</td>
            <td class="text-right">BDT {{ number_format($order->discount ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td>Itemized VAT:</td>
            <td class="text-right">BDT {{ number_format($order->vat_amount ?? 0, 2) }}</td>
        </tr>
        <tr class="grand-total">
            <td>Grand Total:</td>
            <td class="text-right">BDT {{ number_format($order->total_amount, 2) }}</td>
        </tr>
        <tr>
            <td>Paid Amount:</td>
            <td class="text-right">BDT {{ number_format($order->paid_amount ?? $order->total_amount, 2) }}</td>
        </tr>
    </table>

    <div style="clear: both;"></div>

    <div class="footer">
        <p>Thank you for shopping with {{ $order->store->name ?? 'Saba POS' }}!</p>
        <p>Software Powered by Saba POS Engine</p>
    </div>
</body>
</html>
