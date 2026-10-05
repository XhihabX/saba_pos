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
        // 1. Create Default SaaS Tenant
        $tenant = Tenant::create([
            'name' => 'IOT Enterprise Retail Chain',
            'code' => 'TENANT-001',
            'email' => 'ceo@iotpos.com',
            'phone' => '+880 1711 000111',
            'plan_name' => 'Growth Multi-Store',
            'subscription_status' => 'active',
            'mrr_amount' => 3999.00,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        // Pending Merchant Registration for Super Admin Approval Queue
        Tenant::create([
            'name' => 'Apex Footwear Outlet (Pending Approval)',
            'code' => 'TENANT-002',
            'email' => 'apex@iotpos.com',
            'phone' => '+880 1799 888777',
            'plan_name' => 'Starter Single Outlet',
            'subscription_status' => 'pending_approval',
            'mrr_amount' => 1999.00,
            'payment_method' => 'bkash',
            'sender_number' => '01712345678',
            'transaction_id' => 'TRX89X72901',
            'expires_at' => null,
        ]);

        // 2. Create Default Stores
        $mainStore = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'IOT Flagship Outlet',
            'code' => 'STORE-001',
            'phone' => '+880 1700 000000',
            'email' => 'outlet1@iotpos.com',
            'address' => 'Level 4, Jamuna Future Park, Dhaka',
            'vat_number' => 'VAT-99201-BD',
            'currency_symbol' => '৳',
            'default_tax_rate' => 5.00,
            'receipt_header' => 'Welcome to IOT POS Retail Flagship!',
            'receipt_footer' => 'Thank you for shopping with us.',
            'is_active' => true,
        ]);

        $secondStore = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Saba Cafe & Bistro',
            'code' => 'STORE-002',
            'phone' => '+880 1800 111222',
            'email' => 'cafe@sabapos.com',
            'address' => 'Road 11, Banani, Dhaka',
            'vat_number' => 'VAT-88410-BD',
            'currency_symbol' => '৳',
            'default_tax_rate' => 7.50,
            'receipt_header' => 'Saba Cafe & Gourmet Bistro',
            'receipt_footer' => 'Taste the fresh brew!',
            'is_active' => true,
        ]);

        // 3. Create Users for the 4 Portals
        // Portal 1: Super Admin
        User::create([
            'name' => 'Super Admin Platform CEO',
            'email' => 'admin@sabapos.com',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'tenant_id' => null,
            'store_id' => null,
        ]);

        // Portal 2: Merchant CEO
        User::create([
            'name' => 'Merchant Business Owner',
            'email' => 'merchant@sabapos.com',
            'password' => Hash::make('password123'),
            'role' => 'merchant',
            'tenant_id' => $tenant->id,
            'store_id' => $mainStore->id,
        ]);

        // Portal 3: Store Manager
        User::create([
            'name' => 'Banani Branch Supervisor',
            'email' => 'manager@sabapos.com',
            'password' => Hash::make('password123'),
            'role' => 'store_manager',
            'tenant_id' => $tenant->id,
            'store_id' => $mainStore->id,
        ]);

        // Portal 4: Cashier Terminal User
        $cashier = User::create([
            'name' => 'Front-Desk Cashier',
            'email' => 'cashier@sabapos.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'tenant_id' => $tenant->id,
            'store_id' => $mainStore->id,
        ]);

        // 4. Units & Categories
        $pc = Unit::create(['name' => 'Piece', 'short_name' => 'Pc']);
        $kg = Unit::create(['name' => 'Kilogram', 'short_name' => 'Kg']);
        $box = Unit::create(['name' => 'Box', 'short_name' => 'Box']);

        $catElectronics = Category::create(['tenant_id' => $tenant->id, 'name' => 'Electronics & Gadgets', 'slug' => 'electronics']);
        $catFashion = Category::create(['tenant_id' => $tenant->id, 'name' => 'Fashion & Apparel', 'slug' => 'fashion']);
        $catFood = Category::create(['tenant_id' => $tenant->id, 'name' => 'Food & Beverages', 'slug' => 'food']);
        $catGrocery = Category::create(['tenant_id' => $tenant->id, 'name' => 'Grocery & Daily Items', 'slug' => 'grocery']);

        $brandApple = Brand::create(['name' => 'Apple']);
        $brandNike = Brand::create(['name' => 'Nike']);

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
            $product = Product::create($p);

            Stock::create([
                'store_id' => $mainStore->id,
                'product_id' => $product->id,
                'quantity' => $stockQty,
            ]);
        }

        // 6. Active Cashier Shift Seeding
        RegisterShift::create([
            'tenant_id' => $tenant->id,
            'store_id' => $mainStore->id,
            'user_id' => $cashier->id,
            'opening_cash' => 1000.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        // 7. Customers
        Customer::create([
            'name' => 'Walk-in Customer',
            'phone' => '0000000000',
            'address' => 'Store Counter',
            'due_balance' => 0.00,
        ]);
    }
}
