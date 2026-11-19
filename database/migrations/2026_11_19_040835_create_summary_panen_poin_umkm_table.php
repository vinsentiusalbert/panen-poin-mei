<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('summary_panen_poin_umkm')) {
            return;
        }

        DB::statement('CREATE TABLE summary_panen_poin_umkm LIKE summary_panen_poin_v4');

        Schema::table('summary_panen_poin_umkm', function (Blueprint $t) {
            $t->string('period_key', 10)->nullable()->index(); // 'p1' / 'p2'
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summary_panen_poin_umkm');
    }
};