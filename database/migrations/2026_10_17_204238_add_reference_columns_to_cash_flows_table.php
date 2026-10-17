<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_flows', function (Blueprint $table) {
            $table->string('item_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('item_id')->nullable()->after('item_type');
            $table->string('reference')->nullable()->after('flow_date');
            $table->index(['item_type', 'item_id']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::table('cash_flows', function (Blueprint $table) {
            $table->dropIndex(['item_type', 'item_id']);
            $table->dropIndex('reference');
            $table->dropColumn(['item_type', 'item_id', 'reference']);
        });
    }
};
