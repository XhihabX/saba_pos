<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mushak 6.3 - Invoice #{{ $order->id }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; padding: 10px; }
        .nbr-header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 15px; }
        .nbr-header h3 { margin: 0; text-transform: uppercase; font-size: 14px; }
        .nbr-header p { margin: 2px 0; font-size: 10px; color: #475569; }
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-grid td { padding: 4px 6px; border: 1px solid #cbd5e1; vertical-align: top; }
        .items-grid { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .items-grid th, .items-grid td { border: 1px solid #94a3b8; padding: 6px; text-align: left; }
        .items-grid th { background-color: #e2e8f0; font-weight: bold; font-size: 10px; }
        .text-right { text-align: right; }
        .footer-note { font-size: 9px; text-align: center; color: #64748b; margin-top: 25px; }
    </style>
</head>
<body>
    <div class="nbr-header">
        <p>Government of the People's Republic of Bangladesh | National Board of Revenue</p>
        <h3>TAX INVOICE (MUSHAK 6.3 / মুসক-৬.৩)</h3>
        <p>[See Sub-rule (1) of Rule 40]</p>
    </div>


    <table class="info-grid">
        <tr>
            <td width="50%">
                <strong>Registered Person/Store:</strong> {{ $order->store->name ?? 'Saba POS' }}<br>
                <strong>BIN:</strong> {{ $order->store->bin_number ?? 'N/A' }}<br>
                <strong>Address:</strong> {{ $order->store->address ?? 'Main Office' }}
            </td>
            <td width="50%">
                <strong>Invoice No:</strong> {{ $order->invoice_no ?? ('MUSHAK-'.$order->id) }}<br>
                <strong>Issue Date & Time:</strong> {{ $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s') }}<br>
                <strong>Customer Name/BIN:</strong> {{ $order->customer->name ?? 'Unregistered Buyer' }}
            </td>

        </tr>
    </table>

    <table class="items-grid">
        <thead>
            <tr>
                <th>SL</th>
                <th>Item Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Value (BDT)</th>
                <th class="text-right">Total Value (BDT)</th>
                <th class="text-right">SD Rate</th>
                <th class="text-right">VAT Rate</th>
                <th class="text-right">VAT Amount (BDT)</th>
                <th class="text-right">Grand Total (BDT)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $idx => $item)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>{{ $item->product_name }} {{ $item->variant_name ? '('.$item->variant_name.')' : '' }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format($item->unit_price * $item->quantity, 2) }}</td>
                    <td class="text-right">0%</td>
                    <td class="text-right">{{ number_format($item->vat_rate, 1) }}%</td>
                    <td class="text-right">{{ number_format($item->vat_amount, 2) }}</td>
                    <td class="text-right">{{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%;">
        <tr>
            <td style="width: 60%; vertical-align: bottom;">
                <p>Issued by Signature & Seal: _______________________</p>
            </td>
            <td style="width: 40%;">
                <table class="info-grid">
                    <tr>
                        <td><strong>Total Net Value:</strong></td>
                        <td class="text-right">BDT {{ number_format($order->subtotal ?? $order->total_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td><strong>Total VAT Amount:</strong></td>
                        <td class="text-right">BDT {{ number_format($order->vat_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td><strong>Total Payable Amount:</strong></td>
                        <td class="text-right"><strong>BDT {{ number_format($order->total_amount, 2) }}</strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        This tax invoice is generated automatically by NBR-compliant Saba POS Engine.
    </div>
</body>
</html>
