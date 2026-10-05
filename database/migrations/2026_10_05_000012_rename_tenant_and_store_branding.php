<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update Tenant names containing Saba to IOT Enterprise Retail Chain
        DB::table('tenants')
            ->where('name', 'LIKE', '%Saba%')
            ->update(['name' => 'IOT Enterprise Retail Chain']);

        // Update Store names containing Saba to IOT Flagship Outlet
        DB::table('stores')
            ->where('name', 'LIKE', '%Saba%')
            ->update([
                'name' => 'IOT Flagship Outlet',
                'receipt_header' => 'Welcome to IOT POS Retail Flagship!',
            ]);
    }

    public function down(): void
    {
        // No down migration needed
    }
};
