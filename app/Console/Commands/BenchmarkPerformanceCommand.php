<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BenchmarkPerformanceCommand extends Command
{
    protected $signature = 'pos:benchmark {--seed-only} {--run-only}';
    protected $description = 'Seed scale test data (20k products, 5k customers, 500k orders) and measure real page timing & memory telemetry';

    public function handle()
    {
        ini_set('memory_limit', '256M');
        $this->info('Initializing Enterprise POS Performance & Telemetry Benchmark (256MB Memory Cap)...');

        $tenant = Tenant::firstOrCreate(['code' => 'BM01'], [
            'name' => 'Benchmark Enterprise Tenant',
            'email' => 'bm@test.local',
            'phone' => '01700000000',
            'subscription_status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        $store = Store::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'BM-ST01'], [
            'name' => 'Benchmark Main Outlet',
            'phone' => '01700000000',
            'address' => 'Dhaka',
            'default_tax_rate' => 15.00,
            'is_active' => true,
        ]);

        $user = User::firstOrCreate(['email' => 'bm_cashier@test.local'], [
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Benchmark Cashier',
            'password' => Hash::make('password'),
            'role' => 'merchant',
            'pos_pin' => Hash::make('9999'),
        ]);

        auth()->login($user);

        if (!$this->option('run-only')) {
            $this->seedBenchmarkData($tenant, $store);
        }

        if ($this->option('seed-only')) {
            $this->info('Seeding complete. Exiting benchmark.');
            return 0;
        }

        $this->runPerformanceTelemetry($tenant, $store, $user);
        return 0;
    }

    private function seedBenchmarkData($tenant, $store)
    {
        $currentProducts = Product::where('tenant_id', $tenant->id)->count();
        if ($currentProducts < 20000) {
            $this->info("Seeding 20,000 products (current: {$currentProducts})...");
            $targetProducts = 20000 - $currentProducts;
            $batchSize = 2000;
            for ($i = 0; $i < $targetProducts; $i += $batchSize) {
                $productRows = [];
                $stockRows = [];
                $count = min($batchSize, $targetProducts - $i);
                for ($j = 1; $j <= $count; $j++) {
                    $idx = $currentProducts + $i + $j;
                    $productRows[] = [
                        'tenant_id' => $tenant->id,
                        'name' => "Scale Product {$idx}",
                        'sku' => "SKU-BM-{$idx}-" . uniqid(),
                        'barcode' => 'BM' . sprintf('%010d', $idx) . uniqid(),
                        'purchase_cost' => 80.00,
                        'selling_price' => 100.00,
                        'vat_rate' => 15.00,
                        'vat_mode' => 'exclusive',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('products')->insert($productRows);

                // Fetch inserted ids for stock rows
                $insertedProducts = Product::where('tenant_id', $tenant->id)
                    ->orderBy('id', 'desc')
                    ->limit($count)
                    ->pluck('id');

                foreach ($insertedProducts as $pid) {
                    $stockRows[] = [
                        'tenant_id' => $tenant->id,
                        'store_id' => $store->id,
                        'product_id' => $pid,
                        'quantity' => 1000.0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                Stock::insert($stockRows);
            }
        }

        $currentCustomers = Customer::where('tenant_id', $tenant->id)->count();
        if ($currentCustomers < 5000) {
            $this->info("Seeding 5,000 customers (current: {$currentCustomers})...");
            $targetCustomers = 5000 - $currentCustomers;
            $batchSize = 1000;
            for ($i = 0; $i < $targetCustomers; $i += $batchSize) {
                $customerRows = [];
                $count = min($batchSize, $targetCustomers - $i);
                for ($j = 1; $j <= $count; $j++) {
                    $idx = $currentCustomers + $i + $j;
                    $customerRows[] = [
                        'tenant_id' => $tenant->id,
                        'name' => "Customer {$idx}",
                        'phone' => sprintf('017%08d', $idx),
                        'due_balance' => 0.00,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                Customer::insert($customerRows);
            }
        }

        $currentOrders = Order::where('tenant_id', $tenant->id)->count();
        if ($currentOrders < 500000) {
            $this->info("Seeding 500,000 orders (current: {$currentOrders})...");
            $targetOrders = 500000 - $currentOrders;
            $batchSize = 5000;
            $sampleProduct = Product::where('tenant_id', $tenant->id)->first();
            for ($i = 0; $i < $targetOrders; $i += $batchSize) {
                $orderRows = [];
                $count = min($batchSize, $targetOrders - $i);
                for ($j = 1; $j <= $count; $j++) {
                    $idx = $currentOrders + $i + $j;
                    $orderRows[] = [
                        'tenant_id' => $tenant->id,
                        'store_id' => $store->id,
                        'idempotency_key' => "IDEM-SEED-{$idx}-" . uniqid(),
                        'invoice_no' => "INV-SEED-{$idx}-" . strtoupper(substr(uniqid(), -4)),
                        'subtotal' => 100.00,
                        'discount_amount' => 0.00,
                        'tax_amount' => 15.00,
                        'grand_total' => 115.00,
                        'paid_amount' => 115.00,
                        'change_return' => 0.00,
                        'payment_status' => 'paid',
                        'payment_method' => 'cash',
                        'created_at' => now()->subDays(rand(1, 180)),
                        'updated_at' => now(),
                    ];
                }
                DB::table('orders')->insert($orderRows);
            }
            $this->info('Orders seeded.');
        }
    }

    private function runPerformanceTelemetry($tenant, $store, $user)
    {
        $dbDriver = DB::connection()->getDriverName();
        $dbVersion = 'Unknown';
        try {
            $pdo = DB::connection()->getPdo();
            $dbVersion = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) ?? 'Unknown';
        } catch (\Throwable $e) {
            $dbVersion = 'N/A';
        }

        $prodCount = Product::count();
        $custCount = Customer::count();
        $orderCount = Order::count();
        $orderItemCount = OrderItem::count();
        $stockCount = Stock::count();

        $this->info("\n==========================================================================================");
        $this->info(" REAL TELEMETRY RESULTS (Target: < 2.0s, < 256MB)");
        $this->info(sprintf(" DB Engine: %s (v%s)", strtoupper($dbDriver), $dbVersion));
        $this->info(sprintf(" Database Row Counts: Products: %s | Customers: %s | Orders: %s | OrderItems: %s | Stocks: %s",
            number_format($prodCount),
            number_format($custCount),
            number_format($orderCount),
            number_format($orderItemCount),
            number_format($stockCount)
        ));
        $this->info("==========================================================================================");

        $posController = new \App\Http\Controllers\PosController();
        $reportController = new \App\Http\Controllers\ReportController();
        $merchantController = new \App\Http\Controllers\MerchantController();

        // 1. POS Load
        $t1 = microtime(true);
        $req1 = Request::create('/pos', 'GET', ['store_id' => $store->id]);
        $req1->setUserResolver(fn() => $user);
        $posController->index($req1);
        $dur1 = microtime(true) - $t1;
        $mem1 = memory_get_peak_usage(true) / 1024 / 1024;
        $this->line(sprintf("1. POS Load (/pos):                      %6.3fs | Peak Mem: %6.2f MB | %s", $dur1, $mem1, $dur1 < 2.0 ? "PASS" : "FAIL"));

        // 2. Product Search
        $t2 = microtime(true);
        $req2 = Request::create('/pos/products/search', 'GET', ['q' => 'Scale Product 100', 'store_id' => $store->id]);
        $req2->setUserResolver(fn() => $user);
        $posController->searchProducts($req2);
        $dur2 = microtime(true) - $t2;
        $mem2 = memory_get_peak_usage(true) / 1024 / 1024;
        $this->line(sprintf("2. Product Search (/pos/products/search): %6.3fs | Peak Mem: %6.2f MB | %s", $dur2, $mem2, $dur2 < 2.0 ? "PASS" : "FAIL"));

        // 3. Customer Search
        $t3 = microtime(true);
        $req3 = Request::create('/pos/customers/search', 'GET', ['q' => 'Customer 100']);
        $req3->setUserResolver(fn() => $user);
        $posController->searchCustomers($req3);
        $dur3 = microtime(true) - $t3;
        $mem3 = memory_get_peak_usage(true) / 1024 / 1024;
        $this->line(sprintf("3. Customer Search (/pos/customers/search):%6.3fs | Peak Mem: %6.2f MB | %s", $dur3, $mem3, $dur3 < 2.0 ? "PASS" : "FAIL"));

        // 4. Sales Checkout
        RegisterShift::firstOrCreate([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'status' => 'open',
        ], ['opening_float' => 1000.00, 'opened_at' => now()]);

        $prod = Product::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'SKU-BENCH-01'],
            ['name' => 'Benchmark Product', 'selling_price' => 100.00, 'purchase_cost' => 80.00, 'is_active' => true]
        );
        $stock = Stock::where('tenant_id', $tenant->id)
            ->where('store_id', $store->id)
            ->where('product_id', $prod->id)
            ->first();
        if (!$stock) {
            Stock::create([
                'tenant_id' => $tenant->id,
                'store_id' => $store->id,
                'product_id' => $prod->id,
                'quantity' => 100.0,
            ]);
        }

        $t4 = microtime(true);
        $req4 = Request::create('/pos/checkout', 'POST', [
            'store_id' => $store->id,
            'idempotency_key' => 'IDEM-BENCH-' . uniqid(),
            'items' => [['product_id' => $prod->id, 'quantity' => 1]],
            'paid_amount' => 115.00,
            'payment_method' => 'cash',
        ]);
        $req4->setUserResolver(fn() => $user);
        $posController->checkout($req4);
        $dur4 = microtime(true) - $t4;
        $mem4 = memory_get_peak_usage(true) / 1024 / 1024;
        $this->line(sprintf("4. Sales Checkout (/pos/checkout):       %6.3fs | Peak Mem: %6.2f MB | %s", $dur4, $mem4, $dur4 < 2.0 ? "PASS" : "FAIL"));

        // 5. Merchant Dashboard
        $t5 = microtime(true);
        $req5 = Request::create('/merchant/dashboard', 'GET');
        $req5->setUserResolver(fn() => $user);
        $merchantController->dashboard($req5);
        $dur5 = microtime(true) - $t5;
        $mem5 = memory_get_peak_usage(true) / 1024 / 1024;
        $this->line(sprintf("5. Dashboard (/merchant/dashboard):     %6.3fs | Peak Mem: %6.2f MB | %s", $dur5, $mem5, $dur5 < 2.0 ? "PASS" : "FAIL"));

        // 6. Profit & Loss Report
        $t6 = microtime(true);
        $req6 = Request::create('/reports/profit-loss', 'GET', ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-d')]);
        $req6->setUserResolver(fn() => $user);
        $reportController->profitLoss($req6);
        $dur6 = microtime(true) - $t6;
        $mem6 = memory_get_peak_usage(true) / 1024 / 1024;
        $this->line(sprintf("6. Profit & Loss (/reports/profit-loss): %6.3fs | Peak Mem: %6.2f MB | %s", $dur6, $mem6, $dur6 < 2.0 ? "PASS" : "FAIL"));

        // 7. VAT Report
        $t7 = microtime(true);
        $req7 = Request::create('/reports/vat', 'GET', ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-d')]);
        $req7->setUserResolver(fn() => $user);
        $reportController->vatReport($req7);
        $dur7 = microtime(true) - $t7;
        $mem7 = memory_get_peak_usage(true) / 1024 / 1024;
        $this->line(sprintf("7. VAT Report (/reports/vat):            %6.3fs | Peak Mem: %6.2f MB | %s", $dur7, $mem7, $dur7 < 2.0 ? "PASS" : "FAIL"));

        // 8. Stock Report
        $t8 = microtime(true);
        $req8 = Request::create('/reports/stock', 'GET');
        $req8->setUserResolver(fn() => $user);
        $reportController->stockReport($req8);
        $dur8 = microtime(true) - $t8;
        $mem8 = memory_get_peak_usage(true) / 1024 / 1024;
        $this->line(sprintf("8. Stock Report (/reports/stock):        %6.3fs | Peak Mem: %6.2f MB | %s", $dur8, $mem8, $dur8 < 2.0 ? "PASS" : "FAIL"));

        $this->info("==================================================\n");
    }
}
