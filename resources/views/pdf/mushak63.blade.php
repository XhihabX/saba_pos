<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>মুসক-৬.৩ কর চালানপত্র {{ $order->invoice_no }}</title>
    <style>
        body { font-family: 'SolaimanLipi', Arial, sans-serif; font-size: 11px; color: #111; margin: 0; padding: 20px; }
        .invoice-box { max-width: 800px; margin: 0 auto; border: 1px solid #222; padding: 20px; background: #fff; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .header-title { font-size: 16px; font-weight: bold; text-decoration: underline; margin-bottom: 4px; }
        .sub-header { font-size: 12px; margin-bottom: 12px; }
        table.meta-table, table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data-table th, table.data-table td { border: 1px solid #333; padding: 6px; font-size: 10px; }
        table.data-table th { background: #f2f2f2; text-align: center; }
        .footer-note { margin-top: 20px; font-size: 10px; display: flex; justify-content: space-between; }
        @media print {
            body { padding: 0; }
            .invoice-box { border: none; width: 100%; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="text-align: center; margin-bottom: 15px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #059669; color: #fff; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">
            🖨️ প্রিন্ট করুন / Save Mushak 6.3 PDF
        </button>
    </div>

    <div class="invoice-box">
        <div class="text-center">
            <div class="header-title">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার, জাতীয় রাজস্ব বোর্ড</div>
            <div class="bold" style="font-size: 13px; margin-top: 2px;">কর চালানপত্র (মুসক-৬.৩)</div>
            <div class="sub-header">[বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও দফা (চ) দ্রষ্টব্য]</div>
        </div>

        <table class="meta-table">
            <tr>
                <td width="60%">
                    <strong>নিবন্ধিত ব্যক্তির নাম:</strong> {{ $order->store->name ?? 'IOT POS STORE' }}<br>
                    <strong>বিআইএন (BIN):</strong> {{ $order->store->bin_number ?? '001234567-0101' }}<br>
                    <strong>চালান ইস্যুর ঠিকানা:</strong> {{ $order->store->address ?? 'Main Outlet, Dhaka' }}
                </td>
                <td width="40%" class="text-right">
                    <strong>চালান নম্বর:</strong> {{ $order->invoice_no }}<br>
                    <strong>ইস্যুর তারিখ:</strong> {{ $order->created_at->format('Y-m-d') }}<br>
                    <strong>ইস্যুর সময়:</strong> {{ $order->created_at->format('h:i A') }}
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-top: 8px;">
                    <strong>ক্রেতার নাম:</strong> {{ $order->customer->name ?? 'Walk-in Customer' }} | 
                    <strong>ক্রেতার বিআইএন/এনআইডি:</strong> {{ $order->customer->bin_number ?? 'N/A' }} | 
                    <strong>ফোন:</strong> {{ $order->customer->phone ?? 'N/A' }}
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%">ক্রমিক</th>
                    <th width="35%">পণ্য বা সেবার বিবরণ (Product Details)</th>
                    <th width="10%">পরিমাণ (Qty)</th>
                    <th width="15%">একক মূল্য (Unit Price)</th>
                    <th width="15%">মোট মূল্য (Subtotal)</th>
                    <th width="10%">মূসক হার (VAT %)</th>
                    <th width="10%">মূসক পরিমাণ (VAT ৳)</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $taxRate = (float) ($order->store->default_tax_rate ?? 5.0); 
                    $isInclusive = (bool) ($order->store->vat_mode === 'inclusive');
                @endphp
                @foreach($order->items as $index => $item)
                @php
                    $lineTotal = (float) $item->total;
                    if ($isInclusive) {
                        $lineVat = round($lineTotal * ($taxRate / (100 + $taxRate)), 2);
                        $lineNet = round($lineTotal - $lineVat, 2);
                    } else {
                        $lineNet = $lineTotal;
                        $lineVat = round($lineNet * ($taxRate / 100), 2);
                    }
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td class="text-center">{{ (float) $item->quantity }}</td>
                    <td class="text-right">৳{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">৳{{ number_format($lineNet, 2) }}</td>
                    <td class="text-center">{{ number_format($taxRate, 1) }}%</td>
                    <td class="text-right">৳{{ number_format($lineVat, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right bold">সর্বমোট (Total):</td>
                    <td class="text-right bold">৳{{ number_format($order->subtotal - $order->discount_amount, 2) }}</td>
                    <td class="text-center bold">-</td>
                    <td class="text-right bold">৳{{ number_format($order->tax_amount, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="6" class="text-right bold">সর্বমোট প্রদেয় মূল্য (Grand Total Payable Incl. VAT):</td>
                    <td class="text-right bold" style="font-size: 12px;">৳{{ number_format($order->grand_total, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <div class="footer-note">
            <div>
                <br><br>
                --------------------------<br>
                দায়িত্বপ্রাপ্ত কর্মকর্তার স্বাক্ষর
            </div>
            <div class="text-right">
                <br><br>
                --------------------------<br>
                প্রতিষ্ঠানের সিল ও অনুমোদন
            </div>
        </div>

        <div class="text-center" style="margin-top: 15px; font-size: 9px; color: #555;">
            * এই চালানপত্রটি জাতীয় রাজস্ব বোর্ডের বিধিমালা অনুযায়ী প্রস্তুতকৃত আইওটি পস (IOT POS ERP) দ্বারা স্বয়ংক্রিয়ভাবে তৈরি।
        </div>
    </div>
</body>
</html>
