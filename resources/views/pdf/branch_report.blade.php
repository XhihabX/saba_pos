<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Branch Performance Report</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; padding: 15px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0f172a; padding-bottom: 10px; }
        .header h2 { margin: 0 0 5px 0; color: #0f172a; }
        .header p { margin: 2px 0; color: #64748b; }
        .summary-grid { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .summary-grid th, .summary-grid td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }
        .summary-grid th { background-color: #f1f5f9; font-weight: bold; color: #334155; }
        .text-right { text-align: right; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="header">
        <h2>PER-BRANCH SALES & INVENTORY PERFORMANCE REPORT</h2>
        <p>Generated on: {{ date('Y-m-d H:i:s') }}</p>
    </div>

    <table class="summary-grid">
        <thead>
            <tr>
                <th>Branch / Store</th>
                <th class="text-right">Total Orders</th>
                <th class="text-right">Items Sold</th>
                <th class="text-right">Gross Revenue</th>
                <th class="text-right">VAT Collected</th>
                <th class="text-right">COGS</th>
                <th class="text-right">Net Profit</th>
                <th class="text-right">Stock Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach($branch_data as $branch)
                <tr>
                    <td><strong>{{ $branch['store_name'] }}</strong></td>
                    <td class="text-right">{{ number_format($branch['total_orders']) }}</td>
                    <td class="text-right">{{ number_format($branch['items_sold']) }}</td>
                    <td class="text-right">BDT {{ number_format($branch['gross_revenue'], 2) }}</td>
                    <td class="text-right">BDT {{ number_format($branch['vat_collected'], 2) }}</td>
                    <td class="text-right">BDT {{ number_format($branch['cogs'], 2) }}</td>
                    <td class="text-right"><strong>BDT {{ number_format($branch['net_profit'], 2) }}</strong></td>
                    <td class="text-right">{{ number_format($branch['total_stock_qty']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Saba POS Enterprise Branch Analytics Engine</p>
    </div>
</body>
</html>
