<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reparation_stage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reparation_stage_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('cost', 100);
            $table->date('est_days')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reparation_stage_items');
    }
};
