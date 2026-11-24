<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $source = 'akun_panen_poin_v4';
        $target = 'akun_panen_poin_v5';
        $email = 'arifasep@gmail.com';

        if (!Schema::hasTable($source) || !Schema::hasTable($target)) {
            throw new RuntimeException('Tabel akun V4 dan V5 harus tersedia sebelum migrasi admin.');
        }

        if (DB::table($target)->where('email_client', $email)->exists()) {
            return;
        }

        $admin = DB::table($source)->where('email_client', $email)->first();
        if (!$admin) {
            throw new RuntimeException('Akun admin tidak ditemukan di V4.');
        }

        // Keep the existing password hash; let V5 allocate its own account ID.
        $columns = array_diff(Schema::getColumnListing($target), ['id', 'remember_token']);
        $attributes = array_intersect_key((array) $admin, array_flip($columns));
        DB::table($target)->insert($attributes);
    }

    public function down(): void
    {
        // Preserve the account and any associated V5 activity on rollback.
    }
};
