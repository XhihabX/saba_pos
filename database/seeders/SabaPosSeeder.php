<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RegisterShift;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SabaPosSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create/Update Default SaaS Tenant
        $tenant = Tenant::withTrashed()->updateOrCreate(
            ['code' => 'TENANT-001'],
            [
                'name' => 'IOT Enterprise Retail Chain',
                'email' => 'ceo@iotpos.com',
                'phone' => '+880 1711 000111',
                'plan_name' => 'Growth Multi-Store',
                'subscription_status' => 'active',
                'mrr_amount' => 3999.00,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            ]
        );
        if ($tenant->trashed()) {
            $tenant->restore();
        }

        // Pending Merchant Registration for Super Admin Approval Queue
        $tenantPending = Tenant::withTrashed()->updateOrCreate(
            ['code' => 'TENANT-002'],
            [
                'name' => 'Apex Footwear Outlet (Pending Approval)',
                'email' => 'apex@iotpos.com',
                'phone' => '+880 1799 888777',
                'plan_name' => 'Starter Single Outlet',
                'subscription_status' => 'pending_approval',
                'mrr_amount' => 1999.00,
                'payment_method' => 'bkash',
                'sender_number' => '01712345678',
                'transaction_id' => 'TRX89X72901',
                'expires_at' => null,
            ]
        );
        if ($tenantPending->trashed()) {
            $tenantPending->restore();
        }

        // 2. Create/Update Default Stores
        $mainStore = Store::withTrashed()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'STORE-001'],
            [
                'name' => 'IOT Flagship Outlet',
                'phone' => '+880 1700 000000',
                'email' => 'outlet1@iotpos.com',
                'address' => 'Level 4, Jamuna Future Park, Dhaka',
                'vat_number' => 'VAT-99201-BD',
                'currency_symbol' => '৳',
                'default_tax_rate' => 5.00,
                'receipt_header' => 'Welcome to IOT POS Retail Flagship!',
                'receipt_footer' => 'Thank you for shopping with us.',
                'is_active' => true,
            ]
        );
        if ($mainStore->trashed()) {
            $mainStore->restore();
        }

        $secondStore = Store::withTrashed()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'STORE-002'],
            [
                'name' => 'IOT Cafe & Bistro',
                'phone' => '+880 1800 111222',
                'email' => 'cafe@iotpos.com',
                'address' => 'Road 11, Banani, Dhaka',
                'vat_number' => 'VAT-88410-BD',
                'currency_symbol' => '৳',
                'default_tax_rate' => 7.50,
                'receipt_header' => 'IOT Cafe & Gourmet Bistro',
                'receipt_footer' => 'Taste the fresh brew!',
                'is_active' => true,
            ]
        );
        if ($secondStore->trashed()) {
            $secondStore->restore();
        }

        // 3. Create/Update Users for the 4 Portals
        // Portal 1: Super Admin
        User::updateOrCreate(
            ['email' => 'admin@iotpos.com'],
            [
                'name' => 'Super Admin Platform CEO',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
                'tenant_id' => null,
                'store_id' => null,
            ]
        );

        // Portal 2: Merchant CEO
        User::updateOrCreate(
            ['email' => 'merchant@iotpos.com'],
            [
                'name' => 'Merchant Business Owner',
                'password' => Hash::make('password123'),
                'role' => 'merchant',
                'tenant_id' => $tenant->id,
                'store_id' => $mainStore->id,
            ]
        );

        // Portal 3: Store Manager
        User::updateOrCreate(
            ['email' => 'manager@iotpos.com'],
            [
                'name' => 'Banani Branch Supervisor',
                'password' => Hash::make('password123'),
                'role' => 'store_manager',
                'tenant_id' => $tenant->id,
                'store_id' => $mainStore->id,
            ]
        );

        // Portal 4: Cashier Terminal User
        $cashier = User::updateOrCreate(
            ['email' => 'cashier@iotpos.com'],
            [
                'name' => 'Front-Desk Cashier',
                'password' => Hash::make('password123'),
                'role' => 'cashier',
                'tenant_id' => $tenant->id,
                'store_id' => $mainStore->id,
            ]
        );

        // 4. Units & Categories
        $pc = Unit::firstOrCreate(['name' => 'Piece'], ['short_name' => 'Pc']);
        $kg = Unit::firstOrCreate(['name' => 'Kilogram'], ['short_name' => 'Kg']);
        $box = Unit::firstOrCreate(['name' => 'Box'], ['short_name' => 'Box']);

        $catElectronics = Category::firstOrCreate(['tenant_id' => $tenant->id, 'slug' => 'electronics'], ['name' => 'Electronics & Gadgets']);
        $catFashion = Category::firstOrCreate(['tenant_id' => $tenant->id, 'slug' => 'fashion'], ['name' => 'Fashion & Apparel']);
        $catFood = Category::firstOrCreate(['tenant_id' => $tenant->id, 'slug' => 'food'], ['name' => 'Food & Beverages']);
        $catGrocery = Category::firstOrCreate(['tenant_id' => $tenant->id, 'slug' => 'grocery'], ['name' => 'Grocery & Daily Items']);

        $brandApple = Brand::firstOrCreate(['name' => 'Apple']);
        $brandNike = Brand::firstOrCreate(['name' => 'Nike']);

        // 5. Products & Initial Stock
        $products = [
            [
                'name' => 'iPhone 15 Pro Max 256GB',
                'sku' => 'IP15-PRO-256',
                'barcode' => '880123456789',
                'category_id' => $catElectronics->id,
                'brand_id' => $brandApple->id,
                'unit_id' => $pc->id,
                'purchase_cost' => 135000.00,
                'selling_price' => 149999.00,
                'alert_quantity' => 2,
                'has_serial' => true,
                'stock' => 15,
            ],
            [
                'name' => 'AirPods Pro (2nd Gen)',
                'sku' => 'APP-2GEN',
                'barcode' => '880123456790',
                'category_id' => $catElectronics->id,
                'brand_id' => $brandApple->id,
                'unit_id' => $pc->id,
                'purchase_cost' => 24000.00,
                'selling_price' => 28500.00,
                'alert_quantity' => 5,
                'has_serial' => false,
                'stock' => 25,
            ],
            [
                'name' => 'Nike Air Force 1 "07 Triple White',
                'sku' => 'NK-AF1-WHT',
                'barcode' => '880123456791',
                'category_id' => $catFashion->id,
                'brand_id' => $brandNike->id,
                'unit_id' => $pc->id,
                'purchase_cost' => 9500.00,
                'selling_price' => 12500.00,
                'alert_quantity' => 4,
                'has_serial' => false,
                'stock' => 18,
            ],
        ];

        foreach ($products as $p) {
            $stockQty = $p['stock'];
            unset($p['stock']);

            $p['tenant_id'] = $tenant->id;
            $product = Product::withTrashed()->updateOrCreate(['tenant_id' => $tenant->id, 'sku' => $p['sku']], $p);
            if ($product->trashed()) {
                $product->restore();
            }

            Stock::updateOrCreate(
                ['store_id' => $mainStore->id, 'product_id' => $product->id],
                ['quantity' => $stockQty]
            );
        }

        // 6. Active Cashier Shift Seeding
        RegisterShift::firstOrCreate(
            ['tenant_id' => $tenant->id, 'store_id' => $mainStore->id, 'user_id' => $cashier->id, 'status' => 'open'],
            [
                'opening_cash' => 1000.00,
                'opened_at' => now(),
            ]
        );

        // 7. Customers
        $customer = Customer::withTrashed()->firstOrCreate(
            ['name' => 'Walk-in Customer'],
            [
                'phone' => '0000000000',
                'address' => 'Store Counter',
                'due_balance' => 0.00,
            ]
        );
        if ($customer->trashed()) {
            $customer->restore();
        }
    }
}
