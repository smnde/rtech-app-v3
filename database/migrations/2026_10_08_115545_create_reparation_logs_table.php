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

            $table->foreignId('user_id')->nullable()
                ->constrained()
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->string('type');
            $table->string('event')->nullable()->index();
            $table->string('from_value')->nullable();
            $table->string('to_value')->nullable();
            $table->string('description');
            $table->text('note')->nullable();
            $table->timestamp('occurred_at');
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
