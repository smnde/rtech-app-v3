<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->onUpdate('cascade');

            // 'in' = uang masuk, 'out' = uang keluar
            $table->enum('type', ['in', 'out']);

            // SALDO_AWAL, SETOR, TARIK, KOREKSI, LAIN
            $table->string('category');

            $table->string('description');
            $table->decimal('amount', 15, 2);

            // Tanggal efektif mutasi (bisa berbeda dengan created_at)
            $table->date('flow_date');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flows');
    }
};
