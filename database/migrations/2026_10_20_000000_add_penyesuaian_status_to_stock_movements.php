<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mutasi tipe penyesuaian punya status tersendiri: PENYESUAIAN.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('status', ['PEMBELIAN', 'TERJUAL', 'BONUS', 'HUTANG', 'REFUND', 'PENYESUAIAN'])->change();
        });

        DB::table('stock_movements')
            ->where('type', 'adjustment')
            ->update(['status' => 'PENYESUAIAN']);
    }

    public function down(): void
    {
        DB::table('stock_movements')
            ->where('status', 'PENYESUAIAN')
            ->update(['status' => 'PEMBELIAN']);

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('status', ['PEMBELIAN', 'TERJUAL', 'BONUS', 'HUTANG', 'REFUND'])->change();
        });
    }
};
