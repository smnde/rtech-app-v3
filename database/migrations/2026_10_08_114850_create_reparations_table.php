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
        Schema::create('reparations', function (Blueprint $table) {
            $table->id();
            $table->string('invoice')->unique();
            $table->foreignId('customer_id')
                ->constrained()
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->string('item_name')->index();
            $table->json('flag'); // HW, SW, MB
            $table->json('complaint');
            $table->json('accessories')->nullable();
            $table->json('condition')->nullable();
            $table->string('estimated_cost');
            $table->dateTime('check_deadline_at')->nullable();
            $table->dateTime('process_deadline_at')->nullable();
            $table->enum('status', [
                'RECEIVED',
                'CHECKED',
                'CONFIRMED',
                'WAITING CONFIRMATION',
                'ON PROCESS',
                'PENDING',
                'CANCEL',
                'DONE',
                'TAKEN'
            ]);

            $table->text('technician_notes');
            $table->text('additional_notes');
            $table->decimal('total_price', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reparations');
    }
};
