<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reparation_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reparation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->enum('status', ['PENDING', 'CONFIRMED', 'REJECTED', 'CANCELLED'])->default('PENDING');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reparation_stages');
    }
};
