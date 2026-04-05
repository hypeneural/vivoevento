<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')
            ->whereNotNull('phone')
            ->get(['id', 'phone']);

        foreach ($users as $user) {
            $digits = preg_replace('/\D+/', '', (string) $user->phone) ?? '';

            if ($digits === '') {
                DB::table('users')->where('id', $user->id)->update(['phone' => null]);
                continue;
            }

            if (! str_starts_with($digits, '55') && in_array(strlen($digits), [10, 11], true)) {
                $digits = '55' . $digits;
            }

            DB::table('users')->where('id', $user->id)->update(['phone' => $digits]);
        }

        $duplicates = DB::table('users')
            ->select('phone')
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Existem telefones duplicados em users.phone. Ajuste os dados antes de aplicar a migracao.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });
    }
};
