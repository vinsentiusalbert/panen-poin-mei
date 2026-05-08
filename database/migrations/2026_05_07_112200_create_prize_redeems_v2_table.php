<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prize_redeems_v2', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('prize_id');
            $table->integer('point_used');
            $table->timestamps();
            $table->timestamp('shipped_at')->nullable();
            $table->string('shipping_proof_path')->nullable();
            $table->string('proof_path')->nullable();

            $table->index('user_id');
            $table->index('prize_id');
            $table->index(['user_id', 'created_at'], 'prize_redeems_v2_user_created_at_index');
        });

        if (Schema::hasTable('prize_redeems') && DB::table('prize_redeems')->exists()) {
            DB::table('prize_redeems_v2')->insertUsing(
                [
                    'id',
                    'user_id',
                    'prize_id',
                    'point_used',
                    'created_at',
                    'updated_at',
                    'shipped_at',
                    'shipping_proof_path',
                    'proof_path',
                ],
                DB::table('prize_redeems')->select(
                    'id',
                    'user_id',
                    'prize_id',
                    'point_used',
                    'created_at',
                    'updated_at',
                    'shipped_at',
                    'shipping_proof_path',
                    'proof_path'
                )
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prize_redeems_v2');
    }
};
