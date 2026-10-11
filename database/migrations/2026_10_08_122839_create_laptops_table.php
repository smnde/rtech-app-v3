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
        Schema::create('laptops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')
                ->constrained()
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained()
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->string('name');
            $table->string('sku')->unique();
            $table->string('code')->nullable();
            $table->string('serialnumber')->unique()->nullable();
            $table->string('warranty');
            $table->integer('stock');
            $table->decimal('buy_price', 15, 2)->nullable();
            $table->decimal('sell_price', 15, 2)->nullable();
            $table->json('accessories')->nullable();
            $table->json('features')->nullable();
            $table->json('specification')->nullable();
            $table->json('issue_notes')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_posted')->default(false);
            $table->enum(
                'status',
                [
                    'ON PROSES',
                    'SIAP JUAL',
                    'DISPLAY',
                    'PENDING',
                    'KENDALA',
                    'HABIS'
                ]
            );
            $table->timestamps();

            $table->index([
                'name',
                'code',
                'serialnumber'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laptops');
    }
};
