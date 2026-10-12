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
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('flagged_for_review')->default(false)->after('late_synced');
            $table->string('review_reason')->nullable()->after('flagged_for_review');
            $table->timestamp('resolved_at')->nullable()->after('review_reason');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete()->after('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['resolved_by']);
            $table->dropColumn(['flagged_for_review', 'review_reason', 'resolved_at', 'resolved_by']);
        });
    }
};
