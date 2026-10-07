<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('stores') && Schema::hasColumn('stores', 'default_tax_rate')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->decimal('default_tax_rate', 5, 2)->default(15.00)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stores') && Schema::hasColumn('stores', 'default_tax_rate')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->decimal('default_tax_rate', 5, 2)->default(5.00)->change();
            });
        }
    }
};
