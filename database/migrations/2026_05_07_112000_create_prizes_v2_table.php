<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prizes_v2', function (Blueprint $table) {
            $table->id();
            $table->string('img');
            $table->string('name');
            $table->integer('point');
            $table->integer('stock');
            $table->timestamps();
        });

        if (Schema::hasTable('prizes') && DB::table('prizes')->exists()) {
            DB::table('prizes_v2')->insertUsing(
                ['id', 'img', 'name', 'point', 'stock', 'created_at', 'updated_at'],
                DB::table('prizes')->select('id', 'img', 'name', 'point', 'stock', 'created_at', 'updated_at')
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prizes_v2');
    }
};
