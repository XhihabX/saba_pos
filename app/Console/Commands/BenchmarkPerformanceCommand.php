<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\DailySalesSummary;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BenchmarkPerformanceCommand extends Command
{
    protected $signature = 'pos:benchmark {--seed} {--seed-only} {--run-only}';
    protected $description = 'Seed scale test data and measure real COLD vs WARM page timings, CSV stream metrics, & EXPLAIN ANALYZE query telemetry on MySQL 8.4';

    public function handle()
    {
        ini_set('memory_limit', '256M');

        $driver = DB::connection()->getDriverName();
        $dbName = DB::connection()->getDatabaseName();

        // 1. Safety Guard: Refuse to run unless driver is mysql AND database name ends in _benchmark or _test
        if ($driver !== 'mysql') {
            $this->error("\n[SAFETY ERROR] Benchmark must be executed on MySQL (current driver: {$driver}).");
            $this->error("Please configure DB_CONNECTION=mysql in your .env.\n");
            return 1;
        }

        if (!preg_match('/(_benchmark|_test)$/i', $dbName)) {
            $this->error("\n[SAFETY ERROR] Database safety violation!");
            $this->error("Active database \"{$dbName}\" is not named like *_benchmark or *_test.");
            $this->error("Refusing to run benchmark to protect production and real databases!\n");
            return 1;
        }

        $this->info("\n==========================================================================================");
        $this->info(" 🚀 ENTERPRISE POS PERFORMANCE & QUERY EXPLAIN BENCHMARK");
        $this->info(sprintf(" Database: %s | Engine: %s | Memory Cap: 256MB", $dbName, strtoupper($driver)));
        $this->info("==========================================================================================");

        $tenant = Tenant::firstOrCreate(['code' => 'BM-TENANT-01'], [
            'name' => 'Benchmark Enterprise Chain',
            'email' => 'bm_chain@test.local',
            'phone' => '01700000000',
            'subscription_status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        // Ensure 3 Stores exist
        $stores = [];
        for ($s = 1; $s <= 3; $s++) {
            $code = sprintf('BM-ST-%02d', $s);
            $stores[] = Store::firstOrCreate(['tenant_id' => $tenant->id, 'code' => $code], [
                'name' => "Benchmark Outlet Branch {$s}",
                'phone' => sprintf('017000000%02d', $s),
                'address' => "Branch {$s} Location, Dhaka",
                'default_tax_rate' => 15.00,
                'is_active' => true,
            ]);
        }
        $mainStore = $stores[0];

        $user = User::firstOrCreate(['email' => 'bm_cashier@test.local'], [
            'tenant_id' => $tenant->id,
            'store_id' => $mainStore->id,
            'name' => 'Benchmark Cashier',
            'password' => Hash::make('password'),
            'role' => 'merchant',
            'pos_pin' => Hash::make('9999'),
        ]);

        auth()->login($user);

        if ($this->option('seed') || $this->option('seed-only')) {
            $this->seedBenchmarkData($tenant, $stores);
        }

        if ($this->option('seed-only')) {
            $this->info("\n[OK] Seeding complete. Exiting benchmark.");
            return 0;
        }

        $this->runPerformanceTelemetry($tenant, $mainStore, $user);
        return 0;
    }

    private function seedBenchmarkData($tenant, array $stores)
    {
        $this->info("\n📦 Checking & Bulk-Seeding Scale Dataset...");

        // 1. Seed 20,000 Products
        $currentProducts = Product::where('tenant_id', $tenant->id)->count();
        if ($currentProducts < 20000) {
            $targetProducts = 20000 - $currentProducts;
            $this->info("--> Seeding 20,000 products (current: {$currentProducts}, remaining: {$targetProducts})...");
            $batchSize = 2000;
            
            for ($i = 0; $i < $targetProducts; $i += $batchSize) {
                DB::transaction(function () use ($tenant, $stores, $currentProducts, $i, $batchSize, $targetProducts) {
                    $productRows = [];
                    $count = min($batchSize, $targetProducts - $i);
                    for ($j = 1; $j <= $count; $j++) {
                        $idx = $currentProducts + $i + $j;
                        $productRows[] = [
                            'tenant_id' => $tenant->id,
                            'name' => "Scale Product {$idx}",
                            'sku' => "SKU-BM-{$idx}-" . uniqid(),
                            'barcode' => 'BM' . sprintf('%010d', $idx),
                            'purchase_cost' => 80.00,
                            'selling_price' => 100.00,
                            'vat_rate' => 15.00,
                            'vat_mode' => 'exclusive',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    foreach (array_chunk($productRows, 1000) as $chunk) {
                        DB::table('products')->insert($chunk);
                    }

                    // Fetch inserted product IDs for stock seeding across the 3 stores
                    $insertedProducts = Product::where('tenant_id', $tenant->id)
                        ->orderBy('id', 'desc')
                        ->limit($count)
                        ->pluck('id');

                    $stockRows = [];
                    foreach ($insertedProducts as $pid) {
                        $stockRows[] = [
                            'tenant_id' => $tenant->id,
                            'store_id' => $stores[array_rand($stores)]->id,
                            'product_id' => $pid,
                            'quantity' => 1000.0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    foreach (array_chunk($stockRows, 1000) as $chunk) {
                        DB::table('stocks')->insert($chunk);
                    }
                });
            }
            $this->info("✓ 20,000 Products and Stock records seeded.");
        }

        // 2. Seed 5,000 Customers
        $currentCustomers = Customer::where('tenant_id', $tenant->id)->count();
        if ($currentCustomers < 5000) {
            $targetCustomers = 5000 - $currentCustomers;
            $this->info("--> Seeding 5,000 customers (current: {$currentCustomers}, remaining: {$targetCustomers})...");
            $batchSize = 1000;
            for ($i = 0; $i < $targetCustomers; $i += $batchSize) {
                DB::transaction(function () use ($tenant, $currentCustomers, $i, $batchSize, $targetCustomers) {
                    $customerRows = [];
                    $count = min($batchSize, $targetCustomers - $i);
                    for ($j = 1; $j <= $count; $j++) {
                        $idx = $currentCustomers + $i + $j;
                        $customerRows[] = [
                            'tenant_id' => $tenant->id,
                            'name' => "Scale Customer {$idx}",
                            'phone' => sprintf('018%08d', $idx),
                            'email' => "cust{$idx}@bm.test",
                            'due_balance' => 0.00,
                            'points' => 10,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    foreach (array_chunk($customerRows, 1000) as $chunk) {
                        DB::table('customers')->insert($chunk);
                    }
                });
            }
            $this->info("✓ 5,000 Customers seeded.");
        }

        // 3. Seed 500,000 Orders, OrderItems, OrderPayments
        $currentOrders = Order::where('tenant_id', $tenant->id)->count();
        if ($currentOrders < 500000) {
            $targetOrders = 500000 - $currentOrders;
            $this->info("--> Seeding 500,000 orders (current: {$currentOrders}, remaining: {$targetOrders})...");

            $sampleProducts = Product::where('tenant_id', $tenant->id)->limit(100)->pluck('id')->toArray();
            $sampleCustomers = Customer::where('tenant_id', $tenant->id)->limit(100)->pluck('id')->toArray();
            $paymentMethods = ['cash', 'card', 'bkash', 'nagad', 'rocket', 'upay'];

            $batchSize = 2500;
            $startDate = now()->subDays(365);

            for ($i = 0; $i < $targetOrders; $i += $batchSize) {
                DB::transaction(function () use ($tenant, $stores, $sampleProducts, $sampleCustomers, $paymentMethods, $i, $batchSize, $targetOrders, $startDate) {
                    $orderRows = [];
                    $count = min($batchSize, $targetOrders - $i);

                    for ($j = 1; $j <= $count; $j++) {
                        $randomDays = rand(0, 365);
                        $randomSeconds = rand(0, 86400);
                        $createdAt = (clone $startDate)->addDays($randomDays)->addSeconds($randomSeconds);
                        $method = $paymentMethods[array_rand($paymentMethods)];
                        $storeId = $stores[array_rand($stores)]->id;
                        $customerId = $sampleCustomers[array_rand($sampleCustomers)];

                        $orderRows[] = [
                            'tenant_id' => $tenant->id,
                            'store_id' => $storeId,
                            'customer_id' => $customerId,
                            'user_id' => auth()->id() ?? 1,
                            'invoice_no' => 'INV-BM-' . sprintf('%07d', $i + $j),
                            'subtotal' => 300.00,
                            'discount_amount' => 0.00,
                            'tax_amount' => 45.00,
                            'grand_total' => 345.00,
                            'cogs' => 240.00,
                            'paid_amount' => 345.00,
                            'change_return' => 0.00,
                            'payment_status' => 'paid',
                            'payment_method' => $method,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ];
                    }

                    foreach (array_chunk($orderRows, 500) as $chunk) {
                        DB::table('orders')->insert($chunk);
                    }

                    $insertedOrders = Order::where('tenant_id', $tenant->id)
                        ->orderBy('id', 'desc')
                        ->limit($count)
                        ->get();

                    $itemRows = [];
                    $paymentRows = [];

                    foreach ($insertedOrders as $ord) {
                        for ($k = 1; $k <= 3; $k++) {
                            $pid = $sampleProducts[array_rand($sampleProducts)];
                            $itemRows[] = [
                                'tenant_id' => $tenant->id,
                                'order_id' => $ord->id,
                                'product_id' => $pid,
                                'product_name' => "Scale Product {$pid}",
                                'serial_number' => null,
                                'quantity' => 1.0,
                                'unit_price' => 100.00,
                                'cost_price' => 80.00,
                                'discount' => 0.00,
                                'vat_rate' => 15.00,
                                'vat_amount' => 15.00,
                                'total' => 115.00,
                                'created_at' => $ord->created_at,
                                'updated_at' => $ord->created_at,
                            ];
                        }

                        $paymentRows[] = [
                            'tenant_id' => $tenant->id,
                            'order_id' => $ord->id,
                            'payment_method' => $ord->payment_method,
                            'amount' => $ord->grand_total,
                            'reference_no' => 'REF-SEED-' . uniqid(),
                            'created_at' => $ord->created_at,
                            'updated_at' => $ord->created_at,
                        ];
                    }

                    foreach (array_chunk($itemRows, 1000) as $chunk) {
                        DB::table('order_items')->insert($chunk);
                    }
                    foreach (array_chunk($paymentRows, 1000) as $chunk) {
                        DB::table('order_payments')->insert($chunk);
                    }
                });

                if (($i + $batchSize) % 50000 === 0 || ($i + $batchSize) >= $targetOrders) {
                    $this->info(sprintf("  ... Progress: %d / %d orders inserted.", min($i + $batchSize, $targetOrders), $targetOrders));
                }
            }
            $this->info("✓ 500,000 Orders, 1,500,000 OrderItems & OrderPayments seeded across 3 stores.");
        }

        // Backfill Daily Sales Summary Table
        $this->call('pos:backfill-summary');
    }

    private function runPerformanceTelemetry($tenant, $mainStore, $user)
    {
        $dbDriver = DB::connection()->getDriverName();
        $dbVersion = 'Unknown';
        try {
            $pdo = DB::connection()->getPdo();
            $dbVersion = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) ?? 'Unknown';
        } catch (\Throwable $e) {
            $dbVersion = 'N/A';
        }

        // InnoDB Buffer Pool Telemetry
        $innodbBufferPoolSetting = "N/A";
        try {
            $res = DB::select("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
            if (!empty($res)) {
                $bytes = (float) $res[0]->Value;
                $gb = $bytes / (1024 * 1024 * 1024);
                $innodbBufferPoolSetting = sprintf("%s bytes (%.2f GB)", number_format($bytes), $gb);
            }
        } catch (\Throwable $e) {
            $innodbBufferPoolSetting = "Unable to query SHOW VARIABLES";
        }

        $tenantCount = Tenant::count();
        $storeCount = Store::count();
        $prodCount = Product::count();
        $custCount = Customer::count();
        $orderCount = Order::count();
        $orderItemCount = OrderItem::count();
        $paymentCount = OrderPayment::count();
        $summaryCount = DailySalesSummary::count();

        $this->info("\n==========================================================================================");
        $this->info(" 📊 REAL BENCHMARK ROW COUNTS & ENGINE TELEMETRY");
        $this->info(sprintf(" Engine: %s (v%s) | InnoDB Buffer Pool: %s", strtoupper($dbDriver), $dbVersion, $innodbBufferPoolSetting));
        $this->info(sprintf(" Target: < 2.0s COLD Max Duration, < 256 MB Peak Memory (Evaluated on COLD runs)"));
        $this->info(sprintf(" Tenants: %s | Stores: %s | Products: %s | Customers: %s",
            number_format($tenantCount), number_format($storeCount), number_format($prodCount), number_format($custCount)
        ));
        $this->info(sprintf(" Orders: %s | OrderItems: %s | OrderPayments: %s | Daily Summaries: %s",
            number_format($orderCount), number_format($orderItemCount), number_format($paymentCount), number_format($summaryCount)
        ));
        $this->info("==========================================================================================\n");

        $posController = new \App\Http\Controllers\PosController();
        $reportController = new \App\Http\Controllers\ReportController();
        $merchantController = new \App\Http\Controllers\MerchantController();

        RegisterShift::firstOrCreate([
            'tenant_id' => $tenant->id,
            'store_id' => $mainStore->id,
            'user_id' => $user->id,
            'status' => 'open',
        ], ['opening_float' => 1000.00, 'opened_at' => now()]);

        $sampleProduct = Product::where('tenant_id', $tenant->id)->first() ?? Product::create([
            'tenant_id' => $tenant->id, 'sku' => 'SKU-BM-SAMPLE', 'name' => 'Sample Prod', 'selling_price' => 100.00, 'purchase_cost' => 80.00, 'is_active' => true
        ]);
        Stock::updateOrCreate(['tenant_id' => $tenant->id, 'store_id' => $mainStore->id, 'product_id' => $sampleProduct->id], ['quantity' => 1000000.00]);

        DB::enableQueryLog();

        $endpoints = [
            '1. POS Load (/pos)' => function() use ($posController, $mainStore, $user) {
                $req = Request::create('/pos', 'GET', ['store_id' => $mainStore->id]);
                $req->setUserResolver(fn() => $user);
                $res = $posController->index($req);
                return $res instanceof \Inertia\Response ? $res->toResponse($req) : $res;
            },
            '2. Product Search (/pos/products/search)' => function() use ($posController, $mainStore, $user) {
                $req = Request::create('/pos/products/search', 'GET', ['q' => 'Scale Product 100', 'store_id' => $mainStore->id]);
                $req->setUserResolver(fn() => $user);
                $res = $posController->searchProducts($req);
                return $res instanceof \Inertia\Response ? $res->toResponse($req) : $res;
            },
            '3. Customer Search (/pos/customers/search)' => function() use ($posController, $user) {
                $req = Request::create('/pos/customers/search', 'GET', ['q' => 'Customer 100']);
                $req->setUserResolver(fn() => $user);
                $res = $posController->searchCustomers($req);
                return $res instanceof \Inertia\Response ? $res->toResponse($req) : $res;
            },
            '4. Sales Checkout (/pos/checkout)' => function() use ($posController, $mainStore, $sampleProduct, $user) {
                $req = Request::create('/pos/checkout', 'POST', [
                    'client_uuid' => (string) Str::uuid(),
                    'store_id' => $mainStore->id,
                    'idempotency_key' => (string) Str::uuid(),
                    'items' => [['product_id' => $sampleProduct->id, 'quantity' => 1]],
                    'paid_amount' => 115.00,
                    'payment_method' => 'cash',
                ]);
                $req->headers->set('Accept', 'application/json');
                $req->setUserResolver(fn() => $user);
                $res = $posController->checkout($req);
                return $res instanceof \Inertia\Response ? $res->toResponse($req) : $res;
            },
            '5. Dashboard (/merchant/dashboard)' => function() use ($merchantController, $user) {
                $req = Request::create('/merchant/dashboard', 'GET');
                $req->setUserResolver(fn() => $user);
                $res = $merchantController->dashboard($req);
                return $res instanceof \Inertia\Response ? $res->toResponse($req) : $res;
            },
            '6. Sales Report (/merchant/orders)' => function() use ($merchantController, $user) {
                $req = Request::create('/merchant/orders', 'GET');
                $req->setUserResolver(fn() => $user);
                $res = $merchantController->ordersIndex();
                return $res instanceof \Inertia\Response ? $res->toResponse($req) : $res;
            },
            '7. VAT Report (/reports/vat)' => function() use ($reportController, $user) {
                $req = Request::create('/reports/vat', 'GET', ['start_date' => date('Y-01-01'), 'end_date' => date('Y-m-d')]);
                $req->setUserResolver(fn() => $user);
                $res = $reportController->vatReport($req);
                return $res instanceof \Inertia\Response ? $res->toResponse($req) : $res;
            },
            '8. Profit & Loss (/reports/profit-loss)' => function() use ($reportController, $user) {
                $req = Request::create('/reports/profit-loss', 'GET', ['start_date' => date('Y-01-01'), 'end_date' => date('Y-m-d')]);
                $req->setUserResolver(fn() => $user);
                $res = $reportController->profitLoss($req);
                return $res instanceof \Inertia\Response ? $res->toResponse($req) : $res;
            },
            '9. Stock Report (/reports/stock)' => function() use ($reportController, $user) {
                $req = Request::create('/reports/stock', 'GET');
                $req->setUserResolver(fn() => $user);
                $res = $reportController->stockReport($req);
                return $res instanceof \Inertia\Response ? $res->toResponse($req) : $res;
            },
            '10. CSV Export 30-Day Stream' => function() use ($posController, $user) {
                $req = Request::create('/reports/sales/export-csv', 'GET', [
                    'start_date' => date('Y-m-d', strtotime('-30 days')),
                    'end_date' => date('Y-m-d'),
                ]);
                $req->setUserResolver(fn() => $user);
                ob_start();
                $res = $posController->exportSalesCsv($req);
                if ($res instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
                    $res->sendContent();
                }
                $streamContent = ob_get_clean();
                return ['content' => $streamContent, 'response' => $res];
            },
            '11. CSV Export 366-Day Stream (Full Scale)' => function() use ($posController, $user) {
                $req = Request::create('/reports/sales/export-csv', 'GET', [
                    'start_date' => date('Y-m-d', strtotime('-366 days')),
                    'end_date' => date('Y-m-d'),
                ]);
                $req->setUserResolver(fn() => $user);
                ob_start();
                $res = $posController->exportSalesCsv($req);
                if ($res instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
                    $res->sendContent();
                }
                $streamContent = ob_get_clean();
                return ['content' => $streamContent, 'response' => $res];
            },
        ];

        $overallPass = true;

        $this->info(sprintf("%-43s | %-16s | %-16s | %-9s | %-6s", "Endpoint Operation", "COLD (Min/Med/Max)", "WARM (Min/Med/Max)", "Peak RAM", "Status"));
        $this->info(str_repeat("-", 108));

        foreach ($endpoints as $label => $callback) {
            $hadError = false;
            $errorMessage = '';
            $isCsvStream = str_contains($label, 'CSV Export');
            $numRuns = $isCsvStream ? 1 : 5;

            // COLD Runs with Cache::flush() before EACH run
            $coldTimings = [];
            for ($run = 1; $run <= $numRuns; $run++) {
                Cache::flush();
                $start = microtime(true);
                try {
                    $res = $callback();
                    if (is_array($res) && isset($res['content'])) {
                        $streamData = $res['content'];
                        if (strlen($streamData) === 0) {
                            $hadError = true; $errorMessage = "Empty CSV Stream";
                        }
                    } else if (!($res instanceof \Symfony\Component\HttpFoundation\Response)) {
                        $hadError = true; $errorMessage = "Invalid Response";
                    } else if ($res->getStatusCode() !== 200) {
                        $hadError = true; $errorMessage = "HTTP " . $res->getStatusCode();
                    }
                } catch (\Throwable $e) {
                    $hadError = true;
                    $errorMessage = substr($e->getMessage(), 0, 40);
                }
                $coldTimings[] = microtime(true) - $start;
            }

            // WARM Runs (Without Cache::flush())
            $warmTimings = [];
            for ($run = 1; $run <= $numRuns; $run++) {
                $start = microtime(true);
                try {
                    $callback();
                } catch (\Throwable $e) {}
                $warmTimings[] = microtime(true) - $start;
            }

            sort($coldTimings);
            sort($warmTimings);

            $coldMin = $coldTimings[0];
            $coldMed = $coldTimings[intdiv(count($coldTimings), 2)];
            $coldMax = end($coldTimings);

            $warmMin = $warmTimings[0];
            $warmMed = $warmTimings[intdiv(count($warmTimings), 2)];
            $warmMax = end($warmTimings);

            $peakMemMb = memory_get_peak_usage(true) / 1024 / 1024;

            // Strict Pass Criteria for Pages: COLD Max <= 2.0s AND Peak Memory <= 256.0 MB
            // Bulk CSV streams pass if no errors occur and memory <= 256.0 MB
            $pass = $isCsvStream 
                ? (!$hadError && $peakMemMb <= 256.0)
                : (!$hadError && $coldMax <= 2.0 && $peakMemMb <= 256.0);

            if (!$pass) {
                $overallPass = false;
            }

            $statusStr = $pass ? "<fg=green>PASS</>" : "<fg=red;options=bold>FAIL</>";
            if ($hadError) {
                $statusStr .= " <fg=red>({$errorMessage})</>";
            }

            $coldStr = sprintf("%.3fs/%.3fs/%.3fs", $coldMin, $coldMed, $coldMax);
            $warmStr = sprintf("%.3fs/%.3fs/%.3fs", $warmMin, $warmMed, $warmMax);

            $this->line(sprintf("%-43s | %-16s | %-16s | %7.2f MB | %s",
                $label, $coldStr, $warmStr, $peakMemMb, $statusStr
            ));
        }

        $this->info(str_repeat("-", 108));
        $this->info(sprintf("OVERALL BENCHMARK VERDICT (EVALUATED ON COLD RUNS): %s", $overallPass ? "<fg=green;options=bold>PASS</>" : "<fg=red;options=bold>FAIL</>"));
        $this->info("==========================================================================================\n");

        $this->analyzeSlowQueries();
    }

    private function analyzeSlowQueries()
    {
        $queries = DB::getQueryLog();
        if (empty($queries)) {
            return;
        }

        usort($queries, fn($a, $b) => $b['time'] <=> $a['time']);
        $slowest = array_slice($queries, 0, 5);

        $this->info("==========================================================================================");
        $this->info(" 🔍 EXPLAIN & EXPLAIN ANALYZE ON TOP 5 SLOWEST QUERIES (MySQL 8.4)");
        $this->info("==========================================================================================");

        $driver = DB::connection()->getDriverName();

        foreach ($slowest as $idx => $q) {
            $num = $idx + 1;
            $timeMs = $q['time'];
            $sql = $q['query'];
            $bindings = $q['bindings'];

            $this->info(sprintf("\n[Slow Query #%d] Execution Time: %.2f ms", $num, $timeMs));
            $this->line("SQL: " . $sql);

            try {
                $explainSql = ($driver === 'sqlite' ? "EXPLAIN QUERY PLAN " : "EXPLAIN ") . $sql;
                $explainResults = DB::select($explainSql, $bindings);

                $fullTableScan = false;
                $missingIndex = false;

                if ($driver === 'sqlite') {
                    $tableHeaders = array_keys((array)($explainResults[0] ?? ['id' => 1, 'detail' => '']));
                    $tableRows = array_map(fn($r) => (array)$r, $explainResults);
                    $this->table($tableHeaders, $tableRows);
                } else {
                    foreach ($explainResults as $row) {
                        $rowArr = (array) $row;
                        $type = $rowArr['type'] ?? $rowArr['select_type'] ?? '';
                        $possibleKeys = $rowArr['possible_keys'] ?? null;
                        $key = $rowArr['key'] ?? null;

                        if (strtoupper($type) === 'ALL') {
                            $fullTableScan = true;
                        }
                        if (is_null($possibleKeys) || is_null($key)) {
                            $missingIndex = true;
                        }
                    }

                    $this->table(['id', 'select_type', 'table', 'type', 'possible_keys', 'key', 'rows', 'Extra'], array_map(function($r) {
                        $arr = (array) $r;
                        return [
                            $arr['id'] ?? '1',
                            $arr['select_type'] ?? '',
                            $arr['table'] ?? '',
                            $arr['type'] ?? '',
                            $arr['possible_keys'] ?? 'NULL',
                            $arr['key'] ?? 'NULL',
                            $arr['rows'] ?? '',
                            $arr['Extra'] ?? '',
                        ];
                    }, $explainResults));

                    // Execute EXPLAIN ANALYZE on MySQL 8.4+
                    try {
                        $analyzeResults = DB::select("EXPLAIN ANALYZE " . $sql, $bindings);
                        $this->line("EXPLAIN ANALYZE Execution Tree:");
                        foreach ($analyzeResults as $aRow) {
                            $aArr = (array) $aRow;
                            $tree = $aArr['EXPLAIN'] ?? reset($aArr);
                            $this->line("  " . $tree);
                        }
                    } catch (\Throwable $e) {
                        $this->warn("  (EXPLAIN ANALYZE note: " . $e->getMessage() . ")");
                    }
                }

                if ($fullTableScan) {
                    $this->error(" ⚠️ DETECTED WARNING: FULL TABLE SCAN DETECTED");
                }
                if ($missingIndex && $driver !== 'sqlite') {
                    $this->warn(" ⚠️ DETECTED WARNING: MISSING INDEX OR UNINDEXED COLUMN");
                }
                if (!$fullTableScan && !$missingIndex) {
                    $this->info(" ✓ EXPLAIN Status: Indexes utilized effectively.");
                }

            } catch (\Throwable $e) {
                $this->warn("  (Could not run EXPLAIN on this statement: " . $e->getMessage() . ")");
            }
        }

        $this->info("\n==========================================================================================\n");
    }
}
