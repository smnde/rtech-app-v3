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

            $table->foreignId('user_id')->nullable()->after('reparation_id')
                ->constrained()
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->string('type');
            $table->string('event')->nullable()->after('type')->index();
            $table->string('from_value')->nullable()->after('event');
            $table->string('to_value')->nullable()->after('from_value');
            $table->string('description');
            $table->text('note')->nullable()->after('to_value');
            $table->timestamp('occurred_at')->nullable()->after('note')->index();
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
