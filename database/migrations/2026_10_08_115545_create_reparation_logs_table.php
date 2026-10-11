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
        Schema::create('reparation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reparation_id')
                ->constrained()
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->string('type');
            $table->string('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reparation_logs');
    }
};
