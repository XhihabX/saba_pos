<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExportSalesCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tenantId;
    public string $startDate;
    public string $endDate;
    public string $exportId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $tenantId, string $startDate, string $endDate, string $exportId)
    {
        $this->tenantId = $tenantId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->exportId = $exportId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $fileName = "exports/sales_report_{$this->tenantId}_{$this->exportId}.csv";
        $filePath = storage_path("app/public/{$fileName}");

        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        $file = fopen($filePath, 'w');
        fputcsv($file, ['Invoice No', 'Date', 'Store', 'Customer', 'Subtotal', 'Discount', 'VAT (Tax)', 'Grand Total', 'Payment Method', 'Payment Status']);

        DB::table('orders')
            ->where('orders.tenant_id', $this->tenantId)
            ->whereBetween('orders.created_at', [$this->startDate, $this->endDate])
            ->leftJoin('stores', 'orders.store_id', '=', 'stores.id')
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->select([
                'orders.id',
                'orders.invoice_no',
                'orders.created_at',
                'stores.name as store_name',
                'customers.name as customer_name',
                'orders.subtotal',
                'orders.discount_amount',
                'orders.tax_amount',
                'orders.grand_total',
                'orders.payment_method',
                'orders.payment_status',
            ])
            ->chunkById(5000, function ($orders) use ($file) {
                foreach ($orders as $order) {
                    fputcsv($file, [
                        $order->invoice_no,
                        $order->created_at,
                        $order->store_name ?? 'N/A',
                        $order->customer_name ?? 'Walk-in Customer',
                        $order->subtotal,
                        $order->discount_amount,
                        $order->tax_amount,
                        $order->grand_total,
                        $order->payment_method,
                        $order->payment_status,
                    ]);
                }
            }, 'orders.id', 'id');

        fclose($file);

        // Record completion in cache or status store
        cache()->put("export_status_{$this->exportId}", [
            'status' => 'completed',
            'file_name' => basename($filePath),
            'download_url' => asset("storage/{$fileName}"),
            'completed_at' => now()->toDateTimeString(),
        ], 86400);
    }
}
