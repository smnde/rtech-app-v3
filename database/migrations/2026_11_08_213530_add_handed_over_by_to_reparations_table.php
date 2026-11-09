<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reparations', function (Blueprint $table) {
            // Kasir/admin yang menyerahkan unit ke customer saat status TAKEN.
            $table->foreignId('handed_over_by')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete()
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('reparations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handed_over_by');
        });
    }
};
