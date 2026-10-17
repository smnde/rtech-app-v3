<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->morphs('item');
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->onUpdate('cascade');

            $table->enum('type', ['in', 'out', 'adjustment']);
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->enum('status', ['PURHCASE', 'SOLD OUT', 'FREE', 'DEBT', 'REFUND']);
            $table->date('movement_date')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::table('stock_movements')->update(['movement_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
