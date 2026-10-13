<?php

namespace Database\Seeders;

use App\Models\SaaSPlan;
use Illuminate\Database\Seeder;

class SaaSPlanSeeder extends Seeder
{
    /**
     * Run the database seeds for SaaS subscription plan tiers.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter POS',
                'code' => 'STARTER',
                'monthly_price' => 1499.00,
                'max_stores' => 1,
                'max_users' => 3,
                'features' => [
                    '1 Retail Outlet',
                    'Up to 3 Staff Cashiers',
                    'Offline POS Selling',
                    'Thermal Receipt Printing',
                    'Daily Sales Reporting',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Growth Multi-Store',
                'code' => 'GROWTH',
                'monthly_price' => 3999.00,
                'max_stores' => 5,
                'max_users' => 15,
                'features' => [
                    'Up to 5 Store Outlets',
                    'Unlimited Cashier Terminals',
                    'Inter-Branch Stock Transfers',
                    'Customer Due Ledgers & Loyalty',
                    'Profit & Loss Financial Analytics',
                    'Supervisor PIN Audits',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise ERP',
                'code' => 'ENTERPRISE',
                'monthly_price' => 9999.00,
                'max_stores' => 50,
                'max_users' => 100,
                'features' => [
                    'Unlimited Store Outlets',
                    'Dedicated Database Architecture',
                    'NBR Mushak 6.3 Tax Invoicing',
                    'Custom ERP Integrations',
                    '24/7 VIP Phone Support',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $planData) {
            SaaSPlan::updateOrCreate(
                ['code' => $planData['code']],
                $planData
            );
        }
    }
}
