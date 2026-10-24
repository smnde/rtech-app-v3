<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laptops', function (Blueprint $table) {
            $table->string('image')->nullable()->after('code');
        });

        Schema::table('gadgets', function (Blueprint $table) {
            $table->string('image')->nullable()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('laptops', function (Blueprint $table) {
            $table->dropColumn('image');
        });

        Schema::table('gadgets', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
