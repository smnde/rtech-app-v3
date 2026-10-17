<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained()
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained()
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->string('buy_from_reference')->nullable();
            $table->string('invoice')->unique();
            $table->decimal('total', 15, 2)->default(0);
            $table->enum('payment_status', ['lunas', 'hutang'])->default('lunas');
            $table->enum('status', ['ordered', 'pending', 'done'])->default('done');
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('buy_from_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
