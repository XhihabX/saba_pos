<?php

namespace App\Console\Commands;

use App\Models\Customer;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BenchmarkPerformanceCommand extends Command
{
    protected $signature = 'pos:benchmark {--seed} {--seed-only} {--run-only} {--force}';
    protected $description = 'Seed scale test data (1 tenant, 3 stores, 20k products, 5k customers, 500k orders over 1 yr) and measure real page timing & EXPLAIN query telemetry';

    public function handle()
    {
        ini_set('memory_limit', '256M');

        $driver = DB::connection()->getDriverName();
        $dbName = DB::connection()->getDatabaseName();

        // 1. Safety Guard: Refuse to run if database is not named like *_benchmark or *_test unless --force is passed
        if (!$this->option('force')) {
            if ($driver !== 'mysql') {
                $this->error("\n[SAFETY ERROR] Benchmark must be executed on MySQL (current driver: {$driver}).");
                $this->error("Please configure DB_CONNECTION=mysql in your .env or run with --force.\n");
                return 1;
            }

            if (!preg_match('/(_benchmark|_test)$/i', $dbName)) {
                $this->error("\n[SAFETY ERROR] Database safety violation!");
                $this->error("Active database \"{$dbName}\" is not named like *_benchmark or *_test.");
                $this->error("Refusing to run benchmark to protect production and real databases!\n");
                return 1;
            }
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

        if ($this->option('seed') || (!$this->option('run-only') && Order::where('tenant_id', $tenant->id)->count() < 500000)) {
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
                        // Distribute stock rows across main store and sample stores
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
                            'name' => "Benchmark Customer {$idx}",
                            'phone' => sprintf('017%08d', $idx),
                            'due_balance' => 0.00,
                            'points' => rand(0, 500),
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

        // 3. Seed 500,000 Orders with 1,500,000 Order Items and Order Payments over 12 months across 3 Stores
        $currentOrders = Order::where('tenant_id', $tenant->id)->count();
        if ($currentOrders < 500000) {
            $targetOrders = 500000 - $currentOrders;
            $this->info("--> Seeding 500,000 orders + 1,500,000 items + payments spread over 12 months across 3 stores...");
            $batchSize = 2000;
            $sampleProducts = Product::where('tenant_id', $tenant->id)->limit(100)->pluck('id')->toArray();
            if (empty($sampleProducts)) {
                $sampleProducts = [1];
            }

            for ($i = 0; $i < $targetOrders; $i += $batchSize) {
                DB::transaction(function () use ($tenant, $stores, $currentOrders, $i, $batchSize, $targetOrders, $sampleProducts) {
                    $orderRows = [];
                    $count = min($batchSize, $targetOrders - $i);
                    $nowTs = time();
                    $oneYearSec = 365 * 86400;

                    for ($j = 1; $j <= $count; $j++) {
                        $idx = $currentOrders + $i + $j;
                        $randomStore = $stores[array_rand($stores)];
                        $randomDate = date('Y-m-d H:i:s', $nowTs - rand(0, $oneYearSec));

                        $orderRows[] = [
                            'tenant_id' => $tenant->id,
                            'store_id' => $randomStore->id,
                            'idempotency_key' => "IDEM-SEED-{$idx}-" . uniqid(),
                            'invoice_no' => "INV-BM-{$idx}-" . strtoupper(substr(uniqid(), -4)),
                            'subtotal' => 300.00,
                            'discount_amount' => 0.00,
                            'tax_amount' => 45.00,
                            'grand_total' => 345.00,
                            'paid_amount' => 345.00,
                            'change_return' => 0.00,
                            'payment_status' => 'paid',
                            'payment_method' => ($idx % 3 === 0) ? 'bkash' : (($idx % 3 === 1) ? 'card' : 'cash'),
                            'created_at' => $randomDate,
                            'updated_at' => $randomDate,
                        ];
                    }
                    foreach (array_chunk($orderRows, 1000) as $chunk) {
                        DB::table('orders')->insert($chunk);
                    }

                    // Retrieve inserted orders for matching order_items & order_payments
                    $insertedOrders = DB::table('orders')
                        ->where('tenant_id', $tenant->id)
                        ->orderBy('id', 'desc')
                        ->limit($count)
                        ->get(['id', 'payment_method', 'grand_total', 'created_at']);

                    $itemRows = [];
                    $paymentRows = [];

                    foreach ($insertedOrders as $ord) {
                        // 3 items per order = 1,500,000 order_items total
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

        $tenantCount = Tenant::count();
        $storeCount = Store::count();
        $prodCount = Product::count();
        $custCount = Customer::count();
        $orderCount = Order::count();
        $orderItemCount = OrderItem::count();
        $paymentCount = OrderPayment::count();
        $stockCount = Stock::count();

        $this->info("\n==========================================================================================");
        $this->info(" 📊 REAL BENCHMARK ROW COUNTS & ENGINE TELEMETRY");
        $this->info(sprintf(" Engine: %s (v%s) | Target: < 2.0s Max Duration, < 256 MB Peak Memory", strtoupper($dbDriver), $dbVersion));
        $this->info(sprintf(" Tenants: %s | Stores: %s | Products: %s | Customers: %s",
            number_format($tenantCount), number_format($storeCount), number_format($prodCount), number_format($custCount)
        ));
        $this->info(sprintf(" Orders: %s | OrderItems: %s | OrderPayments: %s | Stocks: %s",
            number_format($orderCount), number_format($orderItemCount), number_format($paymentCount), number_format($stockCount)
        ));
        $this->info("==========================================================================================\n");

        $posController = new \App\Http\Controllers\PosController();
        $reportController = new \App\Http\Controllers\ReportController();
        $merchantController = new \App\Http\Controllers\MerchantController();

        // Ensure active open shift for sales checkout endpoint
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

        // Enable query log to profile SQL statements for EXPLAIN analysis
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
                    'client_uuid' => 'IDEM-BENCH-' . uniqid(),
                    'store_id' => $mainStore->id,
                    'idempotency_key' => 'IDEM-BENCH-' . uniqid(),
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
            '10. CSV Export (/reports/sales/export-csv)' => function() use ($posController, $user) {
                $req = Request::create('/reports/sales/export-csv', 'GET');
                $req->setUserResolver(fn() => $user);
                $res = $posController->exportSalesCsv($req);
                return $res instanceof \Inertia\Response ? $res->toResponse($req) : $res;
            },
        ];

        $overallPass = true;

        $this->info(sprintf("%-45s | %-7s | %-7s | %-7s | %-11s | %-6s", "Endpoint Operation", "Min (s)", "Med (s)", "Max (s)", "Peak Memory", "Status"));
        $this->info(str_repeat("-", 100));

        foreach ($endpoints as $label => $callback) {
            $timings = [];
            $hadError = false;
            $errorMessage = '';

            for ($run = 1; $run <= 5; $run++) {
                $start = microtime(true);
                try {
                    $res = $callback();
                    if (!($res instanceof \Symfony\Component\HttpFoundation\Response)) {
                        $hadError = true;
                        $errorMessage = "Invalid Response";
                    } else {
                        $code = $res->getStatusCode();
                        if ($code !== 200) {
                            $hadError = true;
                            $errorMessage = "HTTP {$code}";
                        } else if ($res instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
                            // Streamed HTTP 200 response (CSV Export)
                        } else {
                            $content = $res->getContent();
                            if ($content === false || strlen(trim((string)$content)) === 0) {
                                $hadError = true;
                                $errorMessage = "Empty Body";
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $hadError = true;
                    $errorMessage = substr($e->getMessage(), 0, 45);
                }
                $timings[] = microtime(true) - $start;
            }

            sort($timings);
            $min = $timings[0];
            $median = $timings[2];
            $max = $timings[4];
            $peakMemMb = memory_get_peak_usage(true) / 1024 / 1024;

            $pass = (!$hadError && $max <= 2.0 && $peakMemMb <= 256.0);
            if (!$pass) {
                $overallPass = false;
            }

            $statusStr = $pass ? "<fg=green>PASS</>" : "<fg=red;options=bold>FAIL</>";
            if ($hadError) {
                $statusStr .= " <fg=red>({$errorMessage})</>";
            }

            $this->line(sprintf("%-45s | %6.3fs | %6.3fs | %6.3fs | %8.2f MB | %s",
                $label, $min, $median, $max, $peakMemMb, $statusStr
            ));
        }

        $this->info(str_repeat("-", 100));
        $this->info(sprintf("OVERALL BENCHMARK VERDICT: %s", $overallPass ? "<fg=green;options=bold>PASS</>" : "<fg=red;options=bold>FAIL</>"));
        $this->info("==========================================================================================\n");

        // 5. Query Profiling & EXPLAIN Analysis on Slowest 5 Queries
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
        $this->info(" 🔍 EXPLAIN ANALYSIS ON TOP 5 SLOWEST QUERIES");
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

                    foreach ($explainResults as $row) {
                        $detail = strtoupper(((array)$row)['detail'] ?? '');
                        if (str_contains($detail, 'SCAN TABLE')) {
                            $fullTableScan = true;
                        }
                    }
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
