<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indeks untuk kolom filter/sort di halaman list & dashboard.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['payment_status', 'created_at']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('reparations', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
            $table->index('process_deadline_at');
            $table->index('check_deadline_at');
        });

        Schema::table('cash_flows', function (Blueprint $table) {
            $table->index(['type', 'flow_date']);
            $table->index('flow_date');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index('reference');
            $table->index('movement_date');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['payment_status', 'created_at']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('reparations', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['process_deadline_at']);
            $table->dropIndex(['check_deadline_at']);
        });

        Schema::table('cash_flows', function (Blueprint $table) {
            $table->dropIndex(['type', 'flow_date']);
            $table->dropIndex(['flow_date']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['reference']);
            $table->dropIndex(['movement_date']);
        });
    }
};
