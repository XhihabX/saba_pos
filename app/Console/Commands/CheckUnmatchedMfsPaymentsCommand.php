<?php

namespace App\Console\Commands;

use App\Http\Controllers\StoreManagerController;
use App\Models\OrderPayment;
use Illuminate\Console\Command;

class CheckUnmatchedMfsPaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:check-unmatched-mfs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find counter-verified MFS payments unmatched after 24h, create discrepancy alerts and notify store owners';

    /**
     * Execute the console command.
     */
    public function handle(StoreManagerController $managerController): int
    {
        $this->info('Scanning for counter-verified MFS payments older than 24 hours...');

        $expiredUnmatched = OrderPayment::where('verification_status', 'counter_verified')
            ->where('created_at', '<=', now()->subHours(24))
            ->with(['order.store', 'order.user'])
            ->get();

        $count = 0;
        foreach ($expiredUnmatched as $payment) {
            $alert = $managerController->createMfsDiscrepancyAlertAndNotify($payment);
            if ($alert) {
                $count++;
            }
        }

        $this->info("Completed. Found {$expiredUnmatched->count()} expired unmatched payments. Processed {$count} discrepancy alerts.");

        return Command::SUCCESS;
    }
}
