<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reparation_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('reparation_id')
                ->constrained()
                ->onDelete('restrict')
                ->onUpdate('cascade');
            $table->string('event')->nullable()->after('user_id')->index();
            $table->string('from_value')->nullable()->after('event');
            $table->string('to_value')->nullable()->after('from_value');
            $table->text('note')->nullable()->after('to_value');
            $table->timestamp('occurred_at')->nullable()->after('note')->index();
        });
    }

    public function down(): void
    {
        Schema::table('reparation_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['event', 'from_value', 'to_value', 'note', 'occurred_at']);
        });
    }
};
