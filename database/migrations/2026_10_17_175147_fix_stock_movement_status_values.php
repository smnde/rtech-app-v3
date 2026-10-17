<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalisasi status lama → nilai yang dipakai recorder & validasi.
     */
    public function up(): void
    {
        DB::table('stock_movements')->update([
            'status' => DB::raw(
                'CASE status'
                ." WHEN 'PURHCASE' THEN 'PEMBELIAN'"
                ." WHEN 'SOLD OUT' THEN 'TERJUAL'"
                ." WHEN 'DEBT' THEN 'HUTANG'"
                .' ELSE status END'
            ),
        ]);
    }

    public function down(): void
    {
        DB::table('stock_movements')->update([
            'status' => DB::raw(
                'CASE status'
                ." WHEN 'PEMBELIAN' THEN 'PURHCASE'"
                ." WHEN 'TERJUAL' THEN 'SOLD OUT'"
                ." WHEN 'HUTANG' THEN 'DEBT'"
                .' ELSE status END'
            ),
        ]);
    }
};
