<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CiPlaywrightSeeder extends Seeder
{
    /**
     * Run dedicated CI-only seeder for Playwright E2E browser tests.
     */
    public function run(): void
    {
        // 1. Provision CI Tenant
        $tenant = Tenant::updateOrCreate(
            ['code' => 'CI-TENANT-E2E'],
            [
                'name' => 'CI Playwright Enterprise Store',
                'email' => 'ci_tenant@iotpos.com',
                'subscription_status' => 'active',
                'expires_at' => now()->addYear(),
                'mrr_amount' => 4999.00,
            ]
        );

        // 2. Provision Outlet Store
        $store = Store::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'STORE-CI-01'],
            [
                'name' => 'CI Playwright Main Outlet',
                'currency_symbol' => '৳',
                'default_tax_rate' => 15.00,
                'bin_number' => '123456789-0101',
                'is_vat_registered' => true,
                'allow_negative_stock' => false,
                'is_active' => true,
            ]
        );

        // 3. Provision CI Cashier
        $cashier = User::updateOrCreate(
            ['email' => 'ci_cashier@iotpos.com'],
            [
                'tenant_id' => $tenant->id,
                'store_id' => $store->id,
                'name' => 'CI Cashier Bot',
                'password' => Hash::make('password123'),
                'pos_pin' => Hash::make('1234'),
                'role' => 'cashier',
            ]
        );

        // 4. Close any stale shifts and provision active register shift
        RegisterShift::where('tenant_id', $tenant->id)
            ->where('user_id', $cashier->id)
            ->where('status', 'open')
            ->update(['status' => 'closed', 'closed_at' => now()]);

        RegisterShift::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $cashier->id,
            'opening_cash' => 2000.00,
            'status' => 'open',
            'opened_at' => now(),
            'notes' => 'Pre-seeded CI Playwright active shift',
        ]);

        // 5. Category
        $category = Category::updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Hardware'],
            ['slug' => 'hardware', 'is_active' => true]
        );

        // 6. Test Products & Stocks
        $items = [
            [
                'name' => 'Playwright Laser Mouse',
                'sku' => 'PW-MOUSE-01',
                'barcode' => '890001',
                'purchase_cost' => 200.00,
                'selling_price' => 500.00,
                'stock' => 50,
            ],
            [
                'name' => 'Playwright Mechanical Keyboard',
                'sku' => 'PW-KEYBOARD-01',
                'barcode' => '890002',
                'purchase_cost' => 800.00,
                'selling_price' => 1500.00,
                'stock' => 50,
            ],
            [
                'name' => 'Playwright USB Hub 4-Port',
                'sku' => 'PW-HUB-01',
                'barcode' => '890003',
                'purchase_cost' => 150.00,
                'selling_price' => 350.00,
                'stock' => 50,
            ],
        ];

        foreach ($items as $item) {
            $product = Product::updateOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'barcode' => $item['barcode'],
                    'category_id' => $category->id,
                    'purchase_cost' => $item['purchase_cost'],
                    'selling_price' => $item['selling_price'],
                    'vat_rate' => 15.00,
                    'is_active' => true,
                ]
            );

            Stock::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'store_id' => $store->id,
                    'product_id' => $product->id,
                ],
                [
                    'quantity' => $item['stock'],
                ]
            );
        }
    }
}
