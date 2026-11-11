<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Promo yang dipakai di nota ini. Judul disimpan sebagai snapshot
            // supaya nota lama tetap benar walau promo diubah/dihapus.
            $table->foreignId('promo_id')->nullable()->after('user_id')
                ->constrained('promos')->nullOnDelete()->onUpdate('cascade');
            $table->string('promo_title')->nullable()->after('promo_id');
        });

        Schema::table('sales_details', function (Blueprint $table) {
            // Porsi promo yang ditanggung baris ini; sudah dihitung per unit
            // oleh server, jadi laporan tinggal membaca tanpa membagi lagi.
            $table->decimal('promo_discount', 15, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('sales_details', function (Blueprint $table) {
            $table->dropColumn('promo_discount');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_id');
            $table->dropColumn('promo_title');
        });
    }
};
