<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('type', ['nominal', 'percent'])->default('nominal');

            // nominal: potongan per unit (Rp). percent: persen dari harga per unit.
            $table->decimal('value', 15, 2)->default(0);

            // Rentang berlaku promo.
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('is_active')->default(true);

            // Jenis barang yang berhak, mis. ['laptop', 'gadget'].
            $table->json('item_types');
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};
