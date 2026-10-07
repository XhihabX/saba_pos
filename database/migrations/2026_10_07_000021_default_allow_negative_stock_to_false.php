<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('stores') && Schema::hasColumn('stores', 'allow_negative_stock')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->boolean('allow_negative_stock')->default(false)->change();
            });

            DB::table('stores')->update(['allow_negative_stock' => false]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stores') && Schema::hasColumn('stores', 'allow_negative_stock')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->boolean('allow_negative_stock')->default(true)->change();
            });
        }
    }
};
