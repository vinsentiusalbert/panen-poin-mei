<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prize_redeems_v3')) {
            return;
        }

        Schema::table('prize_redeems_v3', function (Blueprint $table) {
            if (!Schema::hasColumn('prize_redeems_v3', 'point_used')) {
                $table->integer('point_used')->default(0)->after('prize_id');
            }

            if (!Schema::hasColumn('prize_redeems_v3', 'period_start')) {
                $table->date('period_start')->nullable()->after('point_used');
            }

            if (!Schema::hasColumn('prize_redeems_v3', 'period_end')) {
                $table->date('period_end')->nullable()->after('period_start');
            }

            if (!Schema::hasColumn('prize_redeems_v3', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable()->after('updated_at');
            }

            if (!Schema::hasColumn('prize_redeems_v3', 'shipping_proof_path')) {
                $table->string('shipping_proof_path')->nullable()->after('shipped_at');
            }

            if (!Schema::hasColumn('prize_redeems_v3', 'proof_path')) {
                $table->string('proof_path')->nullable()->after('shipping_proof_path');
            }
        });
    }

    public function down(): void
    {
        // Intentionally left blank to avoid destructive schema rollback on live reward data.
    }
};
