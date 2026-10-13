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
        if (Schema::hasTable('subscription_payments')) {
            try {
                Schema::table('subscription_payments', function (Blueprint $table) {
                    $table->unique('reference');
                });
            } catch (\Throwable $e) {
                // Unique index already exists on fresh installations
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subscription_payments')) {
            Schema::table('subscription_payments', function (Blueprint $table) {
                $table->dropUnique(['reference']);
                $table->index('reference');
            });
        }
    }
};
