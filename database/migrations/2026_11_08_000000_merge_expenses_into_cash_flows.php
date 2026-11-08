<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengeluaran operasional dulu disimpan di tabel `expenses` terpisah sehingga
     * tidak pernah mengurangi saldo kas. Sekarang dilebur ke `cash_flows`
     * (type out, kategori BIAYA) supaya kas jadi satu sumber kebenaran.
     */
    public function up(): void
    {
        DB::table('expenses')->orderBy('id')->chunk(500, function ($expenses) {
            $rows = [];

            foreach ($expenses as $expense) {
                $category = trim((string) $expense->category);
                $description = trim((string) $expense->description);

                $rows[] = [
                    'user_id' => $expense->user_id,
                    'item_type' => null,
                    'item_id' => null,
                    'type' => 'out',
                    'category' => 'BIAYA',
                    'description' => $category !== '' ? "{$category} — {$description}" : $description,
                    'amount' => $expense->amount,
                    'flow_date' => substr((string) $expense->created_at, 0, 10),
                    'reference' => null,
                    'created_at' => $expense->created_at,
                    'updated_at' => $expense->updated_at,
                ];
            }

            if ($rows !== []) {
                DB::table('cash_flows')->insert($rows);
            }
        });

        Schema::dropIfExists('expenses');

        // Menu Pengeluaran dihapus; permission "expense" tidak dipakai lagi.
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', ['show expense', 'add expense', 'edit expense', 'delete expense'])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }

    public function down(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->onUpdate('cascade');

            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }
};
