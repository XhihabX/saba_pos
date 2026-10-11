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
        // 1. Add grace_days and allow_suspended_read_only to tenants
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'grace_days')) {
                $table->unsignedInteger('grace_days')->default(5)->after('expires_at');
            }
            if (! Schema::hasColumn('tenants', 'allow_suspended_read_only')) {
                $table->boolean('allow_suspended_read_only')->default(false)->after('grace_days');
            }
        });

        // 2. Add late_synced flag to orders table
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'late_synced')) {
                $table->boolean('late_synced')->default(false)->after('notes');
            }
        });

        // 3. Create idempotent subscription_reminders table
        if (! Schema::hasTable('subscription_reminders')) {
            Schema::create('subscription_reminders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->timestamp('expires_at');
                $table->string('reminder_type', 32); // '7_days', '3_days', '1_day', '0_days'
                $table->timestamp('sent_at');
                $table->timestamps();

                $table->unique(['tenant_id', 'expires_at', 'reminder_type'], 'sub_reminders_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_reminders');

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'late_synced')) {
                $table->dropColumn('late_synced');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'allow_suspended_read_only')) {
                $table->dropColumn('allow_suspended_read_only');
            }
            if (Schema::hasColumn('tenants', 'grace_days')) {
                $table->dropColumn('grace_days');
            }
        });
    }
};
