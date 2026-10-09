<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prize_redeems_v4')) {
            return;
        }

        Schema::table('prize_redeems_v4', function (Blueprint $table) {
            if (!Schema::hasColumn('prize_redeems_v4', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable();
            }

            if (!Schema::hasColumn('prize_redeems_v4', 'shipping_proof_path')) {
                $table->string('shipping_proof_path')->nullable();
            }

            if (!Schema::hasColumn('prize_redeems_v4', 'proof_path')) {
                $table->string('proof_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Preserve shipping status and proof paths when rolling back this repair.
    }
};
